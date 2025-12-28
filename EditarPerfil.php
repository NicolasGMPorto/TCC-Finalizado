<?php
include_once('Conexao.php'); // Conecta ao banco
session_start(); // Inicia a sessão

// Redireciona caso o usuário não esteja logado
if (empty($_SESSION['usuario'])) {
    header("Location: Login.php");
    exit();
}

// Verifica a conexão com o banco de dados
if (!isset($conexao) || !$conexao || mysqli_ping($conexao) === false) {
    die("Erro: Banco de dados indisponível ou conexão inválida.");
}

$id_usuario = (int) $_SESSION['usuario'];
$mensagem_sucesso = "";
$mensagem_erro = "";

// Quando o formulário é enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Dados do formulário
    $nome_usuario = $_POST['nome_usuario'] ?? null;
    $email_usuario = $_POST['email_usuario'] ?? null;
    $rm_usuario = $_POST['rm_usuario'] ?? null;
    $etec_usuario = $_POST['etec_usuario'] ?? null;
    $telefone_usuario = $_POST['telefone_usuario'] ?? null;

    mysqli_begin_transaction($conexao); // Início da transação

    try {
        // Atualiza os dados do usuário
        $query_update = "UPDATE usuarios SET
                            nome_usuario = ?,
                            email_usuario = ?,
                            rm_usuario = ?,
                            etec_usuario = ?,
                            telefone_usuario = ?
                         WHERE id_usuario = ?";
        $stmt_update = mysqli_prepare($conexao, $query_update);
        if (!$stmt_update) {
            throw new Exception("Erro ao preparar a atualização: " . mysqli_error($conexao));
        }
        mysqli_stmt_bind_param(
            $stmt_update,
            "sssssi",
            $nome_usuario,
            $email_usuario,
            $rm_usuario,
            $etec_usuario,
            $telefone_usuario,
            $id_usuario
        );
        if (!mysqli_stmt_execute($stmt_update)) {
            throw new Exception("Erro ao executar a atualização: " . mysqli_error($conexao));
        }
        mysqli_stmt_close($stmt_update);

        // Se o usuário enviou uma nova foto
        if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
            $arquivo_temp = $_FILES['foto_perfil']['tmp_name'];
            $nome_arquivo_original = basename($_FILES['foto_perfil']['name']);
            $extensao = strtolower(pathinfo($nome_arquivo_original, PATHINFO_EXTENSION));
            $novo_nome_arquivo = uniqid() . '.' . $extensao;

            $diretorio_upload = 'uploads/perfis/';
            $caminho_completo_upload = $diretorio_upload . $novo_nome_arquivo;

            // Cria a pasta se não existir
            if (!is_dir($diretorio_upload)) {
                if (!mkdir($diretorio_upload, 0777, true)) {
                    throw new Exception("Não foi possível criar o diretório de upload: " . $diretorio_upload);
                }
            }

            // Valida extensão e MIME
            $tipos_permitidos = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($extensao, $tipos_permitidos)) {
                throw new Exception("Tipo de arquivo não permitido. Apenas JPG, JPEG, PNG, WEBP e GIF são aceitos.");
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $arquivo_temp);
            finfo_close($finfo);

            $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($mime_type, $allowed_mime_types)) {
                throw new Exception("Tipo de conteúdo de arquivo inválido. Apenas imagens JPG, PNG e GIF são permitidas.");
            }

            // Move o arquivo para o destino
            if (move_uploaded_file($arquivo_temp, $caminho_completo_upload)) {
                $query_foto = "UPDATE usuarios SET foto_perfil = ? WHERE id_usuario = ?";
                $stmt_foto = mysqli_prepare($conexao, $query_foto);
                if (!$stmt_foto) {
                    throw new Exception("Erro ao preparar a atualização da foto: " . mysqli_error($conexao));
                }
                mysqli_stmt_bind_param($stmt_foto, "si", $novo_nome_arquivo, $id_usuario);
                if (!mysqli_stmt_execute($stmt_foto)) {
                    throw new Exception("Erro ao atualizar a foto no banco: " . mysqli_error($conexao));
                }
                mysqli_stmt_close($stmt_foto);

                $_SESSION['foto_perfil'] = $novo_nome_arquivo; // Atualiza a sessão
            } else {
                throw new Exception("Falha ao mover o arquivo para o diretório de uploads. Verifique as permissões de pasta.");
            }

            // Trata erro específico de upload, se não for por ausência de arquivo
        } elseif (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] !== UPLOAD_ERR_NO_FILE) {
            $php_upload_errors = [
                UPLOAD_ERR_INI_SIZE => "O arquivo enviado excede a diretiva upload_max_filesize em php.ini.",
                UPLOAD_ERR_FORM_SIZE => "O arquivo enviado excede a diretiva MAX_FILE_SIZE que foi especificada no formulário HTML.",
                UPLOAD_ERR_PARTIAL => "O upload do arquivo foi feito parcialmente.",
                UPLOAD_ERR_NO_FILE => "Nenhum arquivo foi enviado.",
                UPLOAD_ERR_NO_TMP_DIR => "Faltando uma pasta temporária.",
                UPLOAD_ERR_CANT_WRITE => "Falha ao escrever o arquivo em disco.",
                UPLOAD_ERR_EXTENSION => "Uma extensão do PHP interrompeu o upload do arquivo."
            ];
            $error_message = $php_upload_errors[$_FILES['foto_perfil']['error']] ?? "Erro desconhecido no upload do arquivo.";
            throw new Exception("Erro no upload: " . $error_message);
        }

    } catch (Exception $e) {
        mysqli_rollback($conexao); // Reverte alterações em caso de erro
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao atualizar perfil: ' . $e->getMessage()];
        header("Location: Perfil.php");
        exit();
    }
}

// Busca os dados atuais do usuário para preencher o formulário
$query_select = "SELECT
                    nome_usuario,
                    email_usuario,
                    rm_usuario,
                    etec_usuario,
                    telefone_usuario,
                    foto_perfil
                 FROM usuarios
                 WHERE id_usuario = ?
                 LIMIT 1";

$stmt_select = mysqli_prepare($conexao, $query_select);
if (!$stmt_select) {
    die("Erro ao preparar a consulta de seleção: " . mysqli_error($conexao));
}
mysqli_stmt_bind_param($stmt_select, "i", $id_usuario);
mysqli_stmt_execute($stmt_select);
$result_select = mysqli_stmt_get_result($stmt_select);

if (mysqli_num_rows($result_select) === 0) {
    die("Usuário não encontrado.");
}
$usuario_atual = mysqli_fetch_assoc($result_select);
mysqli_stmt_close($stmt_select);

// Define caminho padrão para foto de perfil
$foto_perfil_atual_src = 'imagens/FotoPerfilGen.jpg';
if (!empty($usuario_atual['foto_perfil'])) {
    $temp_foto_path = 'uploads/perfis/' . htmlspecialchars($usuario_atual['foto_perfil']);
    if (file_exists($temp_foto_path)) {
        $foto_perfil_atual_src = $temp_foto_path;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Perfil</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles/EditarPerfil.css">
    <link rel="icon" href="imagens/Logo_Neez.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body class="perfil-page">
    <header>
        <div class="LogoDiv">
            <a href="MenuPrincipal.php" class="logo-link">
                <img class="LogoEtec" src="imagens/Etec_Logo.webp" alt="Logo da Etec">
            </a>
        </div>
        </div>
        <div class="nav-container">
            <div class="nav-links">
                <a href="MenuPrincipal.php">Início</a>
                <a href="Posts.php">Posts</a>
                <a href="#">Vídeos</a>
            </div>
        </div>
        <a href="Perfil.php">
            <img class="profile-icon" src="<?= $foto_perfil_atual_src ?>" alt="Perfil">
        </a>
    </header>

    <div class="container editar-perfil-container">
        <h1>Editar Meu Perfil</h1>

        <?php if (!empty($mensagem_sucesso)): ?>
            <div class="mensagem sucesso">
                <?= htmlspecialchars($mensagem_sucesso) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($mensagem_erro)): ?>
            <div class="mensagem erro">
                <?= htmlspecialchars($mensagem_erro) ?>
            </div>
        <?php endif; ?>

        <form action="EditarPerfil.php" method="POST" enctype="multipart/form-data">
            <div class="form-grupo foto-upload">
                <label for="foto_perfil">Foto de Perfil</label>
                <img src="<?= $foto_perfil_atual_src ?>" alt="Foto Atual" class="foto-preview" id="fotoPreview">
                <input type="file" name="foto_perfil" id="foto_perfil" accept="image/*" onchange="previewImage(event)">
                <small>Clique para alterar a foto (JPG, PNG, GIF ou WEBP)</small>
            </div>

            <div class="form-grupo">
                <label for="nome_usuario">Nome Completo</label>
                <input type="text" id="nome_usuario" name="nome_usuario"
                    value="<?= htmlspecialchars($usuario_atual['nome_usuario'] ?? '') ?>">
            </div>

            <div class="form-grupo">
                <label for="rm_usuario">RM</label>
                <input type="text" id="rm_usuario" name="rm_usuario"
                    value="<?= htmlspecialchars($usuario_atual['rm_usuario'] ?? '') ?>">
            </div>

            <div class="form-grupo">
                <label for="etec_usuario">ETEC</label>
                <input type="text" id="etec_usuario" name="etec_usuario"
                    value="<?= htmlspecialchars($usuario_atual['etec_usuario'] ?? '') ?>">
            </div>

            <div class="form-grupo">
                <label for="email_usuario">Email</label>
                <input type="email" id="email_usuario" name="email_usuario"
                    value="<?= htmlspecialchars($usuario_atual['email_usuario'] ?? '') ?>">
            </div>

            <div class="form-grupo">
                <label for="telefone_usuario">Telefone</label>
                <input type="text" id="telefone_usuario" name="telefone_usuario"
                    value="<?= htmlspecialchars($usuario_atual['telefone_usuario'] ?? '') ?>">
            </div>

            <button type="submit" class="btn-salvar-perfil">Salvar Alterações</button>
            <a href="Perfil.php" class="btn-cancelar">Cancelar</a>
        </form>
    </div>

    <script src="script/EditarPerfil.js"></script>

</body>

</html>