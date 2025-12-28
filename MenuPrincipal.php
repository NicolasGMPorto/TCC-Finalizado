<?php
include_once('Conexao.php');
session_start();

define('ALUNO', 1);
define('ADMIN', 2);
define('PROFESSOR', 3);

// Verifica se o usuário está logado; se não, redireciona para login
if (!isset($_SESSION['usuario'])) {
    header("Location: Login.php");
    exit();
}

// Verifica se a conexão com o banco está ativa
if (!isset($conexao) || !$conexao || mysqli_ping($conexao) === false) {
    die("Erro: Banco de dados indisponível ou conexão inválida.");
}

$foto_perfil_src = 'imagens/FotoPerfilGen.jpg';

// Obtém o ID do usuário logado
$id_usuario_logado = $_SESSION['usuario'] ?? null;

if ($id_usuario_logado) {
    $id_usuario_logado = (int) $id_usuario_logado;

    // Busca foto de perfil do usuário no banco
    $query_foto_db = "SELECT foto_perfil FROM usuarios WHERE id_usuario = ?";
    $stmt_foto_db = mysqli_prepare($conexao, $query_foto_db);
    if ($stmt_foto_db) {
        mysqli_stmt_bind_param($stmt_foto_db, "i", $id_usuario_logado);
        mysqli_stmt_execute($stmt_foto_db);
        $result_foto_db = mysqli_stmt_get_result($stmt_foto_db);
        if ($row_foto = mysqli_fetch_assoc($result_foto_db)) {
            if (!empty($row_foto['foto_perfil'])) {
                $temp_foto_path = 'uploads/perfis/' . htmlspecialchars($row_foto['foto_perfil']);
                // Verifica se arquivo da foto existe antes de usar
                if (file_exists($temp_foto_path)) {
                    $foto_perfil_src = $temp_foto_path;
                    $_SESSION['foto_perfil'] = $row_foto['foto_perfil'];
                }
            }
        }
        mysqli_stmt_close($stmt_foto_db);
    }
}

// Consulta para buscar todas as postagens com nome do criador
$query = "SELECT p.*, u.nome_usuario 
          FROM postagens p
          JOIN usuarios u ON p.criador_id = u.id_usuario
          ORDER BY p.data_criacao DESC";

$stmt = mysqli_prepare($conexao, $query);

if ($stmt) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    echo "Erro ao preparar a consulta de postagens: " . mysqli_error($conexao);
    $result = false;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Principal</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles/MenuPrincipal.css">
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

    <div class="container">
        <?php if (isset($_SESSION['nivel']) && $_SESSION['nivel'] == PROFESSOR): ?>
            <div class="create-post">
                <center>
                    <h2 class="titlepost">Criar Nova Notícia</h2>
                    <form action="CriarPostagem.php" method="POST" enctype="multipart/form-data">
                        <input type="text" name="titulo" placeholder="Título" required>
                        <input type="text" name="descricao" placeholder="Descrição" required>
                        <input type="file" name="imagem" accept="image/*" required>
                        <br>
                        <button type="submit">Criar Notícia</button>
                </center>
                </form>
            </div>
        <?php endif; ?>
        <center>
            <h1 class="not">Notícias</h1>
        </center>
        <?php
        if ($result && mysqli_num_rows($result) > 0):
            while ($row = mysqli_fetch_assoc($result)):
                ?>

                <div class="info-section">
                    <img src="uploads/postagens/<?= htmlspecialchars($row['imagem']) ?>"
                        alt="<?= htmlspecialchars($row['titulo']) ?>">
                    <div class="info-text">
                        <center>
                            <h2 class="titlepost2"><?= htmlspecialchars($row['titulo']) ?></h2>

                            <p class="criador-postagem">
                                Postado por: <?= htmlspecialchars($row['nome_usuario'] ?? 'Autor desconhecido') ?>
                            </p>

                            <p class="descricao2"><?= htmlspecialchars($row['descricao']) ?></p>

                            <?php if (isset($_SESSION['nivel']) && $_SESSION['nivel'] == PROFESSOR): ?>
                                <div class="post-actions">
                                    <a href="EditarPostagem.php?id=<?= $row['id'] ?>">Editar</a>
                                    <a class="btex" href="ExcluirPostagem.php?id=<?= $row['id'] ?>"
                                        onclick="return confirm('Tem certeza que deseja excluir?')">Excluir</a>
                                </div>
                            </center>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['nivel']) && $_SESSION['nivel'] == ADMIN): ?>
                                <div class="post-actions">
                                    <a href="EditarPostagem.php?id=<?= $row['id'] ?>">Editar</a>
                                    <a class="btex" href="ExcluirPostagem.php?id=<?= $row['id'] ?>"
                                        onclick="return confirm('Tem certeza que deseja excluir?')">Excluir</a>
                                </div>
                            </center>
                        <?php endif; ?>

                    </div>
                </div>
            <?php
            endwhile;
        else:
            echo "<p></p>";
        endif;
        ?>
    </div>
</body>
</html>