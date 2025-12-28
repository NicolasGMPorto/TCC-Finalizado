<?php
include_once('Conexao.php'); // Inclui o arquivo de conexão com o banco de dados
session_start(); // Inicia a sessão para manter dados entre páginas

// Constantes que representam os níveis de acesso dos usuários
define('ALUNO', 1);
define('ADMIN', 2);
define('PROFESSOR', 3);

// Redireciona para o login se o usuário não estiver autenticado
if (empty($_SESSION['usuario'])) {
    header("Location: Login.php");
    exit();
}

// Verifica se a conexão com o banco está ativa
if (!isset($conexao) || !$conexao || mysqli_ping($conexao) === false) {
    die("Erro: Banco de dados indisponível ou conexão inválida.");
}

$id_usuario = (int) $_SESSION['usuario'];

// Busca o nível e foto de perfil do usuário
$query_usuario = "SELECT nivel, foto_perfil FROM usuarios WHERE id_usuario = ? LIMIT 1";
$stmt_usuario = mysqli_prepare($conexao, $query_usuario);
if (!$stmt_usuario) {
    die("Erro ao preparar consulta de usuário: " . mysqli_error($conexao));
}
mysqli_stmt_bind_param($stmt_usuario, "i", $id_usuario);
mysqli_stmt_execute($stmt_usuario);
$result_usuario = mysqli_stmt_get_result($stmt_usuario);
$usuario = mysqli_fetch_assoc($result_usuario);
mysqli_stmt_close($stmt_usuario);

if (!$usuario) {
    die("Usuário não encontrado.");
}

// Define a foto padrão ou personalizada
$foto_perfil_src = 'imagens/FotoPerfilGen.jpg';
if (!empty($usuario['foto_perfil'])) {
    $temp_foto_path = 'uploads/perfis/' . htmlspecialchars($usuario['foto_perfil']);
    if (file_exists($temp_foto_path)) {
        $foto_perfil_src = $temp_foto_path;
    }
}

// Inicializa contadores
$total_questionarios_respondidos = 0;
$total_conquistas_desbloqueadas = 0;
$todas_conquistas_exibir = [];

// Processa dados apenas para alunos
if ($usuario['nivel'] == ALUNO) {

    // Conta o total de questionários únicos respondidos
    $query_contador_questionarios = "SELECT COUNT(DISTINCT id_questionario) AS total_respondidos FROM resultados_questionarios WHERE id_usuario = ?";
    $stmt_contador_questionarios = mysqli_prepare($conexao, $query_contador_questionarios);

    if ($stmt_contador_questionarios) {
        mysqli_stmt_bind_param($stmt_contador_questionarios, "i", $id_usuario);
        mysqli_stmt_execute($stmt_contador_questionarios);
        $result_contador = mysqli_stmt_get_result($stmt_contador_questionarios);
        $row_contador = mysqli_fetch_assoc($result_contador);
        $total_questionarios_respondidos = $row_contador['total_respondidos'];
        mysqli_stmt_close($stmt_contador_questionarios);
    } else {
        error_log("Erro ao preparar consulta do contador de questionários: " . mysqli_error($conexao));
    }

    // Busca todas as conquistas disponíveis
    $query_master = "SELECT id_conquista, nome, descricao, raridade FROM conquistas_master ORDER BY nome ASC";
    $result_master = mysqli_query($conexao, $query_master);
    $conquistas_master = [];
    while ($row = mysqli_fetch_assoc($result_master)) {
        $conquistas_master[$row['id_conquista']] = $row;
    }

    // Busca conquistas já obtidas pelo usuário
    $query_obtidas = "SELECT id_conquista, data_conquista FROM usuario_conquistas WHERE id_usuario = ?";
    $stmt_obtidas = mysqli_prepare($conexao, $query_obtidas);
    $conquistas_obtidas_usuario = [];
    if ($stmt_obtidas) {
        mysqli_stmt_bind_param($stmt_obtidas, "i", $id_usuario);
        mysqli_stmt_execute($stmt_obtidas);
        $result_obtidas = mysqli_stmt_get_result($stmt_obtidas);
        while ($row = mysqli_fetch_assoc($result_obtidas)) {
            $conquistas_obtidas_usuario[$row['id_conquista']] = $row['data_conquista'];
        }
        mysqli_stmt_close($stmt_obtidas);
    } else {
        error_log("Erro ao preparar consulta de conquistas obtidas: " . mysqli_error($conexao));
    }

    // Conta o total de alunos cadastrados
    $total_alunos_query = "SELECT COUNT(*) AS total FROM usuarios WHERE nivel = ?";
    $stmt_total_alunos = mysqli_prepare($conexao, $total_alunos_query);
    $total_alunos = 0;
    if ($stmt_total_alunos) {
        $aluno_const = ALUNO;
        mysqli_stmt_bind_param($stmt_total_alunos, "i", $aluno_const);
        mysqli_stmt_execute($stmt_total_alunos);
        $result_total_alunos = mysqli_stmt_get_result($stmt_total_alunos);
        $row_total_alunos = mysqli_fetch_assoc($result_total_alunos);
        $total_alunos = $row_total_alunos['total'];
        mysqli_stmt_close($stmt_total_alunos);
    } else {
        error_log("Erro ao preparar consulta de total de alunos: " . mysqli_error($conexao));
    }

    // Conta quantos alunos têm cada conquista
    $usuarios_por_conquista = [];
    $query_count_conquistas = "
        SELECT uc.id_conquista, COUNT(DISTINCT uc.id_usuario) AS num_usuarios
        FROM usuario_conquistas uc
        JOIN usuarios u ON uc.id_usuario = u.id_usuario
        WHERE u.nivel = ?
        GROUP BY uc.id_conquista
    ";
    $stmt_count_conquistas = mysqli_prepare($conexao, $query_count_conquistas);
    if ($stmt_count_conquistas) {
        $aluno_const = ALUNO;
        mysqli_stmt_bind_param($stmt_count_conquistas, "i", $aluno_const);
        mysqli_stmt_execute($stmt_count_conquistas);
        $result_count_conquistas = mysqli_stmt_get_result($stmt_count_conquistas);
        while ($row = mysqli_fetch_assoc($result_count_conquistas)) {
            $usuarios_por_conquista[$row['id_conquista']] = $row['num_usuarios'];
        }
        mysqli_stmt_close($stmt_count_conquistas);
    } else {
        error_log("Erro ao preparar consulta de contagem de usuários por conquista: " . mysqli_error($conexao));
    }

    // Monta a lista de conquistas para exibição
    foreach ($conquistas_master as $id_conquista => $conquista_info) {
        $obtida = isset($conquistas_obtidas_usuario[$id_conquista]);
        $data_obtencao = $obtida ? $conquistas_obtidas_usuario[$id_conquista] : null;

        $num_usuarios_com_conquista = $usuarios_por_conquista[$id_conquista] ?? 0;
        $porcentagem_alunos = 0;
        if ($total_alunos > 0) {
            $porcentagem_alunos = round(($num_usuarios_com_conquista / $total_alunos) * 100, 1);
        }

        $todas_conquistas_exibir[] = [
            'id' => $id_conquista,
            'nome' => $conquista_info['nome'],
            'descricao' => $conquista_info['descricao'],
            'raridade' => $conquista_info['raridade'],
            'porcentagem_alunos' => $porcentagem_alunos,
            'obtida' => $obtida,
            'data_obtencao' => $data_obtencao
        ];

        if ($obtida) {
            $total_conquistas_desbloqueadas++;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Conquistas</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="styles/Perfil.css">
    <link rel="stylesheet" href="styles/Conquistas.css">
    <link rel="icon" href="imagens/Logo_Neez.png">
</head>

<body class="conquistas-page">
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

    <div class="container conquistas-container">
        <div class="conquistas-header">
            <h1><i class="fas fa-trophy"></i> Minhas Conquistas</h1>
            <p>Acompanhe seu progresso e marcos alcançados na plataforma!</p>
        </div>

        <div class="conquistas-stats">
            <h2>Estatísticas de Atividade</h2>
            <div class="stat-item">
                <strong>Conquistas desbloqueadas:</strong>
                <span><?= htmlspecialchars($total_conquistas_desbloqueadas) ?></span>
            </div>
            <div class="stat-item">
                <strong>Questionários respondidos:</strong>
                <span><?= htmlspecialchars($total_questionarios_respondidos) ?></span>
            </div>
        </div>

        <?php if ($usuario['nivel'] == ALUNO): ?>
            <div class="secao-conquistas">
                <h2><i class="fas fa-award"></i> Todas as Conquistas</h2>
                <?php if (!empty($todas_conquistas_exibir)): ?>
                    <div class="grid-conquistas">
                        <?php foreach ($todas_conquistas_exibir as $conquista): ?>
                            <div class="card-conquista <?= $conquista['obtida'] ? 'obtida' : 'nao-obtida' ?>">
                                <div class="conquista-icon">
                                    <i class="fas fa-medal"></i>
                                </div>
                                <h3><?= htmlspecialchars($conquista['nome']) ?></h3>
                                <p class="descricao-conquista"><?= htmlspecialchars($conquista['descricao']) ?></p>
                                <p class="detalhes-conquista">
                                    <?php if ($conquista['obtida']): ?>
                                        <span class="data-desbloqueio">Desbloqueada em:
                                            <?= $conquista['data_obtencao'] ? date('d/m/Y', strtotime($conquista['data_obtencao'])) : 'N/A' ?></span><br>
                                    <?php else: ?>
                                        <span class="dica-desbloqueio">Desbloqueie para revelar a data!</span><br>
                                    <?php endif; ?>

                                    <?php if ($conquista['porcentagem_alunos'] !== 'N/A'): ?>
                                        <span class="porcentagem"><?= htmlspecialchars($conquista['porcentagem_alunos']) ?>% dos alunos
                                            possuem</span><br>
                                    <?php endif; ?>
                                    <span
                                        class="raridade raridade-<?= strtolower(str_replace(' ', '', $conquista['raridade'])) ?>"><?= htmlspecialchars($conquista['raridade']) ?></span>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>Nenhuma conquista configurada ainda. Volte mais tarde!</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p style="text-align: center; margin-top: 30px; font-size: 1.1em; color: #ADD8E6;">As conquistas são visíveis
                apenas para alunos.</p>
        <?php endif; ?>

        <a href="Perfil.php" class="btn-voltar">Voltar ao Perfil</a>
        <a href="Login.php" class="btn-sair">Sair da conta</a>
    </div>
</body>

</html>