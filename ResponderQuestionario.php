<?php
require_once 'Conexao.php';
require_once 'FuncoesConquistas.php';
require_once 'FuncoesNotificacao.php';

// Inicia a sessão caso não esteja ativa
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Define constantes para níveis de usuário
define('ALUNO', 1);
define('ADMIN', 2);
define('PROFESSOR', 3);

// Verifica se o usuário logado tem nível ALUNO para poder responder questionários
if (!isset($_SESSION['nivel']) || $_SESSION['nivel'] != ALUNO) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Acesso negado. Você não tem permissão para responder questionários.'];
    header('Location: MenuPrincipal.php');
    exit();
}

// Obtém ID e nível do usuário logado da sessão
$id_usuario_logado = $_SESSION['usuario'] ?? null;
$nivel_usuario_logado = $_SESSION['nivel'] ?? ALUNO;

// Caminho padrão para foto de perfil genérica
$foto_perfil_src = 'imagens/FotoPerfilGen.jpg';

// Se há usuário logado, busca o nome do arquivo da foto de perfil no banco
if ($id_usuario_logado) {
    $id_usuario_logado = (int) $id_usuario_logado;
    $query_foto_db = "SELECT foto_perfil FROM usuarios WHERE id_usuario = ?";
    $stmt_foto_db = mysqli_prepare($conexao, $query_foto_db);
    if ($stmt_foto_db) {
        mysqli_stmt_bind_param($stmt_foto_db, "i", $id_usuario_logado);
        mysqli_stmt_execute($stmt_foto_db);
        $result_foto_db = mysqli_stmt_get_result($stmt_foto_db);
        if ($row_foto = mysqli_fetch_assoc($result_foto_db)) {
            if (!empty($row_foto['foto_perfil'])) {
                $temp_foto_path = 'uploads/perfis/' . htmlspecialchars($row_foto['foto_perfil']);
                if (file_exists($temp_foto_path)) {
                    $foto_perfil_src = $temp_foto_path;
                    $_SESSION['foto_perfil'] = $row_foto['foto_perfil'];
                }
            }
        }
        mysqli_stmt_close($stmt_foto_db);
    }
}

// Obtém o ID do vídeo via GET para saber qual questionário responder
$video_id = $_GET['video_id'] ?? null;

$video_titulo = '';
$questionario_id = null;
$questionario_titulo = '';
$perguntas_para_responder = [];
$aluno_ja_respondeu = false; // Flag para controlar se o aluno já respondeu
$resultado_anterior = null; // Para guardar o resultado se já respondeu
$respostas_corretas_gab = []; // Para armazenar as respostas corretas para o gabarito

// Caso haja o video_id, busca título do vídeo e questionário relacionado
if ($video_id) {
    // Busca título do vídeo para exibir na página
    $stmt_video = $conexao->prepare("SELECT titulo FROM videos WHERE id = ?");
    $stmt_video->bind_param("i", $video_id);
    $stmt_video->execute();
    $result_video = $stmt_video->get_result();
    if ($row_video = $result_video->fetch_assoc()) {
        $video_titulo = $row_video['titulo'];
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Vídeo não encontrado.'];
        header('Location: Videos.php');
        exit();
    }
    $stmt_video->close();

    // Busca questionário associado ao vídeo
    $stmt_questionario = $conexao->prepare("SELECT id, titulo, pontuacao_maxima FROM questionarios WHERE id_video = ?");
    $stmt_questionario->bind_param("i", $video_id);
    $stmt_questionario->execute();
    $result_questionario = $stmt_questionario->get_result();
    if ($row_questionario = $result_questionario->fetch_assoc()) {
        $questionario_id = $row_questionario['id'];
        $questionario_titulo = $row_questionario['titulo'];
        $pontuacao_maxima_questionario = $row_questionario['pontuacao_maxima'];

        // --- VERIFICA SE O ALUNO JÁ RESPONDEU ---
        $stmt_check_respondeu = $conexao->prepare("SELECT pontuacao_obtida, total_perguntas FROM tentativas_questionarios WHERE id_usuario = ? AND id_questionario = ?");
        $stmt_check_respondeu->bind_param("ii", $id_usuario_logado, $questionario_id);
        $stmt_check_respondeu->execute();
        $result_check = $stmt_check_respondeu->get_result();
        if ($result_check->num_rows > 0) {
            $aluno_ja_respondeu = true;
            $resultado_anterior = $result_check->fetch_assoc();
        }
        $stmt_check_respondeu->close();

        // Busca as perguntas, opções e RESPOSTAS CORRETAS para o gabarito
        $stmt_perguntas_completo = $conexao->prepare("
            SELECT pq.id AS pergunta_id, pq.texto_pergunta, op.id AS opcao_id, op.texto_opcao, op.is_correta
            FROM perguntas_questionario pq
            LEFT JOIN opcoes_resposta op ON pq.id = op.id_pergunta
            WHERE pq.id_questionario = ?
            ORDER BY pq.id, op.id
        ");
        $stmt_perguntas_completo->bind_param("i", $questionario_id);
        $stmt_perguntas_completo->execute();
        $result_perguntas_completo = $stmt_perguntas_completo->get_result();

        $current_pergunta_id = null;
        while ($row = $result_perguntas_completo->fetch_assoc()) {
            if ($row['pergunta_id'] != $current_pergunta_id) {
                $current_pergunta_id = $row['pergunta_id'];
                $perguntas_para_responder[$current_pergunta_id] = [
                    'id' => $row['pergunta_id'],
                    'texto' => $row['texto_pergunta'],
                    'opcoes' => []
                ];
            }
            if ($row['opcao_id']) {
                $perguntas_para_responder[$current_pergunta_id]['opcoes'][] = [
                    'id' => $row['opcao_id'],
                    'texto' => $row['texto_opcao'],
                    'is_correta' => (bool) $row['is_correta'] // Cast para booleano
                ];
                // Se for a resposta correta, guarda para o gabarito
                if ($row['is_correta']) {
                    $respostas_corretas_gab[$row['pergunta_id']] = $row['opcao_id'];
                }
            }
        }
        $stmt_perguntas_completo->close();

    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Questionário não encontrado para este vídeo.'];
        header('Location: Videos.php');
        exit();
    }
    $stmt_questionario->close();
} else {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'ID do vídeo não especificado para responder questionário.'];
    header('Location: Videos.php');
    exit();
}


// --- Processa o envio das respostas do questionário ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_respostas_questionario'])) {
    if ($aluno_ja_respondeu) { // Impede reenvio se já respondeu
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Você já respondeu a este questionário anteriormente.'];
        // Não redireciona, a página vai mostrar o resultado anterior
        header('Location: ResponderQuestionario.php?video_id=' . htmlspecialchars($video_id));
        exit();
    }

    $respostas_do_aluno = $_POST['respostas'] ?? [];
    $pontuacao = 0;
    $total_perguntas = count($perguntas_para_responder);
    $respostas_aluno_formatado = []; // Para guardar as respostas do aluno para o gabarito

    // Busca as respostas corretas no banco para comparar
    $respostas_corretas_db = [];
    $stmt_corretas = $conexao->prepare("
        SELECT pq.id AS pergunta_id, op.id AS opcao_correta_id
        FROM perguntas_questionario pq
        JOIN opcoes_resposta op ON pq.id = op.id_pergunta
        WHERE pq.id_questionario = ? AND op.is_correta = 1
    ");
    if ($stmt_corretas) {
        $stmt_corretas->bind_param("i", $questionario_id);
        $stmt_corretas->execute();
        $result_corretas = $stmt_corretas->get_result();
        while ($row_correta = $result_corretas->fetch_assoc()) {
            $respostas_corretas_db[$row_correta['pergunta_id']] = $row_correta['opcao_correta_id'];
        }
        $stmt_corretas->close();
    }

    // Compara respostas do aluno com as corretas para somar pontuação
    foreach ($perguntas_para_responder as $pergunta_id => $pergunta_info) {
        $resposta_aluno_para_pergunta = $respostas_do_aluno[$pergunta_id] ?? null;
        $respostas_aluno_formatado[$pergunta_id] = $resposta_aluno_para_pergunta; // Guarda para o gabarito

        if (isset($respostas_corretas_db[$pergunta_id])) {
            $opcao_correta_id = $respostas_corretas_db[$pergunta_id];
            if ($resposta_aluno_para_pergunta == $opcao_correta_id) {
                $pontuacao++;
            }
        }
    }

    try {
        // Insere resultado do questionário no banco na nova tabela tentativas_questionarios
        $stmt_insert_resultado = $conexao->prepare("INSERT INTO tentativas_questionarios (id_usuario, id_questionario, pontuacao_obtida, total_perguntas, data_tentativa) VALUES (?, ?, ?, ?, NOW())");
        if (!$stmt_insert_resultado)
            throw new Exception("Erro ao preparar insert resultado: " . $conexao->error);
        $stmt_insert_resultado->bind_param("iiii", $id_usuario_logado, $questionario_id, $pontuacao, $total_perguntas);
        $stmt_insert_resultado->execute();
        $stmt_insert_resultado->close();

        // Atualiza a flag e o resultado para exibir a tela de gabarito
        $aluno_ja_respondeu = true;
        $resultado_anterior = [
            'pontuacao_obtida' => $pontuacao,
            'total_perguntas' => $total_perguntas
        ];

        $conquistas_ganhas = [];

        // Verifica e desbloqueia conquistas
        $conquistas_ganhas = array_merge($conquistas_ganhas, verificar_e_desbloquear_conquistas($conexao, $id_usuario_logado, 'questionarios_respondidos'));
        if ($pontuacao == $pontuacao_maxima_questionario && $pontuacao_maxima_questionario > 0) {
            $conquistas_ganhas = array_merge($conquistas_ganhas, verificar_e_desbloquear_conquistas($conexao, $id_usuario_logado, 'questionarios_100_porcento'));
        }

        if (!empty($conquistas_ganhas)) {
            $_SESSION['novas_conquistas'] = $conquistas_ganhas;
        }

        // Armazena as respostas do aluno e as corretas na sessão para exibir o gabarito
        $_SESSION['respostas_aluno_ultima_tentativa'] = $respostas_aluno_formatado;
        $_SESSION['respostas_corretas_gab'] = $respostas_corretas_gab;

        // Redireciona para a mesma página, mas agora com o flag $aluno_ja_respondeu ativo
        header('Location: ResponderQuestionario.php?video_id=' . htmlspecialchars($video_id));
        exit();

    } catch (mysqli_sql_exception $e) {
        // Erro de UNIQUE constraint (aluno já respondeu)
        if ($e->getCode() == 1062) { // 1062 é o código de erro para UNIQUE constraint violation no MySQL
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Você já respondeu a este questionário anteriormente.'];
        } else {
            error_log("Erro ao salvar resultado do questionário: " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'error', 'text' => "Erro ao salvar sua pontuação. Por favor, tente novamente."];
        }
        // Não redireciona, a página vai mostrar o resultado anterior ou o formulário desabilitado
        header('Location: ResponderQuestionario.php?video_id=' . htmlspecialchars($video_id));
        exit();
    } catch (Exception $e) {
        error_log("Erro geral ao salvar resultado do questionário: " . $e->getMessage());
        $_SESSION['message'] = ['type' => 'error', 'text' => "Erro ao salvar sua pontuação. Por favor, tente novamente."];
        // Não redireciona, a página vai mostrar o resultado anterior ou o formulário desabilitado
        header('Location: ResponderQuestionario.php?video_id=' . htmlspecialchars($video_id));
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Responder Questionário - <?= htmlspecialchars($video_titulo) ?></title>
    <link rel="stylesheet" href="styles/Posts.css">
    <link rel="stylesheet" href="styles/Videos.css">
    <link rel="stylesheet" href="styles/ResponderQuestionario.css">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="icon" href="imagens/Logo_Neez.png">
</head>

<body>
    <header>
        <div class="LogoDiv">
            <a href="MenuPrincipal.php" class="logo-link">
                <img class="LogoEtec" src="imagens/Etec_Logo.webp" alt="Logo da Etec">
            </a>
        </div>
        <div class="nav-container">
            <div class="nav-links">
                <a href="Posts.php">Posts</a>
                <a href="Videos.php">Vídeos</a>
                <a href="Uploads.php">Uploads</a>
            </div>
        </div>
        <a href="Perfil.php">
            <img class="profile-icon" src="<?= $foto_perfil_src ?>" alt="Perfil">
        </a>
    </header>

    <main class="container">
        <h2>Questionário para: <br>"<?= htmlspecialchars($video_titulo) ?>"</h2>
        <h3><?= htmlspecialchars($questionario_titulo) ?></h3>

        <?php
        // Exibe mensagens de sucesso/erro
        if (isset($_SESSION['message'])) {
            $msg_type = $_SESSION['message']['type'];
            $msg_text = $_SESSION['message']['text'];
            echo "<div id='alertMessage' class='alert {$msg_type}'>{$msg_text}</div>";
            unset($_SESSION['message']);
        }
        // Exibe novas conquistas
        if (isset($_SESSION['novas_conquistas']) && !empty($_SESSION['novas_conquistas'])) {
            echo '<div class="alert success">';
            echo '<strong>Parabéns! Novas Conquistas Desbloqueadas:</strong><br>';
            echo '<ul>';
            foreach ($_SESSION['novas_conquistas'] as $conquista_nome) {
                echo '<li>' . htmlspecialchars($conquista_nome) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
            unset($_SESSION['novas_conquistas']);
        }
        ?>

        <?php if ($aluno_ja_respondeu): // Se o aluno já respondeu, exibe o gabarito ?>
            <div class="resultado-questionario">
                <h3>Seu Resultado</h3>
                <?php
                $pontuacao_obtida = $resultado_anterior['pontuacao_obtida'];
                $total_perguntas = $resultado_anterior['total_perguntas'];
                $percentual_acertos = ($total_perguntas > 0) ? round(($pontuacao_obtida / $total_perguntas) * 100, 2) : 0;
                ?>
                <p class="pontuacao-final">Você acertou: <strong><?= $pontuacao_obtida ?></strong> de
                    <strong><?= $total_perguntas ?></strong> perguntas.</p>
                <p class="percentual-final">Aproveitamento: <strong><?= $percentual_acertos ?>%</strong></p>

                <h4>Gabarito</h4>
                <?php
                // Recupera as respostas do aluno e as corretas da sessão para exibir o gabarito
                // Se a página foi carregada após submissão, elas estarão na sessão.
                // Se foi carregada porque o aluno já respondeu antes, precisaremos puxar as respostas do banco (ou apenas o gabarito geral)
                $respostas_aluno_para_gabarito = $_SESSION['respostas_aluno_ultima_tentativa'] ?? []; // Armazena as escolhas do aluno na última tentativa
                unset($_SESSION['respostas_aluno_ultima_tentativa']); // Limpa após uso
            
                foreach ($perguntas_para_responder as $pergunta_id => $pergunta):
                    $opcao_selecionada_pelo_aluno = $respostas_aluno_para_gabarito[$pergunta_id] ?? null;
                    $opcao_correta_para_gabarito = $respostas_corretas_gab[$pergunta_id] ?? null;
                    ?>
                    <div class="pergunta-gabarito-card">
                        <p class="pergunta-texto"><strong><?= htmlspecialchars($pergunta['texto']) ?></strong></p>
                        <div class="opcoes-gabarito-container">
                            <?php foreach ($pergunta['opcoes'] as $opcao): ?>
                                <?php
                                $class_opcao = '';
                                if ($opcao['is_correta']) {
                                    $class_opcao = 'opcao-correta'; // Verde para a correta
                                } elseif ($opcao['id'] == $opcao_selecionada_pelo_aluno && $opcao['id'] != $opcao_correta_para_gabarito) {
                                    $class_opcao = 'opcao-errada'; // Vermelho para a errada selecionada
                                }
                                ?>
                                <p class="opcao-gabarito <?= $class_opcao ?>">
                                    <?= htmlspecialchars($opcao['texto']) ?>
                                    <?php if ($opcao['is_correta']): ?>
                                        <i class="fas fa-check-circle"></i>
                                    <?php elseif ($opcao['id'] == $opcao_selecionada_pelo_aluno && $opcao['id'] != $opcao_correta_para_gabarito): ?>
                                        <i class="fas fa-times-circle"></i> <?php endif; ?>
                                </p>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="Videos.php" class="btn-voltar-videos">Voltar para Vídeos</a>
                </div>
            </div>
        <?php else: // Se o aluno não respondeu, exibe o formulário normal ?>
            <?php if (!empty($perguntas_para_responder)): ?>
                <form action="ResponderQuestionario.php?video_id=<?= htmlspecialchars($video_id) ?>" method="POST"
                    id="quizRespostaForm">
                    <?php foreach ($perguntas_para_responder as $pergunta_id => $pergunta): ?>
                        <div class="pergunta-card-resposta">
                            <h4><?= htmlspecialchars($pergunta['texto']) ?></h4>
                            <div class="opcoes-resposta-container">
                                <?php shuffle($pergunta['opcoes']); // Embaralha as opções ?>
                                <?php foreach ($pergunta['opcoes'] as $opcao): ?>
                                    <label class="opcao-radio-label">
                                        <input type="radio" name="respostas[<?= htmlspecialchars($pergunta['id']) ?>]"
                                            value="<?= htmlspecialchars($opcao['id']) ?>" required>
                                        <?= htmlspecialchars($opcao['texto']) ?>
                                    </label><br>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <button type="submit" name="submit_respostas_questionario" class="submit-btn">Enviar Respostas</button>
                </form>
            <?php else: ?>
                <p style="text-align: center; color: #AAA;">Nenhum questionário encontrado para este vídeo, ou o questionário
                    não possui perguntas.</p>
            <?php endif; ?>
        <?php endif; ?>
    </main>


    <script src="script/AlertasGerais.js"></script>


</body>

</html>