<?php
require_once 'Conexao.php';
require_once 'FuncoesConquistas.php'; // Mantenha se for usar
require_once 'FuncoesNotificacao.php'; // Mantenha se for usar

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Defina as constantes se elas ainda não estiverem definidas globalmente ou em um arquivo comum
if (!defined('ALUNO'))
    define('ALUNO', 1);
if (!defined('ADMIN'))
    define('ADMIN', 2);
if (!defined('PROFESSOR'))
    define('PROFESSOR', 3);

$foto_perfil_src = 'imagens/FotoPerfilGen.jpg'; // Caminho ajustado para a estrutura atual
$id_usuario_logado = $_SESSION['usuario'] ?? null;
$nivel_usuario_logado = $_SESSION['nivel'] ?? null;
$notificacoes_nao_lidas_count = 0;

if ($id_usuario_logado) {
    $id_usuario_logado_int = (int) $id_usuario_logado;

    $query_foto_db = "SELECT foto_perfil FROM usuarios WHERE id_usuario = ?";
    $stmt_foto_db = mysqli_prepare($conexao, $query_foto_db);
    if ($stmt_foto_db) {
        mysqli_stmt_bind_param($stmt_foto_db, "i", $id_usuario_logado_int);
        mysqli_stmt_execute($stmt_foto_db);
        $result_foto_db = mysqli_stmt_get_result($stmt_foto_db);
        if ($row_foto = mysqli_fetch_assoc($result_foto_db)) {
            if (!empty($row_foto['foto_perfil'])) {
                $temp_foto_path = 'uploads/perfis/' . htmlspecialchars($row_foto['foto_perfil']);
                if (file_exists($temp_foto_path)) {
                    $foto_perfil_src = $temp_foto_path;
                    $_SESSION['foto_perfil'] = $row_foto['foto_perfil']; // Mantido da versão antiga
                }
            }
        }
        mysqli_stmt_close($stmt_foto_db);
    }

    $query_notificacoes_count = "SELECT COUNT(*) AS total FROM notificacoes WHERE id_usuario_destino = ? AND lida = 0";
    $stmt_notificacoes_count = mysqli_prepare($conexao, $query_notificacoes_count);
    if ($stmt_notificacoes_count) {
        mysqli_stmt_bind_param($stmt_notificacoes_count, "i", $id_usuario_logado_int);
        mysqli_stmt_execute($stmt_notificacoes_count);
        $result_notificacoes_count = mysqli_stmt_get_result($stmt_notificacoes_count);
        if ($row_count = mysqli_fetch_assoc($result_notificacoes_count)) {
            $notificacoes_nao_lidas_count = (int) $row_count['total'];
        }
        mysqli_stmt_close($stmt_notificacoes_count);
    }
}
// --- Fim da lógica de usuário e notificações ---


// Função para extrair ID do YouTube (mantida e aprimorada)
function getYouTubeVideoId($url)
{
    // Padrão para URLs padrão do YouTube (watch?v=, embed/, v/)
    $pattern = '/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com|youtu\.be)\/(?:watch\?v=|embed\/|v\/|)([a-zA-Z0-9_-]{11})(?:\S+)?/';
    if (preg_match($pattern, $url, $matches)) {
        return $matches[1];
    }
    // Padrão para o domínio específico 'http://googleusercontent.com/youtube.com/'
    $google_user_content_pattern = '/http:\/\/googleusercontent\.com\/youtube\.com\/([a-zA-Z0-9_-]{11})/';
    if (preg_match($google_user_content_pattern, $url, $matches)) {
        return $matches[1];
    }
    return false;
}


// --- Carregar Matérias Disponíveis (para o filtro e o formulário de upload) ---
$availableMaterias = [];
if ($conexao->connect_error) {
    error_log("Erro de conexão MySQLi: " . $conexao->connect_error);
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao conectar com o banco de dados para carregar matérias.'];
} else {
    try {
        $result_materias = $conexao->query("SELECT id, nome FROM materias ORDER BY nome ASC");
        if ($result_materias) {
            while ($row = $result_materias->fetch_assoc()) {
                $availableMaterias[] = $row;
            }
            $result_materias->free();
        }
    } catch (Exception $e) {
        error_log("Erro ao carregar matérias (MySQLi): " . $e->getMessage());
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao carregar matérias disponíveis.'];
    }
}

// --- Lógica de Criação de Vídeo (Envio de Formulário) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_video'])) {
    // Verifique se o usuário logado é um professor ou administrador
    if (!isset($nivel_usuario_logado) || ($nivel_usuario_logado != PROFESSOR && $nivel_usuario_logado != ADMIN)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Você não tem permissão para adicionar vídeos.'];
        header('Location: Videos.php');
        exit();
    }

    $video_title = trim($_POST['video_title'] ?? '');
    $video_url = trim($_POST['video_url'] ?? '');
    $video_description = trim($_POST['video_description'] ?? '');
    $selected_materia_id = (int) ($_POST['materia'] ?? 0); // Captura o ID da matéria

    if (empty($video_title) || empty($video_url) || empty($video_description) || $selected_materia_id <= 0) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Todos os campos e a seleção de matéria são obrigatórios.'];
        header('Location: Videos.php');
        exit();
    }

    // Validação da URL do YouTube
    $youtube_id = getYouTubeVideoId($video_url);
    if (!$youtube_id) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'URL do vídeo inválida ou não é um vídeo do YouTube reconhecido.'];
        header('Location: Videos.php');
        exit();
    }

    $conexao->begin_transaction();

    try {
        // Inserir o vídeo na tabela 'videos'
        $stmt = $conexao->prepare("INSERT INTO videos (titulo, url_video, descricao, id_professor, id_materia, status_moderacao, data_publicacao) VALUES (?, ?, ?, ?, ?, 'aprovado', NOW())");
        if (!$stmt) {
            throw new Exception("Erro ao preparar statement de vídeo: " . $conexao->error);
        }

        $stmt->bind_param("sssii", $video_title, $video_url, $video_description, $id_usuario_logado_int, $selected_materia_id);
        $stmt->execute();
        $video_id = $conexao->insert_id; // Pega o ID do vídeo recém-inserido
        $stmt->close();

        $conexao->commit();
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Vídeo enviado com sucesso!'];

        // Lógica de conquistas e notificações para vídeos (se você tiver)
        // Ex: verificar_e_desbloquear_conquistas($conexao, $id_usuario_logado, 'videos_enviados');
        // inserirNotificacao($conexao, $id_usuario_logado, 'Você enviou um novo vídeo!');

        header('Location: Videos.php');
        exit();

    } catch (Exception $e) {
        $conexao->rollback();
        error_log("Erro ao enviar vídeo (MySQLi): " . $e->getMessage());
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao enviar vídeo. Por favor, tente novamente mais tarde.'];
        header('Location: Videos.php');
        exit();
    }
}

// --- Lógica de Exclusão de Vídeo (Portado da versão antiga) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_video'])) {
    // Apenas professores e administradores podem excluir vídeos
    if (!isset($nivel_usuario_logado) || ($nivel_usuario_logado != PROFESSOR && $nivel_usuario_logado != ADMIN)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Você não tem permissão para excluir vídeos.'];
        header('Location: Videos.php');
        exit();
    }

    $video_id_to_delete = $_POST['video_id_to_delete'] ?? null;

    if (empty($video_id_to_delete)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'ID do vídeo para exclusão não especificado.'];
        header('Location: Videos.php');
        exit();
    }

    $conexao->begin_transaction();
    try {
        $questionario_id = null;
        // Obter o ID do questionário associado ao vídeo, se existir
        $stmt_get_quiz_id = $conexao->prepare("SELECT id FROM questionarios WHERE id_video = ?");
        if ($stmt_get_quiz_id) {
            $stmt_get_quiz_id->bind_param("i", $video_id_to_delete);
            $stmt_get_quiz_id->execute();
            $result_quiz_id = $stmt_get_quiz_id->get_result();
            if ($row_quiz = $result_quiz_id->fetch_assoc()) {
                $questionario_id = $row_quiz['id'];
            }
            $stmt_get_quiz_id->close();
        } else {
            throw new Exception("Erro ao preparar statement para buscar ID do questionário: " . $conexao->error);
        }

        // Se houver um questionário, excluir dados relacionados em cascata
        if ($questionario_id) {
            // Excluir desempenho do questionário
            $stmt_delete_resultados = $conexao->prepare("DELETE FROM desempenho_questionario WHERE id_questionario = ?");
            if ($stmt_delete_resultados) {
                $stmt_delete_resultados->bind_param("i", $questionario_id);
                $stmt_delete_resultados->execute();
                $stmt_delete_resultados->close();
            } else {
                throw new Exception("Erro ao preparar statement para excluir desempenho: " . $conexao->error);
            }

            // Excluir respostas de alunos para as perguntas do questionário
            $stmt_delete_respostas_aluno = $conexao->prepare("DELETE ra FROM respostas_aluno ra JOIN perguntas_questionario pq ON ra.id_pergunta = pq.id WHERE pq.id_questionario = ?");
            if ($stmt_delete_respostas_aluno) {
                $stmt_delete_respostas_aluno->bind_param("i", $questionario_id);
                $stmt_delete_respostas_aluno->execute();
                $stmt_delete_respostas_aluno->close();
            } else {
                throw new Exception("Erro ao preparar statement para excluir respostas de aluno: " . $conexao->error);
            }

            // Excluir opções de resposta do questionário
            $stmt_delete_opcoes = $conexao->prepare("DELETE op FROM opcoes_resposta op JOIN perguntas_questionario pq ON op.id_pergunta = pq.id WHERE pq.id_questionario = ?");
            if ($stmt_delete_opcoes) {
                $stmt_delete_opcoes->bind_param("i", $questionario_id);
                $stmt_delete_opcoes->execute();
                $stmt_delete_opcoes->close();
            } else {
                throw new Exception("Erro ao preparar statement para excluir opções: " . $conexao->error);
            }

            // Excluir perguntas do questionário
            $stmt_delete_perguntas = $conexao->prepare("DELETE FROM perguntas_questionario WHERE id_questionario = ?");
            if ($stmt_delete_perguntas) {
                $stmt_delete_perguntas->bind_param("i", $questionario_id);
                $stmt_delete_perguntas->execute();
                $stmt_delete_perguntas->close();
            } else {
                throw new Exception("Erro ao preparar statement para excluir perguntas: " . $conexao->error);
            }

            // Excluir o questionário em si
            $stmt_delete_questionario = $conexao->prepare("DELETE FROM questionarios WHERE id = ?");
            if ($stmt_delete_questionario) {
                $stmt_delete_questionario->bind_param("i", $questionario_id);
                $stmt_delete_questionario->execute();
                $stmt_delete_questionario->close();
            } else {
                throw new Exception("Erro ao preparar statement para excluir questionário: " . $conexao->error);
            }
        }

        // Excluir o vídeo principal
        $stmt_delete_video = $conexao->prepare("DELETE FROM videos WHERE id = ?");
        if ($stmt_delete_video) {
            $stmt_delete_video->bind_param("i", $video_id_to_delete);
            $stmt_delete_video->execute();
            $stmt_delete_video->close();
        } else {
            throw new Exception("Erro ao preparar statement para excluir vídeo: " . $conexao->error);
        }

        $conexao->commit();
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Vídeo e seu conteúdo associado (questionário, perguntas, respostas) excluídos com sucesso!'];
        header('Location: Videos.php');
        exit();

    } catch (Exception $e) {
        $conexao->rollback();
        error_log("Erro ao excluir vídeo: " . $e->getMessage());
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao excluir vídeo. Por favor, tente novamente.'];
        header('Location: Videos.php');
        exit();
    }
}

// --- Lógica de Exibição dos Vídeos ---
$videos = [];
$filter_materia_id = (int) ($_GET['materia'] ?? 0); // Captura o ID da matéria para filtrar

if ($conexao->connect_error) {
    $videos = [];
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao conectar com o banco de dados para carregar vídeos.'];
} else {
    try {
        $query_videos = "
            SELECT 
                v.id, v.titulo, v.url_video, v.descricao, v.data_publicacao,
                v.id_professor, 
                u.nome_usuario AS nome_professor,
                m.nome AS nome_materia,
                q.id AS questionario_id_existente /* <-- Usando o ID do questionário diretamente */
            FROM 
                videos v
            JOIN 
                usuarios u ON v.id_professor = u.id_usuario
            JOIN 
                materias m ON v.id_materia = m.id
            LEFT JOIN 
                questionarios q ON v.id = q.id_video /* <-- LEFT JOIN para verificar existência de questionário */
            WHERE 
                v.status_moderacao = 'aprovado'
        ";

        // Adiciona a condição de filtro por matéria se um ID for selecionado
        if ($filter_materia_id > 0) {
            $query_videos .= " AND v.id_materia = ? ";
        }

        $query_videos .= "
            ORDER BY 
                v.data_publicacao DESC
        ";

        $stmt_fetch_videos = mysqli_prepare($conexao, $query_videos);

        if ($stmt_fetch_videos) {
            if ($filter_materia_id > 0) {
                mysqli_stmt_bind_param($stmt_fetch_videos, "i", $filter_materia_id);
            }

            mysqli_stmt_execute($stmt_fetch_videos);
            $result_fetch_videos = mysqli_stmt_get_result($stmt_fetch_videos);

            if ($result_fetch_videos) {
                while ($row = mysqli_fetch_assoc($result_fetch_videos)) {
                    $videos[] = $row;
                }
                $result_fetch_videos->free();
            }
            mysqli_stmt_close($stmt_fetch_videos);
        } else {
            throw new Exception("Erro ao preparar a consulta de vídeos: " . mysqli_error($conexao));
        }

    } catch (Exception $e) {
        error_log("Erro ao carregar vídeos (MySQLi): " . $e->getMessage());
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Não foi possível carregar os vídeos no momento.'];
        $videos = [];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vídeos Educacionais</title>
    <link rel="stylesheet" href="styles/MenuPrincipal.css">
    <link rel="stylesheet" href="styles/Videos.css">
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
                <a href="MenuPrincipal.php">Notícias</a>
                <a href="Posts.php">Posts</a>
                <a href="Uploads.php">Uploads</a>
            </div>
        </div>
        <div class="user-actions">
            <a href="Perfil.php">
                <img class="profile-icon" src="<?= $foto_perfil_src ?>" alt="Perfil">
            </a>
        </div>
    </header>

    <main class="container">
        <h2>Vídeos Educacionais</h2>

        <div class="add-video-toggle">
            <?php if (isset($nivel_usuario_logado) && ($nivel_usuario_logado == PROFESSOR || $nivel_usuario_logado == ADMIN)): ?>
                <button id="addVideoButton" class="add-video-button" title="Adicionar Novo Vídeo">
                    <i class="fas fa-plus"></i> Adicionar vídeo
                </button>
            <?php endif; ?>
        </div>

        <?php
        if (isset($_SESSION['message'])) {
            $msg_type = $_SESSION['message']['type'];
            $msg_text = $_SESSION['message']['text'];
            echo "<div id='alertMessage' class='alert {$msg_type}'>{$msg_text}</div>";
            unset($_SESSION['message']);
        }
        ?>

        <?php if (isset($nivel_usuario_logado) && ($nivel_usuario_logado == PROFESSOR || $nivel_usuario_logado == ADMIN)): ?>
            <section id="addVideoPanel" class="add-video-section hidden">
                <h3>Envie Seu Vídeo</h3>
                <form action="Videos.php" method="POST">
                    <label for="video_title">Título do Vídeo:</label>
                    <input type="text" id="video_title" name="video_title" placeholder="Ex: Introdução à Álgebra Linear"
                        required>

                    <label for="video_url">URL do Vídeo (YouTube):</label>
                    <input type="text" id="video_url" name="video_url"
                        placeholder="Ex: http://googleusercontent.com/youtube.com/ID_DO_VIDEO" required>

                    <label for="video_description">Descrição do Vídeo:</label>
                    <input type="text" id="video_description" name="video_description"
                        placeholder="Descreva o conteúdo do vídeo..." required>

                    <label for="materia">Matéria:</label>
                    <select id="materia" name="materia" required>
                        <option value="">Selecione uma matéria</option>
                        <?php if (!empty($availableMaterias)): ?>
                            <?php foreach ($availableMaterias as $materia): ?>
                                <option value="<?= htmlspecialchars($materia['id']) ?>">
                                    <?= htmlspecialchars($materia['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="">Nenhuma matéria disponível</option>
                        <?php endif; ?>
                    </select>
                    <small>Selecione a matéria principal do seu vídeo.</small>

                    <div class="form-actions">
                        <center><button type="submit" name="submit_video">Enviar Vídeo</button></center>
                        <center><button type="button" id="cancelAddVideo" class="btn-cancel">Cancelar</button></center>
                    </div>
                </form>
            </section>
        <?php else: ?>
            <div id='alertMessage' class="alert info">Apenas professores e administradores podem enviar vídeos.</div>
        <?php endif; ?>

        <hr>

        <section class="existing-videos-section">
            <h3>Vídeos Disponíveis</h3>

            <div class="materia-filter-container">
                <form action="Videos.php" method="GET" class="materia-filter-form">
                    <label for="filter_materia">Filtrar por Matéria:</label>
                    <select name="materia" id="filter_materia" onchange="this.form.submit()">
                        <option value="0">Todas as Matérias</option>
                        <?php
                        $selected_materia_for_filter = $_GET['materia'] ?? '0';
                        foreach ($availableMaterias as $materia): ?>
                            <option value="<?= htmlspecialchars($materia['id']) ?>"
                                <?= ($selected_materia_for_filter == $materia['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($materia['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if (!empty($videos)): ?>
                <div class="video-grid">
                    <?php foreach ($videos as $video): ?>
                        <?php
                        $youtube_id = getYouTubeVideoId($video['url_video']);
                        $embed_url = '';
                        if ($youtube_id) {
                            // Se a URL original for do tipo http://googleusercontent.com/youtube.com/ID
                            if (str_contains($video['url_video'], 'http://googleusercontent.com/youtube.com/')) {
                                $embed_url = 'http://googleusercontent.com/youtube.com/' . htmlspecialchars($youtube_id);
                            } else {
                                // Para URLs padrão do YouTube, use o formato embed
                                $embed_url = 'https://www.youtube.com/embed/' . htmlspecialchars($youtube_id) . '?rel=0';
                            }
                        }
                        $questionario_existe = !empty($video['questionario_id_existente']);
                        ?>
                        <div class="video-item">
                            <?php if ($youtube_id): ?>
                                <iframe src="<?= $embed_url ?>" frameborder="0"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen>
                                </iframe>
                            <?php else: ?>
                                <div class="video-placeholder">
                                    Não foi possível carregar o vídeo. URL inválida.
                                </div>
                            <?php endif; ?>
                            <div class="video-info">
                                <h4 class="video-title"><?= htmlspecialchars($video['titulo']) ?></h4>
                                <p class="video-description"><?= nl2br(htmlspecialchars($video['descricao'])) ?></p>
                                <div class="video-meta">
                                    <span class="video-tag">Matéria: <?= htmlspecialchars($video['nome_materia']) ?></span>
                                    <span class="video-author">Por: <?= htmlspecialchars($video['nome_professor']) ?></span>
                                    <span class="video-date">Publicado em:
                                        <?= date('d/m/Y H:i', strtotime($video['data_publicacao'])) ?></span>
                                </div>

                                <div class="video-actions">
                                    <a href="VideoDetalhes.php?id=<?= htmlspecialchars($video['id']) ?>" class="btn-primary">Ver
                                        Detalhes</a>

                                    <?php if (isset($nivel_usuario_logado)): ?>
                                        <?php if ($questionario_existe): ?>
                                            <?php if ($nivel_usuario_logado == ALUNO): ?>
                                                <a href="ResponderQuestionario.php?video_id=<?= htmlspecialchars($video['id']) ?>"
                                                    class="btn-questionario">Responder Questionário</a>
                                            <?php elseif ($nivel_usuario_logado == PROFESSOR || $nivel_usuario_logado == ADMIN): ?>
                                                <a href="GerenciarQuestionarios.php?video_id=<?= htmlspecialchars($video['id']) ?>"
                                                    class="btn-questionario btn-secondary">Gerenciar Questionário</a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if ($nivel_usuario_logado == PROFESSOR || $nivel_usuario_logado == ADMIN): ?>
                                                <a href="GerenciarQuestionarios.php?video_id=<?= htmlspecialchars($video['id']) ?>"
                                                    class="btn-questionario btn-create">Criar Questionário</a>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php
                                        // Apenas administradores ou o próprio professor que enviou o vídeo podem excluí-lo
                                        if ($nivel_usuario_logado == ADMIN || ($nivel_usuario_logado == PROFESSOR && $id_usuario_logado_int == $video['id_professor'])): ?>
                                            <form action="Videos.php" method="POST" style="display:inline;"
                                                onsubmit="return confirm('Tem certeza que deseja excluir este vídeo e todo o seu conteúdo associado (questionário, perguntas, respostas de alunos)? Esta ação é irreversível!');">
                                                <input type="hidden" name="delete_video" value="1">
                                                <input type="hidden" name="video_id_to_delete"
                                                    value="<?= htmlspecialchars($video['id']) ?>">
                                                <button type="submit" class="btn-delete-video">Excluir Vídeo</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #AAA;">
                    <?php if ($filter_materia_id > 0): ?>
                        Nenhum vídeo aprovado para a matéria selecionada.
                    <?php else: ?>
                        Nenhum vídeo aprovado para exibir ainda.
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </section>

    </main>


    <script src="script/Videos.js"></script>
    <script src="script/AlertasGerais.js"></script>

</body>

</html>
<?php
if (isset($conexao) && $conexao) {
    mysqli_close($conexao);
}
?>