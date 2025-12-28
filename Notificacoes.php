<?php
include_once('Conexao.php');
session_start();

define('ALUNO', 1);
define('ADMIN', 2);
define('PROFESSOR', 3);

// Verifica se usuário está logado; se não, redireciona para login
if (empty($_SESSION['usuario'])) {
    header("Location: Login.php");
    exit();
}

// Verifica conexão com banco de dados
if (!isset($conexao) || !$conexao || mysqli_ping($conexao) === false) {
    die("Erro: Banco de dados indisponível ou conexão inválida.");
}

$id_usuario = (int) $_SESSION['usuario'];
$_SESSION['nivel'] = $_SESSION['nivel'] ?? null;

// Inicializa variáveis padrão para nome e foto do usuário
$nome_usuario_logado = "Usuário";
$foto_perfil_src = 'imagens/FotoPerfilGen.jpg';
$notificacoes_nao_lidas_count = 0;

// Busca nome e foto de perfil do usuário logado para exibição no cabeçalho
$query_usuario_header = "SELECT nome_usuario, foto_perfil FROM usuarios WHERE id_usuario = ? LIMIT 1";
$stmt_usuario_header = mysqli_prepare($conexao, $query_usuario_header);
if ($stmt_usuario_header) {
    mysqli_stmt_bind_param($stmt_usuario_header, "i", $id_usuario);
    mysqli_stmt_execute($stmt_usuario_header);
    $result_usuario_header = mysqli_stmt_get_result($stmt_usuario_header);
    if ($row_usuario_header = mysqli_fetch_assoc($result_usuario_header)) {
        $nome_usuario_logado = htmlspecialchars($row_usuario_header['nome_usuario']);
        // Verifica se o usuário tem foto de perfil personalizada e se arquivo existe
        if (!empty($row_usuario_header['foto_perfil'])) {
            $temp_foto_path = 'uploads/perfis/' . htmlspecialchars($row_usuario_header['foto_perfil']);
            if (file_exists($temp_foto_path)) {
                $foto_perfil_src = $temp_foto_path;
            }
        }
    }
    mysqli_stmt_close($stmt_usuario_header);
}

// Consulta quantidade de notificações não lidas do usuário para exibir badge
$query_notificacoes_count = "SELECT COUNT(*) AS total FROM notificacoes WHERE id_usuario_destino = ? AND lida = 0";
$stmt_notificacoes_count = mysqli_prepare($conexao, $query_notificacoes_count);
if ($stmt_notificacoes_count) {
    mysqli_stmt_bind_param($stmt_notificacoes_count, "i", $id_usuario);
    mysqli_stmt_execute($stmt_notificacoes_count);
    $result_notificacoes_count = mysqli_stmt_get_result($stmt_notificacoes_count);
    if ($row_count = mysqli_fetch_assoc($result_notificacoes_count)) {
        $notificacoes_nao_lidas_count = (int) $row_count['total'];
    }
    mysqli_stmt_close($stmt_notificacoes_count);
}

// Busca todas as notificações do usuário para exibir na lista, ordenando pela mais recente
$todas_notificacoes = [];
$query_todas_notificacoes = "SELECT id, mensagem, data_criacao, lida FROM notificacoes WHERE id_usuario_destino = ? ORDER BY data_criacao DESC";
$stmt_todas_notificacoes = mysqli_prepare($conexao, $query_todas_notificacoes);

if ($stmt_todas_notificacoes) {
    mysqli_stmt_bind_param($stmt_todas_notificacoes, "i", $id_usuario);
    mysqli_stmt_execute($stmt_todas_notificacoes);
    $result_todas_notificacoes = mysqli_stmt_get_result($stmt_todas_notificacoes);

    while ($row = mysqli_fetch_assoc($result_todas_notificacoes)) {
        $todas_notificacoes[] = [
            'id' => $row['id'],
            'mensagem' => $row['mensagem'],
            'data' => $row['data_criacao'],
            'lida' => (bool) $row['lida'] // true se notificação já foi lida
        ];
    }
    mysqli_stmt_close($stmt_todas_notificacoes);
} else {
    error_log("Erro ao preparar a consulta de todas as notificações: " . mysqli_error($conexao));
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Todas as Notificações - <?= $nome_usuario_logado ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="icon" href="imagens/Logo_Neez.png">
    <link rel="stylesheet" href="styles/Notificacoes.css">
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
        <div class="user-actions">
            <!-- Exibe badge com número de notificações não lidas, se houver -->
            <?php if ($notificacoes_nao_lidas_count > 0): ?>
                <span class="notification-badge"><?= $notificacoes_nao_lidas_count ?></span>
            <?php endif; ?>
            <a href="Perfil.php">
                <img class="profile-icon" src="<?= $foto_perfil_src ?>" alt="Perfil">
            </a>
        </div>
    </header>

    <main class="container notificacoes-container">
        <h1><i class="fas fa-bell"></i> Todas as Notificações</h1>

        <!-- Lista todas as notificações, destacando as não lidas -->
        <?php if (!empty($todas_notificacoes)): ?>
            <ul class="lista-notificacoes">
                <?php foreach ($todas_notificacoes as $notificacao): ?>
                    <li class="notificacao-item <?= $notificacao['lida'] ? 'lida' : '' ?>">
                        <span class="notificacao-mensagem"><?= htmlspecialchars($notificacao['mensagem']) ?></span>
                        <span class="notificacao-data"><?= date('d/m/Y H:i', strtotime($notificacao['data'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>Você não possui nenhuma notificação.</p>
        <?php endif; ?>

        <a href="Perfil.php" class="btn-voltar">Voltar para o Perfil</a>
    </main>


    <script src="script/Notificacoes.js"></script>


</body>

</html>
<?php
// Fecha conexão com banco ao fim do script
if (isset($conexao) && $conexao) {
    mysqli_close($conexao);
}
?>