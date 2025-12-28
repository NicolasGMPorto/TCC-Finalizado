<?php
include_once('Conexao.php');
session_start();

define('ALUNO', 1);
define('ADMIN', 2);
define('PROFESSOR', 3);

include_once('FuncoesConquistas.php');
include_once('FuncoesNotificacao.php');

$foto_perfil_src = '../imagens/FotoPerfilGen.jpg';
$id_usuario_logado = $_SESSION['usuario'] ?? null;
$nivel_usuario_logado = $_SESSION['nivel'] ?? null;
$notificacoes_nao_lidas_count = 0;

if ($id_usuario_logado) {
    $id_usuario_logado_int = (int)$id_usuario_logado;

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
            $notificacoes_nao_lidas_count = (int)$row_count['total'];
        }
        mysqli_stmt_close($stmt_notificacoes_count);
    }
}

if (isset($_SESSION['message'])) {
    echo "<div style='background-color: " . ($_SESSION['message']['type'] == 'success' ? '#d4edda' : '#f8d7da') . "; color: " . ($_SESSION['message']['type'] == 'success' ? '#155724' : '#721c24') . "; padding: 10px; margin-bottom: 10px; border-radius: 5px; text-align: center; font-weight: bold;'>";
    echo $_SESSION['message']['text'];
    echo "</div>";
    unset($_SESSION['message']);
}

$post_id = $_GET['id'] ?? 0;
$post_id = (int)$post_id;

$post = null;
$comentarios = [];

if ($post_id > 0) {
    $session_key = 'viewed_post_' . $post_id;
    if (!isset($_SESSION[$session_key]) || $_SESSION[$session_key] !== true) {
        $stmt_update_views = mysqli_prepare($conexao, "UPDATE posts SET visualizacoes = visualizacoes + 1 WHERE id = ?");
        if ($stmt_update_views) {
            mysqli_stmt_bind_param($stmt_update_views, "i", $post_id);
            mysqli_stmt_execute($stmt_update_views);
            mysqli_stmt_close($stmt_update_views);
            $_SESSION[$session_key] = true;
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_comment_id'])) {
    if (!isset($_SESSION['usuario'])) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Você precisa estar logado para excluir comentários.'];
        header("Location: ComentariosPost.php?id=" . $post_id);
        exit();
    }

    $comment_to_delete_id = (int)$_POST['delete_comment_id'];
    $usuario_logado_id = (int)$_SESSION['usuario'];
    $nivel_usuario_logado = $_SESSION['nivel'] ?? 0;

    $query_check_comment = "SELECT usuario_id FROM comentarios WHERE id = ?";
    $stmt_check_comment = mysqli_prepare($conexao, $query_check_comment);
    if ($stmt_check_comment) {
        mysqli_stmt_bind_param($stmt_check_comment, "i", $comment_to_delete_id);
        mysqli_stmt_execute($stmt_check_comment);
        $result_check_comment = mysqli_stmt_get_result($stmt_check_comment);
        $comment_owner_row = mysqli_fetch_assoc($result_check_comment);
        mysqli_stmt_close($stmt_check_comment);

        if ($comment_owner_row) {
            $comment_owner_id = (int)$comment_owner_row['usuario_id'];

            if ($usuario_logado_id === $comment_owner_id || $nivel_usuario_logado == ADMIN) {
                $stmt_delete_comment = mysqli_prepare($conexao, "DELETE FROM comentarios WHERE id = ?");
                if ($stmt_delete_comment) {
                    mysqli_stmt_bind_param($stmt_delete_comment, "i", $comment_to_delete_id);
                    if (mysqli_stmt_execute($stmt_delete_comment)) {
                        $_SESSION['message'] = ['type' => 'success', 'text' => 'Comentário excluído com sucesso!'];
                    } else {
                        $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao excluir comentário: ' . mysqli_error($conexao)];
                    }
                    mysqli_stmt_close($stmt_delete_comment);
                } else {
                    $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao preparar statement de exclusão: ' . mysqli_error($conexao)];
                }
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Você não tem permissão para excluir este comentário.'];
            }
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Comentário não encontrado.'];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao verificar permissões do comentário: ' . mysqli_error($conexao)];
    }
    header("Location: ComentariosPost.php?id=" . $post_id);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_comment_id']) && isset($_POST['edited_content'])) {
    if (!isset($_SESSION['usuario'])) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Você precisa estar logado para editar comentários.'];
        header("Location: ComentariosPost.php?id=" . $post_id);
        exit();
    }

    $comment_to_edit_id = (int)$_POST['edit_comment_id'];
    $edited_content = trim($_POST['edited_content'] ?? '');
    $usuario_logado_id = (int)$_SESSION['usuario'];

    if (empty($edited_content)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'O conteúdo do comentário não pode estar vazio.'];
    } else {
        $query_check_owner = "SELECT usuario_id FROM comentarios WHERE id = ?";
        $stmt_check_owner = mysqli_prepare($conexao, $query_check_owner);
        if ($stmt_check_owner) {
            mysqli_stmt_bind_param($stmt_check_owner, "i", $comment_to_edit_id);
            mysqli_stmt_execute($stmt_check_owner);
            $result_check_owner = mysqli_stmt_get_result($stmt_check_owner);
            $owner_row = mysqli_fetch_assoc($result_check_owner);
            mysqli_stmt_close($stmt_check_owner);

            if ($owner_row && (int)$owner_row['usuario_id'] === $usuario_logado_id) {
                $stmt_update_comment = mysqli_prepare($conexao, "UPDATE comentarios SET conteudo = ?, data_ultima_edicao = NOW() WHERE id = ?");
                if ($stmt_update_comment) {
                    mysqli_stmt_bind_param($stmt_update_comment, "si", $edited_content, $comment_to_edit_id);
                    if (mysqli_stmt_execute($stmt_update_comment)) {
                        $_SESSION['message'] = ['type' => 'success', 'text' => 'Comentário editado com sucesso!'];
                    } else {
                        $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao editar comentário: ' . mysqli_error($conexao)];
                    }
                    mysqli_stmt_close($stmt_update_comment);
                } else {
                    $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao preparar statement de edição: ' . mysqli_error($conexao)];
                }
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Você não tem permissão para editar este comentário.'];
            }
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao verificar dono do comentário: ' . mysqli_error($conexao)];
        }
    }
    header("Location: ComentariosPost.php?id=" . $post_id);
    exit();
}

if ($post_id > 0) {
    $query_post = "
        SELECT
            p.id, p.titulo, p.conteudo, p.data_criacao, p.curtidas, p.id_aluno, p.visualizacoes,
            u.nome_usuario AS nome_aluno,
            GROUP_CONCAT(t.nome SEPARATOR ', ') AS tags_nomes_str
        FROM posts p
        JOIN usuarios u ON p.id_aluno = u.id_usuario
        LEFT JOIN post_tags pt ON p.id = pt.post_id
        LEFT JOIN tags t ON pt.tag_id = t.id
        WHERE p.id = ? AND p.status_moderacao = 'aprovado'
        GROUP BY p.id
    ";
    $stmt_post = mysqli_prepare($conexao, $query_post);
    if ($stmt_post) {
        mysqli_stmt_bind_param($stmt_post, "i", $post_id);
        mysqli_stmt_execute($stmt_post);
        $result_post = mysqli_stmt_get_result($stmt_post);
        $post = mysqli_fetch_assoc($result_post);
        mysqli_stmt_close($stmt_post);
    }
    
    if ($post) {
        $query_comentarios = "
            SELECT
                c.id, c.conteudo, c.data_criacao, c.usuario_id, c.data_ultima_edicao,
                u.nome_usuario AS nome_usuario_comentario,
                u.foto_perfil AS foto_perfil_comentario
            FROM comentarios c
            JOIN usuarios u ON c.usuario_id = u.id_usuario
            WHERE c.post_id = ?
            ORDER BY c.data_criacao ASC
        ";
        $stmt_comentarios = mysqli_prepare($conexao, $query_comentarios);
        if ($stmt_comentarios) {
            mysqli_stmt_bind_param($stmt_comentarios, "i", $post_id);
            mysqli_stmt_execute($stmt_comentarios);
            $result_comentarios = mysqli_stmt_get_result($stmt_comentarios);
            while ($row = mysqli_fetch_assoc($result_comentarios)) {
                $comentarios[] = $row;
            }
            mysqli_stmt_close($stmt_comentarios);
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_comment']) && $post) {
    if (!isset($_SESSION['usuario'])) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Você precisa estar logado para comentar.'];
        header("Location: ComentariosPost.php?id=" . $post_id);
        exit();
    }

    $conteudo_comentario = trim($_POST['conteudo_comentario'] ?? '');
    $usuario_id_comentario = (int)$_SESSION['usuario'];

    if (empty($conteudo_comentario)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'O conteúdo do comentário não pode estar vazio.'];
    } else {
        $stmt_insert_comment = mysqli_prepare($conexao, "INSERT INTO comentarios (post_id, usuario_id, conteudo, data_criacao) VALUES (?, ?, ?, NOW())");
        if ($stmt_insert_comment) {
            mysqli_stmt_bind_param($stmt_insert_comment, "iis", $post_id, $usuario_id_comentario, $conteudo_comentario);
            if (mysqli_stmt_execute($stmt_insert_comment)) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Comentário adicionado com sucesso!'];

                $autor_post_id = $post['id_aluno']; 
                if ($usuario_id_comentario !== $autor_post_id) {
                    $mensagem_notificacao_autor = $_SESSION['nome_usuario'] . ' comentou em seu post: "' . htmlspecialchars($post['titulo']) . '".';
                    inserirNotificacao($conexao, $autor_post_id, $mensagem_notificacao_autor);
                }

                $conquistas_ganhas = [];
                $conquistas_ganhas = array_merge($conquistas_ganhas, verificar_e_desbloquear_conquistas($conexao, $usuario_id_comentario, 'comentarios_feitos'));
                $conquistas_ganhas = array_merge($conquistas_ganhas, verificar_e_desbloquear_conquistas($conexao, $usuario_id_comentario, 'comentario_post_viral', $post_id));

                if (!empty($conquistas_ganhas)) {
                    $_SESSION['novas_conquistas'] = $conquistas_ganhas;
                    foreach ($conquistas_ganhas as $conquista_nome) {
                        $mensagem_notificacao_conquista = 'Parabéns! Você desbloqueou a conquista: "' . htmlspecialchars($conquista_nome) . '".';
                        inserirNotificacao($conexao, $usuario_id_comentario, $mensagem_notificacao_conquista);
                    }
                }

            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao adicionar comentário: ' . mysqli_error($conexao)];
            }
            mysqli_stmt_close($stmt_insert_comment);
        } else {
             $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao preparar statement de inserção: ' . mysqli_error($conexao)];
        }
    }
    header("Location: ComentariosPost.php?id=" . $post_id);
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes da Postagem - Minha ETEC</title>
    <link rel="stylesheet" href="styles/Posts.css">
    <link rel="stylesheet" href="styles/ComentariosPost.css">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="icon" href="../imagens/Logo_Neez.png">
</head>
<body>
    <header>
        <div class="LogoDiv">
            <a href="MenuPrincipal.php" class="logo-link">
            <img class="LogoEtec" src="imagens/Etec_Logo.webp">
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
        <?php if ($post): ?>
            <div class="post-detail-container">
                <div class="post-detail-header">
                    <h2><?= htmlspecialchars($post['titulo']) ?></h2>
                    <div class="post-detail-meta">
                        <span class="post-author">Por: <?= htmlspecialchars($post['nome_aluno']) ?></span>
                        <span class="post-date">Em: <?= date('d/m/Y H:i', strtotime($post['data_criacao'])) ?></span>
                        <span class="post-views"><i class="fas fa-eye"></i> <?= $post['visualizacoes'] ?></span>
                    </div>
                </div>
                <p class="post-detail-content"><?= nl2br(htmlspecialchars($post['conteudo'])) ?></p>
                <?php if (!empty($post['tags_nomes_str'])): ?>
                    <div class="post-detail-tags">
                        Tags:
                        <?php
                        $display_tags = explode(', ', $post['tags_nomes_str']);
                        foreach ($display_tags as $tag):
                            echo '<span class="tag-label">' . htmlspecialchars(trim($tag)) . '</span> ';
                        endforeach;
                        ?>
                    </div>
                <?php endif; ?>
            </div>

            <section class="comments-section">
                <h3>Comentários (<?= count($comentarios) ?>)</h3>

                <?php
                if (isset($_SESSION['novas_conquistas']) && !empty($_SESSION['novas_conquistas'])) {
                    echo '<div style="background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px; margin-bottom: 20px; text-align: center; border-radius: 5px;">';
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

                <?php if (isset($_SESSION['usuario'])): ?>
                    <div class="comment-form">
                        <h4>Deixe seu comentário:</h4>
                        <form action="ComentariosPost.php?id=<?= $post_id ?>" method="POST">
                            <textarea name="conteudo_comentario" placeholder="Escreva seu comentário aqui..." required></textarea>
                            <button type="submit" name="submit_comment">Enviar Comentário</button>
                        </form>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: #bdc3c7;">Faça login para deixar um comentário.</p>
                <?php endif; ?>

                <div class="comment-list">
                    <?php if (!empty($comentarios)): ?>
                        <?php foreach ($comentarios as $comment):
                            $comment_foto_perfil_src = 'imagens/FotoPerfilGen.jpg';
                            if (!empty($comment['foto_perfil_comentario'])) {
                                $temp_comment_foto_path = 'uploads/perfis/' . htmlspecialchars($comment['foto_perfil_comentario']);
                                if (file_exists($temp_comment_foto_path)) {
                                    $comment_foto_perfil_src = $temp_comment_foto_path;
                                }
                            }
                        ?>
                            <div class="comment-item">
                                <img class="comment-avatar" src="<?= $comment_foto_perfil_src ?>" alt="Foto de Perfil">
                                <div class="comment-content-wrapper">
                                    <div class="comment-author">
                                        <?= htmlspecialchars($comment['nome_usuario_comentario']) ?>
                                        <span class="comment-date">
                                            <?= date('d/m/Y H:i', strtotime($comment['data_criacao'])) ?>
                                            <?php if (!empty($comment['data_ultima_edicao']) && $comment['data_ultima_edicao'] != $comment['data_criacao']): ?>
                                                (Editado em <?= date('d/m/Y H:i', strtotime($comment['data_ultima_edicao'])) ?>)
                                            <?php endif; ?>
                                        </span>
                                        <?php
                                        if (isset($_SESSION['usuario']) &&
                                            (($_SESSION['usuario'] == $comment['usuario_id']) || (isset($_SESSION['nivel']) && $_SESSION['nivel'] == ADMIN))
                                        ) :
                                        ?>
                                            <div class="comment-actions">
                                                <?php if ($_SESSION['usuario'] == $comment['usuario_id']): ?>
                                                    <button class="btn-edit-comment"
                                                            data-comment-id="<?= $comment['id'] ?>"
                                                            data-comment-content="<?= htmlspecialchars($comment['conteudo']) ?>">
                                                            <i class="fas fa-edit"></i> Editar
                                                    </button>
                                                <?php endif; ?>
                                                <form action="ComentariosPost.php?id=<?= $post_id ?>" method="POST" style="display: inline-block; margin-left: 5px;">
                                                    <input type="hidden" name="delete_comment_id" value="<?= $comment['id'] ?>">
                                                    <button type="submit" class="btn-delete-comment" onclick="return confirm('Tem certeza que deseja excluir este comentário?');">
                                                        <i class="fas fa-trash-alt"></i> Excluir
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <p class="comment-text"><?= nl2br(htmlspecialchars($comment['conteudo'])) ?></p>

                                    <form class="edit-comment-form" style="display: none; margin-top: 10px;" action="ComentariosPost.php?id=<?= $post_id ?>" method="POST">
                                        <input type="hidden" name="edit_comment_id" value="<?= $comment['id'] ?>">
                                        <textarea name="edited_content" class="edit-comment-textarea" rows="3" required><?= htmlspecialchars($comment['conteudo']) ?></textarea>
                                        <button type="submit" class="btn-save-edit">Salvar</button>
                                        <button type="button" class="btn-cancel-edit">Cancelar</button>
                                    </form>

                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #bdc3c7;">Nenhum comentário ainda. Seja o primeiro a comentar!</p>
                    <?php endif; ?>
                </div>
            </section>
        <?php else: ?>
            <p style="text-align: center; font-size: 1.2em; color: #e74c3c;">Postagem não encontrada ou não aprovada.</p>
        <?php endif; ?>
    </main>

<script src="script/ComentariosPost.js"></script>

</body>
</html>