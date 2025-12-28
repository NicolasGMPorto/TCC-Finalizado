<?php
include_once('Conexao.php'); // Conexão com o banco
session_start(); // Inicia sessão

// Restrição de acesso: somente usuários com nível 3 (professor) podem acessar
if ($_SESSION['nivel'] != 3) {
    $_SESSION['erro'] = "Acesso negado!";
    header("Location: MenuPrincipal.php");
    exit();
}

// Verifica se o ID do post foi passado via GET e se é um número válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID inválido!");
}
$id_post = (int) $_GET['id'];

// Busca a postagem pelo ID usando prepared statement para evitar SQL Injection
$query = "SELECT * FROM postagens WHERE id = ?";
$stmt = mysqli_prepare($conexao, $query);
mysqli_stmt_bind_param($stmt, "i", $id_post);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Se a postagem não existir, redireciona com erro
if (mysqli_num_rows($result) == 0) {
    $_SESSION['erro'] = "Postagem não encontrada!";
    header("Location: MenuPrincipal.php");
    exit();
}
$post = mysqli_fetch_assoc($result);

// Processa o formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Recebe e escapa os dados do título e descrição para evitar SQL Injection
    $titulo = mysqli_real_escape_string($conexao, $_POST['titulo']);
    $descricao = mysqli_real_escape_string($conexao, $_POST['descricao']);
    $imagem = $post['imagem']; // mantém imagem atual caso não seja trocada

    // Se foi enviada uma nova imagem, realiza validações
    if (!empty($_FILES['imagem']['name'])) {
        $upload_dir = 'uploads/postagens/';
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];

        // Verifica o MIME type do arquivo para segurança
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['imagem']['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed_types)) {
            $_SESSION['erro'] = "Apenas imagens JPEG, PNG e WEBP são permitidas!";
            header("Location: EditarPostagem.php?id=$id_post");
            exit();
        }

        // Define a extensão correta com base no MIME type
        $ext = '';
        switch ($mime) {
            case 'image/jpeg':
                $ext = 'jpg';
                break;
            case 'image/png':
                $ext = 'png';
                break;
            case 'image/webp':
                $ext = 'webp';
                break;
            default:
                $_SESSION['erro'] = "Tipo de arquivo inválido após validação MIME.";
                header("Location: EditarPostagem.php?id=$id_post");
                exit();
        }

        // Remove a imagem antiga do servidor, exceto se for uma imagem genérica placeholder
        if (file_exists($upload_dir . $post['imagem']) && $post['imagem'] != 'placeholder.jpg') {
            unlink($upload_dir . $post['imagem']);
        }

        // Gera um nome único para a nova imagem para evitar conflitos
        $new_filename = uniqid() . '.' . $ext;

        // Move a nova imagem para o diretório de uploads
        if (move_uploaded_file($_FILES['imagem']['tmp_name'], $upload_dir . $new_filename)) {
            $imagem = $new_filename; // atualiza variável para salvar no banco
        } else {
            $_SESSION['erro'] = "Erro ao carregar a nova imagem.";
            header("Location: EditarPostagem.php?id=$id_post");
            exit();
        }
    }

    // Atualiza a postagem no banco com os novos dados e a possível nova imagem
    $update_query = "UPDATE postagens SET titulo = ?, descricao = ?, imagem = ? WHERE id = ?";
    $stmt = mysqli_prepare($conexao, $update_query);
    mysqli_stmt_bind_param($stmt, "sssi", $titulo, $descricao, $imagem, $id_post);

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['sucesso'] = "Postagem atualizada com sucesso!";
    } else {
        $_SESSION['erro'] = "Erro ao atualizar: " . mysqli_error($conexao);
    }
    header("Location: MenuPrincipal.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Postagem</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles/Postagens.css">
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
    </header>
    <div class="container">
        <h2>Editar Postagem</h2>
        <form action="EditarPostagem.php?id=<?= $id_post ?>" method="POST" enctype="multipart/form-data">
            <label for="titulo">Título:</label>
            <center><input type="text" id="titulo" name="titulo" value="<?= htmlspecialchars($post['titulo']) ?>" required></center>

            <label for="descricao">Descrição:</label>
            <center><textarea id="descricao" name="descricao" rows="4"
                required><?= htmlspecialchars($post['descricao']) ?></textarea></center>

            <label for="imagem">Nova Imagem (opcional):</label>
            <center><input type="file" id="imagem" name="imagem" accept="image/jpeg, image/png, image/webp"></center>

            <p>Imagem atual:</p>
           <center><img src="uploads/postagens/<?= htmlspecialchars($post['imagem']) ?>" width="200"
                style="margin: 10px 0; border: 1px solid #ddd;"></center>

            <center><button type="submit">Salvar Alterações</button></center>
        </form>
    </div>
</body>

</html>