<?php
require_once 'Conexao.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verifica se o usuário tem nível ADMIN (2) ou PROFESSOR (3) para acessar o gerenciamento
if (!isset($_SESSION['nivel']) || ($_SESSION['nivel'] != 2 && $_SESSION['nivel'] != 3)) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Acesso negado. Você não tem permissão para gerenciar questionários.'];
    header('Location: MenuPrincipal.php');
    exit();
}

$id_usuario_logado = $_SESSION['usuario'] ?? null;
$nivel_usuario_logado = $_SESSION['nivel'] ?? 0;

// Define foto padrão e busca foto de perfil do usuário logado
$foto_perfil_src = 'imagens/FotoPerfilGen.jpg';
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

// Recebe o ID do vídeo para carregar os dados do questionário associado
$video_id = $_GET['video_id'] ?? null;
$video_titulo = '';
$questionario_id = null;
$questionario_titulo = '';
$perguntas_existentes = [];

if ($video_id) {
    // Busca o título do vídeo
    $stmt_video = $conexao->prepare("SELECT titulo FROM videos WHERE id = ?");
    $stmt_video->bind_param("i", $video_id);
    $stmt_video->execute();
    $result_video = $stmt_video->get_result();
    if ($row_video = $result_video->fetch_assoc()) {
        $video_titulo = $row_video['titulo'];
    } else {
        // Caso vídeo não seja encontrado, redireciona com erro
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Vídeo não encontrado.'];
        header('Location: Videos.php');
        exit();
    }
    $stmt_video->close();

    // Busca questionário vinculado ao vídeo
    $stmt_questionario = $conexao->prepare("SELECT id, titulo FROM questionarios WHERE id_video = ?");
    $stmt_questionario->bind_param("i", $video_id);
    $stmt_questionario->execute();
    $result_questionario = $stmt_questionario->get_result();
    if ($row_questionario = $result_questionario->fetch_assoc()) {
        $questionario_id = $row_questionario['id'];
        $questionario_titulo = $row_questionario['titulo'];

        // Busca perguntas e suas opções para o questionário
        $stmt_perguntas = $conexao->prepare("SELECT pq.id AS pergunta_id, pq.texto_pergunta, op.id AS opcao_id, op.texto_opcao, op.is_correta
                                             FROM perguntas_questionario pq
                                             LEFT JOIN opcoes_resposta op ON pq.id = op.id_pergunta
                                             WHERE pq.id_questionario = ?
                                             ORDER BY pq.id, op.id");
        $stmt_perguntas->bind_param("i", $questionario_id);
        $stmt_perguntas->execute();
        $result_perguntas = $stmt_perguntas->get_result();

        // Organiza perguntas e opções em array multidimensional para manipulação posterior
        $current_pergunta_id = null;
        while ($row = $result_perguntas->fetch_assoc()) {
            if ($row['pergunta_id'] != $current_pergunta_id) {
                $current_pergunta_id = $row['pergunta_id'];
                $perguntas_existentes[$current_pergunta_id] = [
                    'id' => $row['pergunta_id'],
                    'texto' => $row['texto_pergunta'],
                    'opcoes' => []
                ];
            }
            if ($row['opcao_id']) {
                $perguntas_existentes[$current_pergunta_id]['opcoes'][] = [
                    'id' => $row['opcao_id'],
                    'texto' => $row['texto_opcao'],
                    'is_correta' => $row['is_correta']
                ];
            }
        }
        $stmt_perguntas->close();
    }
    $stmt_questionario->close();
} else {
    // Se não recebeu o ID do vídeo, redireciona com erro
    $_SESSION['message'] = ['type' => 'error', 'text' => 'ID do vídeo não especificado para gerenciar questionário.'];
    header('Location: Videos.php');
    exit();
}

// Processa o envio do formulário para salvar ou atualizar questionário e perguntas
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_gerenciar_questionario'])) {
    $novo_questionario_titulo = trim($_POST['questionario_titulo'] ?? '');
    $perguntas_data = $_POST['perguntas'] ?? [];

    if (empty($novo_questionario_titulo)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'O título do questionário é obrigatório.'];
        header('Location: GerenciarQuestionarios.php?video_id=' . $video_id);
        exit();
    }

    // Inicia transação para garantir atomicidade das operações no banco
    $conexao->begin_transaction();
    try {
        // Atualiza título do questionário existente ou insere um novo
        if ($questionario_id) {
            $stmt_update_quiz = $conexao->prepare("UPDATE questionarios SET titulo = ? WHERE id = ?");
            if (!$stmt_update_quiz)
                throw new Exception("Erro ao preparar update quiz: " . $conexao->error);
            $stmt_update_quiz->bind_param("si", $novo_questionario_titulo, $questionario_id);
            $stmt_update_quiz->execute();
            $stmt_update_quiz->close();
        } else {
            $stmt_insert_quiz = $conexao->prepare("INSERT INTO questionarios (id_video, titulo) VALUES (?, ?)");
            if (!$stmt_insert_quiz)
                throw new Exception("Erro ao preparar insert quiz: " . $conexao->error);
            $stmt_insert_quiz->bind_param("is", $video_id, $novo_questionario_titulo);
            $stmt_insert_quiz->execute();
            $questionario_id = $conexao->insert_id;
            $stmt_insert_quiz->close();
        }

        $perguntas_ids_manter = [];
        // Itera pelas perguntas enviadas para salvar ou atualizar
        foreach ($perguntas_data as $temp_pergunta_id => $pergunta_info) {
            $pergunta_texto = trim($pergunta_info['texto'] ?? '');
            $opcoes_texto = $pergunta_info['opcoes'] ?? [];
            $opcao_correta_idx = $pergunta_info['correta'] ?? null;
            $pergunta_db_id = $pergunta_info['id'] ?? null;

            if (empty($pergunta_texto)) {
                continue; // pula perguntas sem texto
            }

            $current_pergunta_id = null;
            if ($pergunta_db_id) {
                // Atualiza pergunta existente
                $stmt_update_pergunta = $conexao->prepare("UPDATE perguntas_questionario SET texto_pergunta = ? WHERE id = ? AND id_questionario = ?");
                if (!$stmt_update_pergunta)
                    throw new Exception("Erro ao preparar update pergunta: " . $conexao->error);
                $stmt_update_pergunta->bind_param("sii", $pergunta_texto, $pergunta_db_id, $questionario_id);
                $stmt_update_pergunta->execute();
                $stmt_update_pergunta->close();
                $current_pergunta_id = $pergunta_db_id;
            } else {
                // Insere nova pergunta
                $stmt_insert_pergunta = $conexao->prepare("INSERT INTO perguntas_questionario (id_questionario, texto_pergunta) VALUES (?, ?)");
                if (!$stmt_insert_pergunta)
                    throw new Exception("Erro ao preparar insert pergunta: " . $conexao->error);
                $stmt_insert_pergunta->bind_param("is", $questionario_id, $pergunta_texto);
                $stmt_insert_pergunta->execute();
                $current_pergunta_id = $conexao->insert_id;
                $stmt_insert_pergunta->close();
            }
            $perguntas_ids_manter[] = $current_pergunta_id;

            $opcoes_ids_manter = [];
            // Itera pelas opções para salvar ou atualizar
            foreach ($opcoes_texto as $temp_opcao_id => $opcao_texto) {
                $opcao_texto = trim($opcao_texto);
                $is_correta = ($temp_opcao_id == $opcao_correta_idx);
                $opcao_db_id = $pergunta_info['opcoes_ids'][$temp_opcao_id] ?? null;

                if (empty($opcao_texto)) {
                    continue; // pula opções sem texto
                }

                if ($opcao_db_id) {
                    // Atualiza opção existente
                    $stmt_update_opcao = $conexao->prepare("UPDATE opcoes_resposta SET texto_opcao = ?, is_correta = ? WHERE id = ? AND id_pergunta = ?");
                    if (!$stmt_update_opcao)
                        throw new Exception("Erro ao preparar update opcao: " . $conexao->error);
                    $stmt_update_opcao->bind_param("siii", $opcao_texto, $is_correta, $opcao_db_id, $current_pergunta_id);
                    $stmt_update_opcao->execute();
                    $stmt_update_opcao->close();
                    $opcoes_ids_manter[] = $opcao_db_id;
                } else {
                    // Insere nova opção
                    $stmt_insert_opcao = $conexao->prepare("INSERT INTO opcoes_resposta (id_pergunta, texto_opcao, is_correta) VALUES (?, ?, ?)");
                    if (!$stmt_insert_opcao)
                        throw new Exception("Erro ao preparar insert opcao: " . $conexao->error);
                    $stmt_insert_opcao->bind_param("isi", $current_pergunta_id, $opcao_texto, $is_correta);
                    $stmt_insert_opcao->execute();
                    $opcoes_ids_manter[] = $conexao->insert_id;
                    $stmt_insert_opcao->close();
                }
            }

            // Remove opções que não foram mantidas
            if (!empty($opcoes_ids_manter)) {
                $placeholders = implode(',', array_fill(0, count($opcoes_ids_manter), '?'));
                $stmt_delete_opcoes = $conexao->prepare("DELETE FROM opcoes_resposta WHERE id_pergunta = ? AND id NOT IN ($placeholders)");
                if (!$stmt_delete_opcoes)
                    throw new Exception("Erro ao preparar delete opcoes: " . $conexao->error);
                $types = 'i' . str_repeat('i', count($opcoes_ids_manter));
                $params = array_merge([$current_pergunta_id], $opcoes_ids_manter);
                $stmt_delete_opcoes->bind_param($types, ...$params);
                $stmt_delete_opcoes->execute();
                $stmt_delete_opcoes->close();
            } else {
                // Remove todas as opções se nenhuma for mantida
                $stmt_delete_all_opcoes = $conexao->prepare("DELETE FROM opcoes_resposta WHERE id_pergunta = ?");
                if (!$stmt_delete_all_opcoes)
                    throw new Exception("Erro ao preparar delete all opcoes: " . $conexao->error);
                $stmt_delete_all_opcoes->bind_param("i", $current_pergunta_id);
                $stmt_delete_all_opcoes->execute();
                $stmt_delete_all_opcoes->close();
            }
        }

        // Remove perguntas que não foram mantidas
        if (!empty($perguntas_ids_manter)) {
            $placeholders = implode(',', array_fill(0, count($perguntas_ids_manter), '?'));
            $stmt_delete_perguntas = $conexao->prepare("DELETE FROM perguntas_questionario WHERE id_questionario = ? AND id NOT IN ($placeholders)");
            if (!$stmt_delete_perguntas)
                throw new Exception("Erro ao preparar delete perguntas: " . $conexao->error);
            $types = 'i' . str_repeat('i', count($perguntas_ids_manter));
            $params = array_merge([$questionario_id], $perguntas_ids_manter);
            $stmt_delete_perguntas->bind_param($types, ...$params);
            $stmt_delete_perguntas->execute();
            $stmt_delete_perguntas->close();
        } else {
            // Remove todas as perguntas se nenhuma for mantida
            $stmt_delete_all_perguntas = $conexao->prepare("DELETE FROM perguntas_questionario WHERE id_questionario = ?");
            if (!$stmt_delete_all_perguntas)
                throw new Exception("Erro ao preparar delete all perguntas: " . $conexao->error);
            $stmt_delete_all_perguntas->bind_param("i", $questionario_id);
            $stmt_delete_all_perguntas->execute();
            $stmt_delete_all_perguntas->close();
        }

        // Confirma as alterações no banco
        $conexao->commit();
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Questionário salvo com sucesso!'];
        header('Location: Videos.php');
        exit();

    } catch (Exception $e) {
        // Reverte alterações em caso de erro
        $conexao->rollback();
        error_log("Erro ao gerenciar questionário: " . $e->getMessage());
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao salvar o questionário. Por favor, tente novamente. Detalhes: ' . $e->getMessage()];
        header('Location: GerenciarQuestionarios.php?video_id=' . $video_id);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Questionário - <?= htmlspecialchars($video_titulo) ?></title>
    <link rel="stylesheet" href="styles/Posts.css">
    <link rel="stylesheet" href="styles/Videos.css">
    <link rel="stylesheet" href="styles/GerenciarQuestionarios.css">
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

    <main class="container">
        <h2>Gerenciar Questionário para: <br>"<?= htmlspecialchars($video_titulo) ?>"</h2>

        <?php
        if (isset($_SESSION['message'])) {
            $msg_type = $_SESSION['message']['type'];
            $msg_text = $_SESSION['message']['text'];
            echo "<div id='alertMessage' class='alert {$msg_type}'>{$msg_text}</div>";
            unset($_SESSION['message']);
        }
        ?>

        <section class="questionario-manager-section">
            <form action="GerenciarQuestionarios.php?video_id=<?= htmlspecialchars($video_id) ?>" method="POST"
                id="quizForm">
                <label for="questionario_titulo">Título do Questionário:</label>
                <input type="text" id="questionario_titulo" name="questionario_titulo"
                    value="<?= htmlspecialchars($questionario_titulo) ?>" placeholder="Ex: Questionário sobre a Aula 1"
                    required>

                <div id="perguntas-container">
                    <?php
                    $pergunta_idx_js = 0;
                    foreach ($perguntas_existentes as $pergunta_id_db => $pergunta):
                        $current_pergunta_js_index = $pergunta_idx_js++;
                        ?>
                        <div class="pergunta-card" data-pergunta-uuid="<?= $current_pergunta_js_index ?>">
                            <input type="hidden" name="perguntas[<?= $current_pergunta_js_index ?>][id]"
                                value="<?= htmlspecialchars($pergunta['id']) ?>">
                            <label>Pergunta:</label>
                            <textarea name="perguntas[<?= $current_pergunta_js_index ?>][texto]" rows="3"
                                required><?= htmlspecialchars($pergunta['texto']) ?></textarea>
                            <div class="opcoes-container">
                                <?php foreach ($pergunta['opcoes'] as $opcao_idx => $opcao): ?>
                                    <div class="opcao-item">
                                        <input type="hidden"
                                            name="perguntas[<?= $current_pergunta_js_index ?>][opcoes_ids][<?= $opcao_idx ?>]"
                                            value="<?= htmlspecialchars($opcao['id']) ?>">
                                        <label>Opção <?= $opcao_idx + 1 ?>:</label>
                                        <input type="text"
                                            name="perguntas[<?= $current_pergunta_js_index ?>][opcoes][<?= $opcao_idx ?>]"
                                            value="<?= htmlspecialchars($opcao['texto']) ?>" required>
                                        <label class="radio-label">
                                            <input type="radio" name="perguntas[<?= $current_pergunta_js_index ?>][correta]"
                                                value="<?= $opcao_idx ?>" <?= $opcao['is_correta'] ? 'checked' : '' ?> required>
                                            Correta
                                        </label>
                                        <button type="button" class="remove-opcao-btn">Remover Opção</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="add-opcao-btn">Adicionar Opção</button>
                            <button type="button" class="remove-pergunta-btn">Remover Pergunta</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" id="addPerguntaBtn">Adicionar Pergunta</button>
                <button type="submit" name="submit_gerenciar_questionario" class="submit-btn">Salvar
                    Questionário</button>
            </form>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let uniqueIdCounter = new Date().getTime();

            function generateUniqueId() {
                return uniqueIdCounter++;
            }

            function addOpcao(perguntaCard, currentOpcaoIndex = null, opcaoText = '', isCorrect = false, opcaoId = null) {
                const opcoesContainer = perguntaCard.querySelector('.opcoes-container');
                const uuid = generateUniqueId();

                const opcaoDiv = document.createElement('div');
                opcaoDiv.classList.add('opcao-item');
                opcaoDiv.innerHTML = `
                    ${opcaoId ? `<input type="hidden" name="perguntas[${perguntaCard.dataset.perguntaUuid}][opcoes_ids][${uuid}]" value="${opcaoId}">` : ''}
                    <label>Opção ${opcoesContainer.children.length + 1}:</label>
                    <input type="text" name="perguntas[${perguntaCard.dataset.perguntaUuid}][opcoes][${uuid}]" value="${opcaoText}" required>
                    <label class="radio-label">
                        <input type="radio" name="perguntas[${perguntaCard.dataset.perguntaUuid}][correta]" value="${uuid}" ${isCorrect ? 'checked' : ''} required>
                        Correta
                    </label>
                    <button type="button" class="remove-opcao-btn">Remover Opção</button>
                `;
                opcoesContainer.appendChild(opcaoDiv);
                updateOpcaoLabels(perguntaCard);
            }

            function addPergunta(perguntaId = null, perguntaText = '', opcoesData = []) {
                const perguntasContainer = document.getElementById('perguntas-container');
                const uuid = generateUniqueId();

                const perguntaCard = document.createElement('div');
                perguntaCard.classList.add('pergunta-card');
                perguntaCard.dataset.perguntaUuid = uuid;

                perguntaCard.innerHTML = `
                    ${perguntaId ? `<input type="hidden" name="perguntas[${uuid}][id]" value="${perguntaId}">` : ''}
                    <label>Pergunta:</label>
                    <textarea name="perguntas[${uuid}][texto]" rows="3" required>${perguntaText}</textarea>
                    <div class="opcoes-container"></div>
                    <button type="button" class="add-opcao-btn">Adicionar Opção</button>
                    <button type="button" class="remove-pergunta-btn">Remover Pergunta</button>
                `;
                perguntasContainer.appendChild(perguntaCard);

                if (opcoesData.length > 0) {
                    opcoesData.forEach((opcao, opIdx) => {
                        addOpcao(perguntaCard, opIdx, opcao.texto, opcao.is_correta, opcao.id);
                    });
                } else {
                    addOpcao(perguntaCard);
                }
            }

            function updateOpcaoLabels(perguntaCard) {
                perguntaCard.querySelectorAll('.opcao-item').forEach((opcaoItem, index) => {
                    const label = opcaoItem.querySelector('label');
                    if (label) {
                        label.textContent = `Opção ${index + 1}:`;
                    }
                });
            }

            document.getElementById('addPerguntaBtn').addEventListener('click', function () {
                addPergunta();
            });

            document.getElementById('perguntas-container').addEventListener('click', function (event) {
                if (event.target.classList.contains('add-opcao-btn')) {
                    const perguntaCard = event.target.closest('.pergunta-card');
                    addOpcao(perguntaCard);
                } else if (event.target.classList.contains('remove-opcao-btn')) {
                    const opcaoItem = event.target.closest('.opcao-item');
                    const perguntaCard = event.target.closest('.pergunta-card');
                    if (opcaoItem) {
                        opcaoItem.remove();
                        updateOpcaoLabels(perguntaCard);
                    }
                } else if (event.target.classList.contains('remove-pergunta-btn')) {
                    const perguntaCard = event.target.closest('.pergunta-card');
                    if (perguntaCard) {
                        perguntaCard.remove();
                    }
                }
            });

            const perguntasExistentesData = <?= json_encode(array_values($perguntas_existentes)) ?>;
            if (perguntasExistentesData.length > 0) {
                document.getElementById('perguntas-container').innerHTML = '';
                perguntasExistentesData.forEach(pergunta => {
                    addPergunta(pergunta.id, pergunta.texto, pergunta.opcoes);
                });
            } else {
                addPergunta();
            }
        });
    </script>

    <script src="script/AlertasGerais.js"></script>

</body>

</html>