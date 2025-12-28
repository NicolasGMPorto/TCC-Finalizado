<?php
require_once 'Conexao.php';
require_once 'FuncoesConquistas.php';
require_once 'FuncoesNotificacao.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

define('ALUNO', 1);
define('ADMIN', 2);
define('PROFESSOR', 3);

$foto_perfil_src = 'imagens/FotoPerfilGen.jpg';
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

$id_aluno_logado = $id_usuario_logado;

if (!$id_aluno_logado) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Por favor, faça login para criar postagens.'];
}

$availableTags = [];
if ($conexao->connect_error) {
    error_log("Erro de conexão MySQLi: " . $conexao->connect_error);
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao conectar com o banco de dados para carregar tags.'];
} else {
    try {
        $result_tags = $conexao->query("SELECT id, nome FROM tags ORDER BY nome ASC");
        if ($result_tags) {
            while ($row = $result_tags->fetch_assoc()) {
                $availableTags[] = $row;
            }
            $result_tags->free();
        }
    } catch (Exception $e) {
        error_log("Erro ao carregar tags (MySQLi): " . $e->getMessage());
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao carregar tags disponíveis.'];
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_post'])) {
    $post_title = trim($_POST['post_title'] ?? '');
    $post_content = trim($_POST['post_content'] ?? '');
    $selected_tag_names = $_POST['tags'] ?? [];

    if (empty($post_title) || empty($post_content)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Título e conteúdo da postagem são obrigatórios.'];
        header('Location: Posts.php');
        exit();
    }
    if (!$id_aluno_logado) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Você precisa estar logado para criar uma postagem.'];
        header('Location: Posts.php');
        exit();
    }

    $conexao->begin_transaction();

    try {
        $stmt_post = $conexao->prepare("INSERT INTO posts (id_aluno, titulo, conteudo, status_moderacao, data_criacao) VALUES (?, ?, ?, 'pendente', NOW())");
        if (!$stmt_post) {
            throw new Exception("Erro ao preparar statement de postagem: " . $conexao->error);
        }
        $stmt_post->bind_param("iss", $id_aluno_logado, $post_title, $post_content);
        $stmt_post->execute();
        $post_id = $conexao->insert_id;
        $stmt_post->close();

        if ($post_id && !empty($selected_tag_names)) {
            $placeholders = implode(',', array_fill(0, count($selected_tag_names), '?'));
            $stmt_get_tag_ids = $conexao->prepare("SELECT id FROM tags WHERE nome IN ($placeholders)");
            if (!$stmt_get_tag_ids) {
                throw new Exception("Erro ao preparar statement para obter IDs de tags: " . $conexao->error);
            }
            $types = str_repeat('s', count($selected_tag_names));
            $bind_params = array_merge([$types], $selected_tag_names);
            call_user_func_array([$stmt_get_tag_ids, 'bind_param'], refValues($bind_params));
            $stmt_get_tag_ids->execute();
            $result_tag_ids = $stmt_get_tag_ids->get_result();

            $selected_tag_ids = [];
            while ($row = $result_tag_ids->fetch_assoc()) {
                $selected_tag_ids[] = $row['id'];
            }
            $stmt_get_tag_ids->close();

            if (!empty($selected_tag_ids)) {
                $stmt_post_tag = $conexao->prepare("INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)");
                if (!$stmt_post_tag) {
                    throw new Exception("Erro ao preparar statement para post_tags: " . $conexao->error);
                }
                foreach ($selected_tag_ids as $tag_id) {
                    $stmt_post_tag->bind_param("ii", $post_id, $tag_id);
                    $stmt_post_tag->execute();
                }
                $stmt_post_tag->close();
            }
        }

        $conexao->commit();
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Postagem enviada para moderação com sucesso! Aguarde a aprovação.'];

        $conquistas_ganhas = [];
        $conquistas_ganhas = array_merge($conquistas_ganhas, verificar_e_desbloquear_conquistas($conexao, $id_aluno_logado, 'posts_criados'));
        $conquistas_ganhas = array_merge($conquistas_ganhas, verificar_e_desbloquear_conquistas($conexao, $id_aluno_logado, 'post_viral', $post_id));
        $conquistas_ganhas = array_merge($conquistas_ganhas, verificar_e_desbloquear_conquistas($conexao, $id_aluno_logado, 'post_as_3h', $post_id));

        if (!empty($conquistas_ganhas)) {
            $_SESSION['novas_conquistas'] = $conquistas_ganhas;
            foreach ($conquistas_ganhas as $conquista_nome) {
                $mensagem_notificacao_conquista = 'Parabéns! Você desbloqueou a conquista: "' . htmlspecialchars($conquista_nome) . '".';
                inserirNotificacao($conexao, $id_aluno_logado, $mensagem_notificacao_conquista);
            }
        }

        header('Location: Posts.php');
        exit();

    } catch (Exception $e) {
        $conexao->rollback();
        error_log("Erro ao criar postagem (MySQLi): " . $e->getMessage());
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao enviar postagem. Por favor, tente novamente mais tarde.'];
        header('Location: Posts.php');
        exit();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'toggle_like') {
    header('Content-Type: application/json');

    if (!$id_usuario_logado) {
        echo json_encode(['success' => false, 'message' => 'Usuário não logado.']);
        exit();
    }

    $post_id_like = (int) $_POST['post_id'];
    $is_liked = false;

    $query_check_like = "SELECT id FROM post_likes WHERE post_id = ? AND usuario_id = ?";
    $stmt_check_like = mysqli_prepare($conexao, $query_check_like);
    if ($stmt_check_like) {
        mysqli_stmt_bind_param($stmt_check_like, "ii", $post_id_like, $id_usuario_logado);
        mysqli_stmt_execute($stmt_check_like);
        mysqli_stmt_store_result($stmt_check_like);
        if (mysqli_stmt_num_rows($stmt_check_like) > 0) {
            $is_liked = true;
        }
        mysqli_stmt_close($stmt_check_like);
    }

    $conexao->begin_transaction();
    try {
        if ($is_liked) {
            $stmt_unlike = mysqli_prepare($conexao, "DELETE FROM post_likes WHERE post_id = ? AND usuario_id = ?");
            if ($stmt_unlike) {
                mysqli_stmt_bind_param($stmt_unlike, "ii", $post_id_like, $id_usuario_logado);
                mysqli_stmt_execute($stmt_unlike);
                mysqli_stmt_close($stmt_unlike);

                $stmt_update_post = mysqli_prepare($conexao, "UPDATE posts SET curtidas = curtidas - 1 WHERE id = ?");
                mysqli_stmt_bind_param($stmt_update_post, "i", $post_id_like);
                mysqli_stmt_execute($stmt_update_post);
                mysqli_stmt_close($stmt_update_post);

                $conexao->commit();
                echo json_encode(['success' => true, 'is_liked' => false, 'message' => 'Post descurtido.']);
            } else {
                throw new Exception("Erro ao preparar descurtir.");
            }
        } else {
            $stmt_like = mysqli_prepare($conexao, "INSERT INTO post_likes (post_id, usuario_id) VALUES (?, ?)");
            if ($stmt_like) {
                mysqli_stmt_bind_param($stmt_like, "ii", $post_id_like, $id_usuario_logado);
                mysqli_stmt_execute($stmt_like);
                mysqli_stmt_close($stmt_like);

                $stmt_update_post = mysqli_prepare($conexao, "UPDATE posts SET curtidas = curtidas + 1 WHERE id = ?");
                mysqli_stmt_bind_param($stmt_update_post, "i", $post_id_like);
                mysqli_stmt_execute($stmt_update_post);
                mysqli_stmt_close($stmt_update_post);

                $query_autor_post_like = "SELECT id_aluno, titulo FROM posts WHERE id = ?";
                $stmt_autor_post_like = mysqli_prepare($conexao, $query_autor_post_like);
                if ($stmt_autor_post_like) {
                    mysqli_stmt_bind_param($stmt_autor_post_like, "i", $post_id_like);
                    mysqli_stmt_execute($stmt_autor_post_like);
                    $result_autor_post_like = mysqli_stmt_get_result($stmt_autor_post_like);
                    if ($row_autor_post_like = mysqli_fetch_assoc($result_autor_post_like)) {
                        $autor_post_id_like = (int) $row_autor_post_like['id_aluno'];
                        $titulo_post_like = htmlspecialchars($row_autor_post_like['titulo']);

                        if ($id_usuario_logado !== $autor_post_id_like) {
                            $nome_usuario_curtiu = $_SESSION['nome_usuario'] ?? 'Alguém';
                            $mensagem_notificacao_curtida = $nome_usuario_curtiu . ' curtiu sua postagem: "' . $titulo_post_like . '".';
                            inserirNotificacao($conexao, $autor_post_id_like, $mensagem_notificacao_curtida);
                        }
                    }
                    mysqli_stmt_close($stmt_autor_post_like);
                }

                $conquistas_ganhas_curtida = verificar_e_desbloquear_conquistas($conexao, $id_usuario_logado, 'curtidas_feitas');
                if (!empty($conquistas_ganhas_curtida)) {
                    foreach ($conquistas_ganhas_curtida as $conquista_nome) {
                        $mensagem_conquista = 'Parabéns! Você desbloqueou a conquista: "' . htmlspecialchars($conquista_nome) . '".';
                        inserirNotificacao($conexao, $id_usuario_logado, $mensagem_conquista);
                    }
                }

                $conexao->commit();
                echo json_encode(['success' => true, 'is_liked' => true, 'message' => 'Post curtido.']);
            } else {
                throw new Exception("Erro ao preparar curtir.");
            }
        }
    } catch (Exception $e) {
        $conexao->rollback();
        error_log("Erro ao curtir/descurtir post: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro ao processar a curtida: ' . $e->getMessage()]);
    }
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_post') {
    header('Content-Type: application/json');

    if (!$id_usuario_logado) {
        echo json_encode(['success' => false, 'message' => 'Usuário não logado.']);
        exit();
    }

    $post_id_to_delete = (int) $_POST['post_id'];
    $usuario_logado_id = (int) $id_usuario_logado;
    $nivel_usuario_logado_int = (int) $nivel_usuario_logado;

    $conexao->begin_transaction();
    try {
        $query_get_author = "SELECT id_aluno FROM posts WHERE id = ?";
        $stmt_get_author = mysqli_prepare($conexao, $query_get_author);
        if (!$stmt_get_author) {
            throw new Exception("Erro ao preparar consulta do autor: " . mysqli_error($conexao));
        }
        mysqli_stmt_bind_param($stmt_get_author, "i", $post_id_to_delete);
        mysqli_stmt_execute($stmt_get_author);
        $result_get_author = mysqli_stmt_get_result($stmt_get_author);
        $post_author_row = mysqli_fetch_assoc($result_get_author);
        mysqli_stmt_close($stmt_get_author);

        if (!$post_author_row) {
            throw new Exception("Postagem não encontrada.");
        }

        $post_author_id = (int) $post_author_row['id_aluno'];

        if ($usuario_logado_id === $post_author_id || $nivel_usuario_logado_int === ADMIN) {
            $stmt_delete_post = mysqli_prepare($conexao, "DELETE FROM posts WHERE id = ?");
            if (!$stmt_delete_post) {
                throw new Exception("Erro ao preparar exclusão da postagem: " . mysqli_error($conexao));
            }
            mysqli_stmt_bind_param($stmt_delete_post, "i", $post_id_to_delete);

            if (mysqli_stmt_execute($stmt_delete_post)) {
                $conexao->commit();
                echo json_encode(['success' => true, 'message' => 'Postagem excluída com sucesso!']);
            } else {
                throw new Exception("Erro ao excluir postagem: " . mysqli_error($conexao));
            }
            mysqli_stmt_close($stmt_delete_post);
        } else {
            throw new Exception("Você não tem permissão para excluir esta postagem.");
        }
    } catch (Exception $e) {
        $conexao->rollback();
        error_log("Erro ao excluir postagem: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir postagem: ' . $e->getMessage()]);
    }
    exit();
}

function refValues($arr)
{
    if (strnatcmp(phpversion(), '5.3') >= 0) {
        $refs = array();
        foreach ($arr as $key => $value)
            $refs[$key] = &$arr[$key];
        return $refs;
    }
    return $arr;
}

$posts = [];
$filter_tag_name = $_GET['tag'] ?? '';

if ($conexao->connect_error) {
    $posts = [];
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao conectar com o banco de dados para carregar postagens.'];
} else {
    try {
        $query = "
            SELECT
                p.id, p.titulo, p.conteudo, p.data_criacao, p.curtidas, p.visualizacoes, p.id_aluno,
                u.nome_usuario AS nome_aluno,
                GROUP_CONCAT(t.nome SEPARATOR ', ') AS tags_nomes_str,
                (SELECT COUNT(*) FROM comentarios c WHERE c.post_id = p.id) AS total_comentarios,
                (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id AND pl.usuario_id = ?) AS usuario_curtiu
            FROM
                posts p
            JOIN
                usuarios u ON p.id_aluno = u.id_usuario
            LEFT JOIN
                post_tags pt ON p.id = pt.post_id
            LEFT JOIN
                tags t ON pt.tag_id = t.id
            WHERE
                p.status_moderacao = 'aprovado'
        ";

        if (!empty($filter_tag_name)) {
            $query .= "
                AND p.id IN (
                    SELECT pt_filter.post_id
                    FROM post_tags pt_filter
                    JOIN tags t_filter ON pt_filter.tag_id = t_filter.id
                    WHERE t_filter.nome = '" . $conexao->real_escape_string($filter_tag_name) . "'
                )
            ";
        }

        $query .= "
            GROUP BY
                p.id, p.titulo, p.conteudo, p.data_criacao, p.curtidas, p.visualizacoes, u.nome_usuario, p.id_aluno
            ORDER BY
                p.data_criacao DESC
        ";

        $stmt_fetch_posts = mysqli_prepare($conexao, $query);
        if ($stmt_fetch_posts) {
            mysqli_stmt_bind_param($stmt_fetch_posts, "i", $id_usuario_logado);
            mysqli_stmt_execute($stmt_fetch_posts);
            $result_fetch_posts = mysqli_stmt_get_result($stmt_fetch_posts);

            if ($result_fetch_posts) {
                while ($row = $result_fetch_posts->fetch_assoc()) {
                    $posts[] = $row;
                }
                $result_fetch_posts->free();
            }
            mysqli_stmt_close($stmt_fetch_posts);
        } else {
            throw new Exception("Erro ao preparar a consulta de posts: " . mysqli_error($conexao));
        }

    } catch (Exception $e) {
        error_log("Erro ao carregar posts (MySQLi): " . $e->getMessage());
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Não foi possível carregar as postagens no momento.'];
        $posts = [];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Postagens</title>
    <link rel="stylesheet" href="styles/Posts.css">
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
                <a href="Videos.php">Vídeos</a>
                <a href="Uploads.php">Uploads</a>

                <?php
                if (isset($_SESSION['nivel']) && $_SESSION['nivel'] == ADMIN):
                    ?>
                    <a href="GerenciarPostagens.php">Gerenciar Postagens</a>
                <?php endif; ?>

            </div>
        </div>
        <div class="user-actions">
            <a href="Perfil.php">
                <img class="profile-icon" src="<?= $foto_perfil_src ?>" alt="Perfil">
            </a>
        </div>
    </header>

    <main class="container">
        <h2>Painel de Postagens</h2>

        <?php
        if (isset($_SESSION['message'])) {
            $msg_type = $_SESSION['message']['type'];
            $msg_text = $_SESSION['message']['text'];
            echo "<div id='alertMessage' class='alert {$msg_type}'>{$msg_text}</div>";
            unset($_SESSION['message']);
        }
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

        <div class="add-post-toggle">
            <button id="addPostButton" class="add-post-button" title="Adicionar Nova Postagem">
                + Adicionar Post
            </button>
        </div>

        <div id="addPostContainer" class="create-post-section hidden">
            <h3>Crie Sua Postagem</h3>
            <form action="Posts.php" method="POST">
                <label for="post_title">Título da Postagem:</label>
                <input type="text" id="post_title" name="post_title" placeholder="Ex: Dúvida em Cálculo Diferencial"
                    required>

                <label for="post_content">Conteúdo da Postagem:</label>
                <textarea id="post_content" name="post_content" placeholder="Descreva sua dúvida ou peça ajuda aqui..."
                    required></textarea>

                <label>Tags (selecione):</label>
                <div class="tags-container">
                    <?php if (!empty($availableTags)): ?>
                        <?php foreach ($availableTags as $tag): ?>
                            <label class="tag-checkbox">
                                <input type="checkbox" name="tags[]" value="<?= htmlspecialchars($tag['nome']) ?>">
                                <span><?= htmlspecialchars($tag['nome']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>Nenhuma tag disponível. O administrador precisa cadastrar tags.</p>
                    <?php endif; ?>
                </div>
                <small>Selecione as tags que melhor descrevem sua postagem.</small>

            <center><button type="submit" name="submit_post" class="submit-button">Enviar Postagem</button></center>
            <center><button type="button" id="cancelAddPost" class="cancel-button">Cancelar</button></center>
            </form>
        </div>

        <hr>

        <section class="existing-posts-section">
            <h3>Postagens da Comunidade</h3>

            <div class="tag-filter-container">
                <form action="Posts.php" method="GET" class="tag-filter-form">
                   <center><label for="filter_tag">Filtrar por Tag:</label></center>
                    <center><select name="tag" id="filter_tag" onchange="this.form.submit()">
                        <option value="">Todas as Tags</option>
                        <?php
                        $selected_tag = $_GET['tag'] ?? '';
                        foreach ($availableTags as $tag): ?>
                            <option value="<?= htmlspecialchars($tag['nome']) ?>" <?= ($selected_tag == $tag['nome']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tag['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select></center>
                </form>
            </div>
            <?php if (!empty($posts)): ?>
                <div class="post-grid">
                    <?php foreach ($posts as $post): ?>
                        <div class="post-item">
                            <div class="post-header">
                                <h4><?= htmlspecialchars($post['titulo']) ?></h4>
                                <div class="post-stats">
                                    <?php if ($id_usuario_logado): ?>

                                        <span class="post-author">Por: <?= htmlspecialchars($post['nome_aluno']) ?></span>
                                        <span class="post-date"> - <?= date('d/m/Y H:i', strtotime($post['data_criacao'])) ?></span>
                                        <button class="like-button <?= $post['usuario_curtiu'] ? 'liked' : '' ?>"
                                            data-post-id="<?= htmlspecialchars($post['id']) ?>"
                                            data-is-liked="<?= $post['usuario_curtiu'] ? 'true' : 'false' ?>">
                                            <i class="fas fa-heart"></i>
                                        </button>
                                    <?php else: ?>
                                        <i class="fas fa-heart"></i>
                                    <?php endif; ?>
                                    <span class="like-count"><?= htmlspecialchars($post['curtidas']) ?></span>
                                    <i class="fas fa-eye"></i> <span
                                        class="view-count"><?= htmlspecialchars($post['visualizacoes']) ?></span>
                                </div>
                            </div>
                            <p class="post-content"><?= nl2br(htmlspecialchars($post['conteudo'])) ?></p>
                            <?php if (!empty($post['tags_nomes_str'])): ?>
                                <div class="post-meta">
                                    <span class="post-tags">Tags:
                                        <?php
                                        $display_tags = explode(', ', $post['tags_nomes_str']);
                                        foreach ($display_tags as $tag):
                                            echo '<span class="tag-label">' . htmlspecialchars(trim($tag)) . '</span> ';
                                        endforeach;
                                        ?>
                                    </span>
                                    <a href="ComentariosPost.php?id=<?= htmlspecialchars($post['id']) ?>"
                                        class="btn-view-comments">Ver Respostas
                                        (<?= htmlspecialchars($post['total_comentarios']) ?>)</a>
                                </div>
                            <?php endif; ?>
                            <?php if ($id_usuario_logado && ($id_usuario_logado == $post['id_aluno'] || $nivel_usuario_logado == ADMIN)): ?>
                                <div class="post-actions" style="text-align: right; margin-top: 10px;">
                                    <button class="btn-delete-post" data-post-id="<?= htmlspecialchars($post['id']) ?>">
                                        <i class="fas fa-trash-alt"></i> Excluir Postagem
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #AAA;">
                    <?php if (!empty($filter_tag_name)): ?>
                        Nenhuma postagem aprovada com a tag "<?= htmlspecialchars($filter_tag_name) ?>" para exibir ainda.
                    <?php else: ?>
                        Nenhuma postagem aprovada para exibir ainda.
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </section>

    </main>

    <script src="script/Posts.js"></script>
    <script src="script/AlertasGerais.js"></script>

</body>

</html>
<?php
if (isset($conexao) && $conexao) {
    mysqli_close($conexao);
}
?>