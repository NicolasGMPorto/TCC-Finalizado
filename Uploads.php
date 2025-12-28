<?php
require_once 'Conexao.php';
require_once 'FuncoesNotificacao.php';
require_once 'FuncoesConquistas.php';

// Inicia sessão se não estiver ativa
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Define constantes para níveis de usuários
define('ALUNO', 1);
define('ADMIN', 2);
define('PROFESSOR', 3);

// Verifica se usuário está logado; caso contrário, redireciona para login com mensagem
if (!isset($_SESSION['usuario'])) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Você precisa estar logado para acessar esta página.'];
    header('Location: Login.php');
    exit();
}

// Obtém ID e nível do usuário logado
$id_usuario_logado = $_SESSION['usuario'];
$nivel_usuario_logado = $_SESSION['nivel'] ?? ALUNO;

// Define foto de perfil padrão
$foto_perfil_src = 'imagens/FotoPerfilGen.jpg';

// Busca a foto de perfil do usuário no banco e verifica se o arquivo existe
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

// Define tipos MIME permitidos para upload de arquivos
$allowed_types = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain',
    'application/zip',
    'application/x-rar-compressed',
    'text/html',
    'text/css',
    'application/javascript',
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp'
];
// Tamanho máximo de arquivo permitido: 5 MB
$max_file_size = 5 * 1024 * 1024;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['arquivo_upload'])) {
    $id_materia = $_POST['id_materia'] ?? null;
    $total_uploads_sucesso = 0;
    $conquistas_ganhas_sessao = $_SESSION['novas_conquistas'] ?? []; // Para acumular conquistas

    // Itera sobre cada arquivo enviado (mesmo que seja apenas um)
    foreach ($_FILES['arquivo_upload']['name'] as $key => $name) {
        $file = [
            'name' => $_FILES['arquivo_upload']['name'][$key],
            'type' => $_FILES['arquivo_upload']['type'][$key],
            'tmp_name' => $_FILES['arquivo_upload']['tmp_name'][$key],
            'error' => $_FILES['arquivo_upload']['error'][$key],
            'size' => $_FILES['arquivo_upload']['size'][$key]
        ];

        // Se não houver arquivo selecionado para esta entrada, pula
        if ($file['error'] == UPLOAD_ERR_NO_FILE) {
            continue;
        }

        // Verifica erros no upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            // Se houver um erro em um arquivo, registra a mensagem de erro específica para ele
            if (!isset($_SESSION['message'])) { // Se não houver mensagem de erro geral, cria uma
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Alguns arquivos não puderam ser enviados. Erro no arquivo ' . htmlspecialchars($file['name']) . ': ' . $file['error']];
            } else { // Se já houver, concatena
                $_SESSION['message']['text'] .= '<br>Erro no arquivo ' . htmlspecialchars($file['name']) . ': ' . $file['error'];
            }
            continue; // Pula para o próximo arquivo
        }

        // Verifica tamanho do arquivo
        if ($file['size'] > $max_file_size) {
            if (!isset($_SESSION['message'])) {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Alguns arquivos são muito grandes. Arquivo ' . htmlspecialchars($file['name']) . ' excede 5 MB.'];
            } else {
                $_SESSION['message']['text'] .= '<br>Arquivo ' . htmlspecialchars($file['name']) . ' é muito grande.';
            }
            continue;
        }

        // Verifica tipo MIME permitido
        if (!in_array($file['type'], $allowed_types)) {
            if (!isset($_SESSION['message'])) {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Alguns tipos de arquivo não são permitidos. Arquivo ' . htmlspecialchars($file['name']) . ' tem tipo não permitido.'];
            } else {
                $_SESSION['message']['text'] .= '<br>Arquivo ' . htmlspecialchars($file['name']) . ' tem tipo não permitido.';
            }
            continue;
        }

        // Define diretório de upload, criando se não existir
        $upload_dir = 'uploads/arquivos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Gera nome único para arquivo para evitar conflitos
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_file_name = uniqid('upload_') . '.' . $file_extension;
        $target_file = $upload_dir . $new_file_name;

        // Move arquivo enviado para pasta de uploads
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            try {
                // Insere dados do arquivo no banco
                $stmt = $conexao->prepare("INSERT INTO arquivos_usuario (id_usuario, id_materia, nome_original, nome_salvo, tipo_mime, tamanho_kb, caminho_arquivo) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if (!$stmt) {
                    throw new Exception("Erro ao preparar statement: " . $conexao->error);
                }

                $tamanho_kb = round($file['size'] / 1024);

                $stmt->bind_param("iisssis", $id_usuario_logado, $id_materia, $file['name'], $new_file_name, $file['type'], $tamanho_kb, $target_file);
                $stmt->execute();
                $stmt->close();

                $total_uploads_sucesso++;

            } catch (Exception $e) {
                error_log("Erro ao salvar dados do upload no BD para " . $file['name'] . ": " . $e->getMessage());
                unlink($target_file); // Tenta deletar o arquivo se a inserção no BD falhar
                if (!isset($_SESSION['message'])) {
                    $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao registrar o arquivo ' . htmlspecialchars($file['name']) . ' no banco de dados.'];
                } else {
                    $_SESSION['message']['text'] .= '<br>Erro ao registrar o arquivo ' . htmlspecialchars($file['name']) . ' no banco de dados.';
                }
            }
        } else {
            // Erro ao mover arquivo para pasta de upload
            if (!isset($_SESSION['message'])) {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao mover o arquivo ' . htmlspecialchars($file['name']) . ' para o diretório de uploads.'];
            } else {
                $_SESSION['message']['text'] .= '<br>Erro ao mover o arquivo ' . htmlspecialchars($file['name']) . '.';
            }
        }
    } // Fim do foreach

    // Após processar todos os arquivos, atualiza o contador de uploads e verifica conquistas
    if ($total_uploads_sucesso > 0) {
        $stmt_update_uploads = $conexao->prepare("UPDATE usuarios SET total_uploads_feitos = total_uploads_feitos + ? WHERE id_usuario = ?");
        if (!$stmt_update_uploads) {
            error_log("Erro ao preparar atualização do contador de uploads: " . $conexao->error);
        } else {
            $stmt_update_uploads->bind_param("ii", $total_uploads_sucesso, $id_usuario_logado);
            $stmt_update_uploads->execute();
            $stmt_update_uploads->close();
        }

        $novas_conquistas_desbloqueadas = verificar_e_desbloquear_conquistas($conexao, $id_usuario_logado, 'uploads_feitos');
        if (!empty($novas_conquistas_desbloqueadas)) {
            $_SESSION['novas_conquistas'] = array_unique(array_merge($conquistas_ganhas_sessao, $novas_conquistas_desbloqueadas));
        }

        // Mensagem final de sucesso se houve uploads bem-sucedidos
        if (!isset($_SESSION['message']) || $_SESSION['message']['type'] != 'error') {
            $_SESSION['message'] = ['type' => 'success', 'text' => $total_uploads_sucesso . ' arquivo(s) enviado(s) com sucesso!'];
        } else {
            $_SESSION['message']['text'] = $total_uploads_sucesso . ' arquivo(s) enviado(s) com sucesso!' . $_SESSION['message']['text'];
            $_SESSION['message']['type'] = 'warning'; // Muda para warning se houveram sucessos e erros
        }

    } elseif (!isset($_SESSION['message'])) { // Se não houve sucesso e nem erro individualmente marcado
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Nenhum arquivo foi enviado.'];
    }

    header('Location: Uploads.php');
    exit();
}

// Tratamento da exclusão de um upload enviado
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_upload_id'])) {
    $upload_id_to_delete = $_POST['delete_upload_id'];

    if (empty($upload_id_to_delete)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'ID do upload para exclusão não especificado.'];
        header('Location: Uploads.php');
        exit();
    }

    try {
        // Busca caminho e nome do arquivo para exclusão
        $stmt_select = $conexao->prepare("SELECT caminho_arquivo, nome_salvo FROM arquivos_usuario WHERE id = ? AND id_usuario = ?");
        if (!$stmt_select)
            throw new Exception("Erro ao preparar seleção: " . $conexao->error);
        $stmt_select->bind_param("ii", $upload_id_to_delete, $id_usuario_logado);
        $stmt_select->execute();
        $result_select = $stmt_select->get_result();

        if ($file_info = $result_select->fetch_assoc()) {
            $file_path = $file_info['caminho_arquivo'];
            $base_upload_dir = realpath('uploads/arquivos/');
            $full_file_path = realpath($file_path);

            // Verifica se o caminho é seguro e está dentro do diretório permitido
            if ($full_file_path && str_starts_with($full_file_path, $base_upload_dir)) {
                // Tenta excluir o arquivo físico
                if (file_exists($full_file_path) && unlink($full_file_path)) {
                    // Remove registro no banco
                    $stmt_delete = $conexao->prepare("DELETE FROM arquivos_usuario WHERE id = ? AND id_usuario = ?");
                    if (!$stmt_delete)
                        throw new Exception("Erro ao preparar deleção: " . $conexao->error);
                    $stmt_delete->bind_param("ii", $upload_id_to_delete, $id_usuario_logado);
                    $stmt_delete->execute();
                    $stmt_delete->close();

                    $_SESSION['message'] = ['type' => 'success', 'text' => 'Arquivo excluído com sucesso!'];
                } else {
                    // Caso arquivo físico não seja encontrado ou falhe exclusão, remove registro e avisa
                    $_SESSION['message'] = ['type' => 'warning', 'text' => 'Arquivo não encontrado no servidor ou erro ao excluir o arquivo físico, mas o registro será removido.'];
                    $stmt_delete = $conexao->prepare("DELETE FROM arquivos_usuario WHERE id = ? AND id_usuario = ?");
                    if (!$stmt_delete)
                        throw new Exception("Erro ao preparar deleção: " . $conexao->error);
                    $stmt_delete->bind_param("ii", $upload_id_to_delete, $id_usuario_logado);
                    $stmt_delete->execute();
                    $stmt_delete->close();
                }
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Caminho do arquivo inválido ou inseguro.'];
            }
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Arquivo não encontrado ou você não tem permissão para excluí-lo.'];
        }
        $stmt_select->close();

    } catch (Exception $e) {
        error_log("Erro ao excluir upload: " . $e->getMessage());
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao excluir o arquivo. Por favor, tente novamente.'];
    }
    header('Location: Uploads.php');
    exit();
}

// Busca as matérias disponíveis para filtro e seleção na página
$materias = [];
try {
    $stmt_materias = $conexao->prepare("SELECT id, nome FROM materias ORDER BY nome ASC");
    if (!$stmt_materias) {
        throw new Exception("Erro ao preparar consulta de matérias: " . $conexao->error);
    }
    $stmt_materias->execute();
    $result_materias = $stmt_materias->get_result();
    while ($row = $result_materias->fetch_assoc()) {
        $materias[] = $row;
    }
    $stmt_materias->close();
} catch (Exception $e) {
    error_log("Erro ao carregar matérias: " . $e->getMessage());
}

// Obtém filtros e ordenação via GET
$filter_materia_id = $_GET['materia'] ?? '';
$sort_by = $_GET['sort'] ?? 'data_upload_desc';

// Busca arquivos do usuário, aplicando filtro e ordenação
$user_files = [];
$sql_files = "SELECT au.id, au.nome_original, au.tipo_mime, au.tamanho_kb, au.caminho_arquivo, au.data_upload, m.nome AS nome_materia
              FROM arquivos_usuario au
              LEFT JOIN materias m ON au.id_materia = m.id
              WHERE au.id_usuario = ?";
$params = [$id_usuario_logado];
$types = "i";

if (!empty($filter_materia_id) && is_numeric($filter_materia_id)) {
    $sql_files .= " AND au.id_materia = ?";
    $params[] = $filter_materia_id;
    $types .= "i";
}

switch ($sort_by) {
    case 'nome_asc':
        $sql_files .= " ORDER BY au.nome_original ASC";
        break;
    case 'nome_desc':
        $sql_files .= " ORDER BY au.nome_original DESC";
        break;
    case 'data_upload_asc':
        $sql_files .= " ORDER BY au.data_upload ASC";
        break;
    case 'data_upload_desc':
    default:
        $sql_files .= " ORDER BY au.data_upload DESC";
        break;
}

// Executa consulta dos arquivos do usuário com filtros aplicados
try {
    $stmt_files = $conexao->prepare($sql_files);
    if (!$stmt_files) {
        throw new Exception("Erro ao preparar consulta de arquivos: " . $conexao->error);
    }
    $stmt_files->bind_param($types, ...$params);
    $stmt_files->execute();
    $result_files = $stmt_files->get_result();

    while ($row = $result_files->fetch_assoc()) {
        $user_files[] = $row;
    }
    $stmt_files->close();
} catch (Exception $e) {
    error_log("Erro ao carregar arquivos do usuário: " . $e->getMessage());
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Não foi possível carregar seus arquivos no momento.'];
}

// Função para retornar a classe do ícone FontAwesome baseado no tipo MIME do arquivo
function getFileIconClass($mime_type)
{
    if (str_starts_with($mime_type, 'image/')) {
        return 'fas fa-image';
    } elseif ($mime_type === 'application/pdf') {
        return 'fas fa-file-pdf';
    } elseif (str_contains($mime_type, 'word') || str_contains($mime_type, 'text')) {
        return 'fas fa-file-word';
    } elseif (str_contains($mime_type, 'excel') || str_contains($mime_type, 'sheet')) {
        return 'fas fa-file-excel';
    } elseif (str_contains($mime_type, 'powerpoint') || str_contains($mime_type, 'presentation')) {
        return 'fas fa-file-powerpoint';
    } elseif ($mime_type === 'application/zip' || $mime_type === 'application/x-rar-compressed' || $mime_type === 'application/vnd.rar') {
        return 'fas fa-file-archive';
    } elseif ($mime_type === 'text/html' || $mime_type === 'text/css' || $mime_type === 'application/javascript') {
        return 'fas fa-file-code';
    }
    return 'fas fa-file';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Uploads - Minha ETEC</title>
    <link rel="stylesheet" href="styles/MenuPrincipal.css">
    <link rel="stylesheet" href="styles/Uploads.css">
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
                <a href="Videos.php">Vídeos</a>
            </div>
        </div>
        <a href="Perfil.php">
            <img class="profile-icon" src="<?= $foto_perfil_src ?>" alt="Perfil">
        </a>
    </header>

    <main class="container">
        <center>
            <h2 class="title1">Meus Arquivos Digitais</h2>
        </center>

        <?php
        if (isset($_SESSION['message'])) {
            $msg_type = $_SESSION['message']['type'];
            $msg_text = $_SESSION['message']['text'];
            echo "<div id='alertMessage' class='alert {$msg_type}'>{$msg_text}</div>";
            unset($_SESSION['message']);
        }
        if (isset($_SESSION['novas_conquistas']) && !empty($_SESSION['novas_conquistas'])) {
            echo '<div class="alert success achievement-alert">'; // Nova classe para estilização de conquistas
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

        <section class="controls-section">
            <button id="toggleUploadFormBtn" class="control-btn upload-btn">
                <i class="fas fa-plus"></i> Novo Upload
            </button>
            <button id="toggleFilterSortBtn" class="control-btn filter-btn">
                <i class="fas fa-filter"></i> Filtrar/Ordenar
            </button>
        </section>

        <section id="uploadFormSection" class="upload-section hidden">
            <h3 class="title2">Enviar Novo Arquivo</h3>
            <form action="Uploads.php" method="POST" enctype="multipart/form-data">
                <label for="arquivo_upload">Selecione um ou mais arquivos:</label>
                <div class="file-input-wrapper">
                    <input type="file" id="arquivo_upload" name="arquivo_upload[]" multiple required>                    <button type="button" id="clearFilesBtn" class="btn-clear-files">Limpar Seleção</button>
                </div>

                <label for="id_materia">Selecione a Matéria:</label>
                <select id="id_materia" name="id_materia" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($materias as $materia): ?>
                        <option value="<?= htmlspecialchars($materia['id']) ?>">
                            <?= htmlspecialchars($materia['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-upload">Enviar Arquivo</button>
            </form>
        </section>

        <section id="filterSortSection" class="filter-sort-section hidden">
            <center>
                <h3>Filtrar e Ordenar Arquivos</h3>
                <form action="Uploads.php" method="GET">
                    <div class="filter-group">
                        <label for="filter_materia">Filtrar por Matéria:</label>
                        <select id="filter_materia" name="materia">
                            <option value="">Todas as Matérias</option>
                            <?php foreach ($materias as $materia): ?>
                                <option value="<?= htmlspecialchars($materia['id']) ?>"
                                    <?= ($filter_materia_id == $materia['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($materia['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="sort_by">Ordenar por:</label>
                        <select id="sort_by" name="sort">
                            <option value="data_upload_desc" <?= ($sort_by == 'data_upload_desc') ? 'selected' : '' ?>>Data
                                de Envio (Mais Recente)</option>
                            <option value="data_upload_asc" <?= ($sort_by == 'data_upload_asc') ? 'selected' : '' ?>>Data
                                de Envio (Mais Antigo)</option>
                            <option value="nome_asc" <?= ($sort_by == 'nome_asc') ? 'selected' : '' ?>>Nome do Arquivo
                                (A-Z)</option>
                            <option value="nome_desc" <?= ($sort_by == 'nome_desc') ? 'selected' : '' ?>>Nome do Arquivo
                                (Z-A)</option>
                        </select>
                    </div>
            </center> <br>
            <center>
                <button type="submit" class="btn-apply-filters">Aplicar Filtros</button>
            </center>
            </form>
        </section>

        <section class="my-uploads-section">
            <center>
                <h3 class="title3">Meus Arquivos Enviados</h3>
            </center>
            <?php if (!empty($user_files)): ?>
                <div class="file-grid">
                    <?php foreach ($user_files as $file): ?>
                        <div class="file-item">
                            <?php if (str_starts_with($file['tipo_mime'], 'image/')): ?>
                                <a href="<?= htmlspecialchars($file['caminho_arquivo']) ?>" target="_blank"
                                    download="<?= htmlspecialchars($file['nome_original']) ?>">
                                    <img src="<?= htmlspecialchars($file['caminho_arquivo']) ?>"
                                        alt="<?= htmlspecialchars($file['nome_original']) ?>" class="file-thumbnail">
                                </a>
                            <?php else: ?>
                                <a href="<?= htmlspecialchars($file['caminho_arquivo']) ?>" target="_blank"
                                    download="<?= htmlspecialchars($file['nome_original']) ?>" class="file-icon-link">
                                    <i class="<?= getFileIconClass($file['tipo_mime']) ?> file-icon"></i>
                                </a>
                            <?php endif; ?>
                            <p class="file-name" title="<?= htmlspecialchars($file['nome_original']) ?>">
                                <?= htmlspecialchars(strlen($file['nome_original']) > 25 ? substr($file['nome_original'], 0, 22) . '...' : $file['nome_original']) ?>
                            </p>
                            <?php if (!empty($file['nome_materia'])): ?>
                                <span class="file-category">Matéria: <?= htmlspecialchars($file['nome_materia']) ?></span>
                            <?php endif; ?>
                            <span class="file-size"><?= htmlspecialchars($file['tamanho_kb']) ?> KB</span>
                            <form action="Uploads.php" method="POST"
                                onsubmit="return confirm('Tem certeza que deseja excluir este arquivo?');">
                                <input type="hidden" name="delete_upload_id" value="<?= htmlspecialchars($file['id']) ?>">
                                <button type="submit" class="btn-delete-file"><i class="fas fa-trash-alt"></i> Excluir</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #AAA;">Você ainda não enviou nenhum arquivo.</p>
            <?php endif; ?>
        </section>
    </main>


    <script src="script/Uploads.js"></script>
    <script src="script/AlertasGerais.js"></script>

</body>

</html>