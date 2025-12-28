<?php
require_once 'Conexao.php';

// Inicia sessão caso ainda não esteja ativa
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Define foto de perfil padrão
$foto_perfil_src = 'imagens/FotoPerfilGen.jpg';

// Obtém dados do usuário logado da sessão (ID e nível)
$id_usuario_logado = $_SESSION['usuario'] ?? null;
$nivel_usuario_logado = $_SESSION['nivel'] ?? 0;

// Se usuário está logado, busca foto de perfil no banco e verifica se arquivo existe
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

// Obtém o ID do vídeo a partir do parâmetro GET; se não existir, redireciona com erro
$video_id = $_GET['id'] ?? null;
if (!$video_id) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'ID do vídeo não especificado.'];
    header('Location: Videos.php');
    exit();
}

$video = null;
$questionario = null;
$perguntas = [];
$aluno_ja_respondeu = false;
$desempenho_aluno = null;

// Função para extrair ID do YouTube de uma URL (não usada no restante do código, mas útil)
function getYouTubeVideoId($url)
{
    $pattern = '/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com|youtu\.be)\/(?:watch\?v=|embed\/|v\/|)([a-zA-Z0-9_-]{11})(?:\S+)?/';
    if (preg_match($pattern, $url, $matches)) {
        return $matches[1];
    }
    return false;
}

try {
    // Consulta dados do vídeo (incluindo nome do professor) e só considera vídeos aprovados
    $stmt_video = $conexao->prepare("SELECT v.id, v.titulo, v.url_video, v.descricao, v.data_publicacao, u.nome_usuario AS nome_professor
                                     FROM videos v
                                     JOIN usuarios u ON v.id_professor = u.id_usuario
                                     WHERE v.id = ? AND v.status_moderacao = 'aprovado'");
    $stmt_video->bind_param("i", $video_id);
    $stmt_video->execute();
    $result_video = $stmt_video->get_result();
    $video = $result_video->fetch_assoc();
    $stmt_video->close();

    // Se vídeo não encontrado ou não aprovado, redireciona com mensagem de erro
    if (!$video) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Vídeo não encontrado ou não aprovado.'];
        header('Location: Videos.php');
        exit();
    }

    // Consulta questionário relacionado ao vídeo, se existir
    $stmt_questionario = $conexao->prepare("SELECT id, titulo FROM questionarios WHERE id_video = ?");
    $stmt_questionario->bind_param("i", $video_id);
    $stmt_questionario->execute();
    $result_questionario = $stmt_questionario->get_result();
    $questionario = $result_questionario->fetch_assoc();
    $stmt_questionario->close();

    if ($questionario) {
        // Se usuário logado for aluno, verifica se ele já respondeu ao questionário
        if ($id_usuario_logado && $nivel_usuario_logado == 1) {
            $stmt_desempenho = $conexao->prepare("SELECT pontuacao_total, total_perguntas, porcentagem_acertos FROM desempenho_questionario WHERE id_aluno = ? AND id_questionario = ?");
            $stmt_desempenho->bind_param("ii", $id_usuario_logado, $questionario['id']);
            $stmt_desempenho->execute();
            $result_desempenho = $stmt_desempenho->get_result();
            if ($result_desempenho->num_rows > 0) {
                $aluno_ja_respondeu = true;
                $desempenho_aluno = $result_desempenho->fetch_assoc();
            }
            $stmt_desempenho->close();
        }

        // Se aluno não respondeu ou o usuário tem nível professor/admin, busca perguntas e opções para exibir
        if (!$aluno_ja_respondeu || $nivel_usuario_logado >= 2) {
            $stmt_perguntas = $conexao->prepare("SELECT pq.id AS pergunta_id, pq.texto_pergunta, op.id AS opcao_id, op.texto_opcao, op.is_correta
                                                 FROM perguntas_questionario pq
                                                 JOIN opcoes_resposta op ON pq.id = op.id_pergunta
                                                 WHERE pq.id_questionario = ?
                                                 ORDER BY pq.id, op.id");
            $stmt_perguntas->bind_param("i", $questionario['id']);
            $stmt_perguntas->execute();
            $result_perguntas = $stmt_perguntas->get_result();

            $current_pergunta_id = null;
            while ($row = $result_perguntas->fetch_assoc()) {
                if ($row['pergunta_id'] != $current_pergunta_id) {
                    $current_pergunta_id = $row['pergunta_id'];
                    $perguntas[$current_pergunta_id] = [
                        'id' => $row['pergunta_id'],
                        'texto' => $row['texto_pergunta'],
                        'opcoes' => []
                    ];
                }
                $opcao_data = [
                    'id' => $row['opcao_id'],
                    'texto' => $row['texto_opcao']
                ];

                // Professores/admins veem quais opções são corretas
                if ($nivel_usuario_logado >= 2) {
                    $opcao_data['is_correta'] = (bool) $row['is_correta'];
                }

                $perguntas[$current_pergunta_id]['opcoes'][] = $opcao_data;
            }
            $stmt_perguntas->close();
        }
    }

} catch (Exception $e) {
    // Em caso de erro, registra log, seta mensagem e redireciona
    error_log("Erro ao carregar detalhes do vídeo ou questionário: " . $e->getMessage());
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao carregar os detalhes do vídeo.'];
    header('Location: Videos.php');
    exit();
}

// Processa envio do questionário via POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_questionario'])) {
    // Só alunos podem responder questionário
    if (!$id_usuario_logado || $nivel_usuario_logado != 1) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Você precisa estar logado como aluno para responder ao questionário.'];
        header('Location: VideoDetalhes.php?id=' . $video_id);
        exit();
    }

    // Se aluno já respondeu, avisa e impede novo envio
    if ($aluno_ja_respondeu) {
        $_SESSION['message'] = ['type' => 'warning', 'text' => 'Você já respondeu a este questionário.'];
        header('Location: VideoDetalhes.php?id=' . $video_id);
        exit();
    }

    $respostas_enviadas = $_POST['resposta'] ?? [];
    $pontuacao = 0;

    // Busca perguntas e opções para calcular pontuação
    $perguntas_para_calculo = [];
    $stmt_perguntas_calculo = $conexao->prepare("SELECT pq.id AS pergunta_id, op.id AS opcao_id, op.is_correta
                                                 FROM perguntas_questionario pq
                                                 JOIN opcoes_resposta op ON pq.id = op.id_pergunta
                                                 WHERE pq.id_questionario = ?
                                                 ORDER BY pq.id, op.id");
    $stmt_perguntas_calculo->bind_param("i", $questionario['id']);
    $stmt_perguntas_calculo->execute();
    $result_perguntas_calculo = $stmt_perguntas_calculo->get_result();

    $total_perguntas_questionario = 0;
    while ($row_calc = $result_perguntas_calculo->fetch_assoc()) {
        if (!isset($perguntas_para_calculo[$row_calc['pergunta_id']])) {
            $perguntas_para_calculo[$row_calc['pergunta_id']] = ['opcoes' => []];
            $total_perguntas_questionario++;
        }
        $perguntas_para_calculo[$row_calc['pergunta_id']]['opcoes'][$row_calc['opcao_id']] = (bool) $row_calc['is_correta'];
    }
    $stmt_perguntas_calculo->close();

    // Caso não existam perguntas, não prossegue
    if ($total_perguntas_questionario == 0) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Este questionário não possui perguntas para avaliação.'];
        header('Location: VideoDetalhes.php?id=' . $video_id);
        exit();
    }

    // Começa transação para salvar respostas e desempenho
    $conexao->begin_transaction();
    try {
        // Remove respostas anteriores do aluno para este questionário, se houver
        $stmt_delete_respostas = $conexao->prepare("DELETE FROM respostas_aluno WHERE id_aluno = ? AND id_pergunta IN (SELECT id FROM perguntas_questionario WHERE id_questionario = ?)");
        $stmt_delete_respostas->bind_param("ii", $id_usuario_logado, $questionario['id']);
        $stmt_delete_respostas->execute();
        $stmt_delete_respostas->close();

        // Salva cada resposta enviada e calcula pontuação
        foreach ($perguntas_para_calculo as $pergunta_id => $pergunta_info) {
            $opcao_selecionada_id = $respostas_enviadas[$pergunta_id] ?? null;

            if ($opcao_selecionada_id) {
                $stmt_salvar_resposta = $conexao->prepare("INSERT INTO respostas_aluno (id_aluno, id_pergunta, id_opcao_selecionada) VALUES (?, ?, ?)");
                $stmt_salvar_resposta->bind_param("iii", $id_usuario_logado, $pergunta_id, $opcao_selecionada_id);
                $stmt_salvar_resposta->execute();
                $stmt_salvar_resposta->close();

                // Incrementa pontuação se resposta correta
                if (isset($pergunta_info['opcoes'][$opcao_selecionada_id]) && $pergunta_info['opcoes'][$opcao_selecionada_id]) {
                    $pontuacao++;
                }
            }
        }

        // Calcula porcentagem de acertos
        $porcentagem_acertos = ($total_perguntas_questionario > 0) ? ($pontuacao / $total_perguntas_questionario) * 100 : 0;

        // Insere ou atualiza desempenho do aluno no questionário
        $stmt_desempenho_insert = $conexao->prepare("INSERT INTO desempenho_questionario (id_aluno, id_questionario, pontuacao_total, total_perguntas, porcentagem_acertos) VALUES (?, ?, ?, ?, ?)
                                                    ON DUPLICATE KEY UPDATE pontuacao_total = VALUES(pontuacao_total), total_perguntas = VALUES(total_perguntas), porcentagem_acertos = VALUES(porcentagem_acertos)");
        $stmt_desempenho_insert->bind_param("iiidd", $id_usuario_logado, $questionario['id'], $pontuacao, $total_perguntas_questionario, $porcentagem_acertos);
        $stmt_desempenho_insert->execute();
        $stmt_desempenho_insert->close();

        $conexao->commit();

        // Mensagem de sucesso com pontuação e redirecionamento
        $_SESSION['message'] = ['type' => 'success', 'text' => "Questionário concluído! Sua pontuação: {$pontuacao}/{$total_perguntas_questionario} ({$porcentagem_acertos}% de acerto)."];
        header('Location: VideoDetalhes.php?id=' . $video_id);
        exit();

    } catch (Exception $e) {
        // Em caso de erro, desfaz transação, loga e avisa usuário
        $conexao->rollback();
        error_log("Erro ao salvar questionário: " . $e->getMessage());
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao salvar suas respostas. Por favor, tente novamente.'];
        header('Location: VideoDetalhes.php?id=' . $video_id);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($video['titulo']) ?> - Detalhes do Vídeo</title>
    <link rel="stylesheet" href="styles/Posts.css">
    <link rel="stylesheet" href="styles/Videos.css">
    <link rel="stylesheet" href="styles/VideoDetalhes.css">
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
                <a href="#">Uploads</a>
            </div>
        </div>
        <a href="Perfil.php">
            <img class="profile-icon" src="<?= $foto_perfil_src ?>" alt="Perfil">
        </a>
    </header>

    <main class="container video-details-container">
        <?php
        if (isset($_SESSION['message'])) {
            $msg_type = $_SESSION['message']['type'];
            $msg_text = $_SESSION['message']['text'];
            echo "<div id='alertMessage' class='alert {$msg_type}'>{$msg_text}</div>";
            unset($_SESSION['message']);
        }
        ?>

        <section class="video-info-section">
            <h2 class="video-main-title"><?= htmlspecialchars($video['titulo']) ?></h2>
            <p class="video-main-author">Por: <?= htmlspecialchars($video['nome_professor']) ?></p>
            <div class="video-player-large">
                <?php
                $youtube_id = getYouTubeVideoId($video['url_video']);
                if ($youtube_id): ?>
                    <iframe src="https://www.youtube.com/embed/<?= htmlspecialchars($youtube_id) ?>" frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen>
                    </iframe>
                <?php else: ?>
                    <p>Não foi possível carregar o vídeo. URL inválida.</p>
                <?php endif; ?>
            </div>
            <div class="video-main-description">
                <h3>Sobre esta aula:</h3>
                <p><?= nl2br(htmlspecialchars($video['descricao'])) ?></p>
            </div>
        </section>

        <?php if ($questionario): ?>
            <section class="questionario-section">
                <h3>Questionário: <?= htmlspecialchars($questionario['titulo']) ?></h3>

                <?php if ($aluno_ja_respondeu): ?>
                    <div class="quiz-result-summary">
                        <p>Você já respondeu a este questionário.</p>
                        <p>Sua pontuação: <strong><?= htmlspecialchars($desempenho_aluno['pontuacao_total']) ?> /
                                <?= htmlspecialchars($desempenho_aluno['total_perguntas']) ?></strong></p>
                        <p>Porcentagem de acertos:
                            <strong><?= htmlspecialchars($desempenho_aluno['porcentagem_acertos']) ?>%</strong></p>
                    </div>
                <?php elseif ($nivel_usuario_logado == 1): ?>
                    <?php if (!empty($perguntas)): ?>
                        <form action="VideoDetalhes.php?id=<?= htmlspecialchars($video_id) ?>" method="POST" class="quiz-form">
                            <?php foreach ($perguntas as $pergunta): ?>
                                <div class="pergunta-item">
                                    <p class="pergunta-texto"><?= htmlspecialchars($pergunta['texto']) ?></p>
                                    <div class="opcoes-resposta">
                                        <?php foreach ($pergunta['opcoes'] as $opcao): ?>
                                            <label>
                                                <input type="radio" name="resposta[<?= htmlspecialchars($pergunta['id']) ?>]"
                                                    value="<?= htmlspecialchars($opcao['id']) ?>" required>
                                                <?= htmlspecialchars($opcao['texto']) ?>
                                            </label><br>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <button type="submit" name="submit_questionario">Enviar Respostas</button>
                        </form>
                    <?php else: ?>
                        <p style="text-align: center; color: #AAA;">Este vídeo ainda não possui um questionário.</p>
                    <?php endif; ?>
                <?php elseif ($nivel_usuario_logado >= 2): ?>
                    <div class="admin-quiz-view">
                        <p>Como professor/administrador, você pode gerenciar este questionário.</p>
                        <?php if (!empty($perguntas)): ?>
                            <h4>Perguntas Cadastradas:</h4>
                            <?php foreach ($perguntas as $pergunta): ?>
                                <div class="pergunta-item-admin">
                                    <p><strong>P:</strong> <?= htmlspecialchars($pergunta['texto']) ?></p>
                                    <ul>
                                        <?php foreach ($pergunta['opcoes'] as $opcao): ?>
                                            <li
                                                class="<?= (isset($opcao['is_correta']) && $opcao['is_correta']) ? 'correct-option' : '' ?>">
                                                <?= htmlspecialchars($opcao['texto']) ?>
                                                <?php if (isset($opcao['is_correta']) && $opcao['is_correta']): ?>
                                                    (Correta)
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>Este questionário ainda não possui perguntas.</p>
                        <?php endif; ?>
                        <a href="GerenciarQuestionarios.php?video_id=<?= htmlspecialchars($video['id']) ?>"
                            class="btn-manage-quiz">Gerenciar Questionário</a>
                    </div>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <section class="questionario-section">
                <p style="text-align: center; color: #AAA;">Este vídeo ainda não possui um questionário associado.</p>
                <?php if ($nivel_usuario_logado >= 2): ?>
                    <div class="admin-quiz-view">
                        <a href="GerenciarQuestionarios.php?video_id=<?= htmlspecialchars($video['id']) ?>"
                            class="btn-manage-quiz">Criar Questionário para este Vídeo</a>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>


    <script src="script/AlertasGerais.js"></script>

</body>

</html>