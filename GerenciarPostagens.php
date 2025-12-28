<?php
include_once('Conexao.php');
session_start();

define('ALUNO', 1);
define('ADMIN', 2);
define('PROFESSOR', 3);

include_once('FuncoesNotificacao.php');

// Verifica se o usuário está logado e se é administrador
if (!isset($_SESSION['usuario']) || $_SESSION['nivel'] != ADMIN) {
    $_SESSION['erro'] = "Acesso negado! Apenas administradores podem gerenciar postagens.";
    header("Location: MenuPrincipal.php");
    exit();
}

// Trata requisições POST com ações para aprovar ou excluir postagens
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    $post_id = (int) $_POST['post_id'];
    $acao = $_POST['acao'];

    // Busca o autor e título do post para notificações
    $autor_post_id = null;
    $query_autor = "SELECT id_aluno, titulo FROM posts WHERE id = ?";
    $stmt_autor = mysqli_prepare($conexao, $query_autor);
    if ($stmt_autor) {
        mysqli_stmt_bind_param($stmt_autor, "i", $post_id);
        mysqli_stmt_execute($stmt_autor);
        $result_autor = mysqli_stmt_get_result($stmt_autor);
        if ($row_autor = mysqli_fetch_assoc($result_autor)) {
            $autor_post_id = (int) $row_autor['id_aluno'];
            $titulo_post = htmlspecialchars($row_autor['titulo']);
        }
        mysqli_stmt_close($stmt_autor);
    }

    if ($acao == 'aprovar') {
        // Atualiza o status da postagem para 'aprovado'
        $stmt = mysqli_prepare($conexao, "UPDATE posts SET status_moderacao = 'aprovado' WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $post_id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['sucesso'] = "Postagem aprovada com sucesso!";
            // Envia notificação ao autor informando aprovação
            if ($autor_post_id) {
                $mensagem = "Sua postagem '" . $titulo_post . "' foi aprovada e está visível para a comunidade!";
                inserirNotificacao($conexao, $autor_post_id, $mensagem);
            }
        } else {
            $_SESSION['erro'] = "Erro ao aprovar postagem: " . mysqli_error($conexao);
        }
        mysqli_stmt_close($stmt);

    } elseif ($acao == 'excluir') {
        // Remove associações de tags vinculadas ao post
        $stmt_delete_tags = mysqli_prepare($conexao, "DELETE FROM post_tags WHERE post_id = ?");
        mysqli_stmt_bind_param($stmt_delete_tags, "i", $post_id);
        mysqli_stmt_execute($stmt_delete_tags);
        mysqli_stmt_close($stmt_delete_tags);

        // Remove comentários vinculados ao post
        $stmt_delete_comments = mysqli_prepare($conexao, "DELETE FROM comentarios WHERE post_id = ?");
        mysqli_stmt_bind_param($stmt_delete_comments, "i", $post_id);
        mysqli_stmt_execute($stmt_delete_comments);
        mysqli_stmt_close($stmt_delete_comments);

        // Exclui o post da tabela principal
        $stmt = mysqli_prepare($conexao, "DELETE FROM posts WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $post_id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['sucesso'] = "Postagem excluída com sucesso!";
            // Envia notificação ao autor informando remoção
            if ($autor_post_id) {
                $mensagem = "Sua postagem '" . $titulo_post . "' foi removida. Entre em contato com a administração para mais detalhes.";
                inserirNotificacao($conexao, $autor_post_id, $mensagem);
            }
        } else {
            $_SESSION['erro'] = "Erro ao excluir postagem: " . mysqli_error($conexao);
        }
        mysqli_stmt_close($stmt);
    }
    // Redireciona após a ação para evitar reenvio de formulário
    header("Location: GerenciarPostagens.php");
    exit();
}

// Busca posts pendentes de moderação para exibir na lista
$posts_pendentes = [];
$query_pendentes = "SELECT p.id, p.titulo, p.conteudo, p.data_criacao, u.nome_usuario AS nome_aluno, p.id_aluno,
                           GROUP_CONCAT(t.nome SEPARATOR ', ') AS tags_nomes_str
                    FROM posts p
                    JOIN usuarios u ON p.id_aluno = u.id_usuario
                    LEFT JOIN post_tags pt ON p.id = pt.post_id
                    LEFT JOIN tags t ON pt.tag_id = t.id
                    WHERE p.status_moderacao = 'pendente'
                    GROUP BY p.id, p.titulo, p.conteudo, p.data_criacao, u.nome_usuario, p.id_aluno 
                    ORDER BY p.data_criacao ASC";

$result_pendentes = mysqli_query($conexao, $query_pendentes);

if ($result_pendentes) {
    while ($row = mysqli_fetch_assoc($result_pendentes)) {
        $posts_pendentes[] = $row;
    }
} else {
    $_SESSION['erro'] = "Erro ao buscar postagens pendentes: " . mysqli_error($conexao);
}

// Define foto padrão caso usuário não tenha foto
$foto_perfil_src = 'imagens/FotoPerfilGen.jpg';

$id_usuario_logado = $_SESSION['usuario'] ?? null;

if ($id_usuario_logado) {
    $id_usuario_logado = (int) $id_usuario_logado;

    // Busca a foto de perfil do usuário logado
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
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Postagens - Administração</title>
    <link rel="stylesheet" href="styles/MenuPrincipal.css">
    <link rel="stylesheet" href="styles/GerenciarPostagens.css">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="icon" href="imagens/Logo_Neez.png">
    <style>
        .alert {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
        }

        .alert.sucesso {
            background-color: #28a745;
            color: white;
        }

        .alert.erro {
            background-color: #dc3545;
            color: white;
        }
    </style>
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
        <h2>Gerenciamento de Postagens Pendentes</h2>

        <?php
        if (isset($_SESSION['sucesso'])) {
            echo "<div class='alert sucesso'>" . $_SESSION['sucesso'] . "</div>";
            unset($_SESSION['sucesso']);
        }
        if (isset($_SESSION['erro'])) {
            echo "<div class='alert erro'>" . $_SESSION['erro'] . "</div>";
            unset($_SESSION['erro']);
        }
        ?>

        <?php if (!empty($posts_pendentes)): ?>
            <div class="post-list">
                <?php foreach ($posts_pendentes as $post): ?>
                    <div class="post-item">
                        <div class="post-header">
                            <h4><?= htmlspecialchars($post['titulo']) ?></h4>
                            <span class="post-meta">Por: <?= htmlspecialchars($post['nome_aluno']) ?> | Em:
                                <?= date('d/m/Y H:i', strtotime($post['data_criacao'])) ?></span>
                        </div>
                        <p class="post-content"><?= nl2br(htmlspecialchars($post['conteudo'])) ?></p>
                        <?php if (!empty($post['tags_nomes_str'])): ?>
                            <div class="post-tags">
                                Tags:
                                <?php
                                $display_tags = explode(', ', $post['tags_nomes_str']);
                                foreach ($display_tags as $tag):
                                    echo '<span class="tag-label">' . htmlspecialchars(trim($tag)) . '</span> ';
                                endforeach;
                                ?>
                            </div>
                        <?php endif; ?>
                        <div class="post-actions">
                            <form action="GerenciarPostagens.php" method="POST" style="display:inline;">
                                <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                <button type="submit" name="acao" value="aprovar" class="btn-aprovar">Aprovar</button>
                            </form>
                            <form action="GerenciarPostagens.php" method="POST" style="display:inline;">
                                <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                <button type="submit" name="acao" value="excluir" class="btn-excluir"
                                    onclick="return confirm('Tem certeza que deseja excluir esta postagem? Esta ação é irreversível e excluirá todos os comentários associados.');">Excluir</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="text-align: center; color: #000000ff; margin-top: 50px;">Nenhuma postagem pendente para moderação no
                momento.</p>
        <?php endif; ?>

    </main>

    <script src="script/AlertasGerais.js"></script>

</body>

</html>