<?php
include_once('Conexao.php');
session_start();

// Definições dos níveis de usuário para facilitar a leitura do código
define('ALUNO', 1);
define('ADMIN', 2);
define('PROFESSOR', 3);

// Verifica se usuário está logado, senão redireciona para login
if (empty($_SESSION['usuario'])) {
    header("Location: Login.php");
    exit();
}

// Verifica se a conexão com o banco está disponível e válida
if (!isset($conexao) || !$conexao || mysqli_ping($conexao) === false) {
    die("Erro: Banco de dados indisponível ou conexão inválida.");
}

$id_usuario = (int) $_SESSION['usuario'];

// Consulta para obter dados básicos do usuário logado
$query = "SELECT
            nome_usuario,
            email_usuario,
            rm_usuario,
            etec_usuario,
            telefone_usuario,
            nivel,
            foto_perfil,
            data_criacao
          FROM usuarios
          WHERE id_usuario = ?
          LIMIT 1";

$stmt = mysqli_prepare($conexao, $query);

// Caso falhe a preparação da query, encerra exibindo erro
if (!$stmt) {
    die("Erro ao preparar a consulta SQL: " . mysqli_error($conexao));
}

// Associa o parâmetro, executa e obtém o resultado
mysqli_stmt_bind_param($stmt, "i", $id_usuario);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Verifica se encontrou o usuário, senão encerra com erro
if (mysqli_num_rows($result) === 0) {
    die("Erro: Usuário não encontrado no sistema.");
}

// Obtém os dados do usuário
$usuario = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Calcula quantos dias se passaram desde a criação da conta do usuário
$dias_desde_criacao = 0;
if (!empty($usuario['data_criacao'])) {
    try {
        $data_criacao_obj = new DateTime($usuario['data_criacao']);
        $data_atual_obj = new DateTime();
        $intervalo = $data_criacao_obj->diff($data_atual_obj);
        $dias_desde_criacao = $intervalo->days;
    } catch (Exception $e) {
        // Em caso de erro, loga e define 0 dias
        error_log("Erro ao calcular dias desde a criação da conta para o usuário " . $id_usuario . ": " . $e->getMessage());
        $dias_desde_criacao = 0;
    }
}

// Função para exibir dados, retornando um placeholder caso esteja vazio ou nulo
function exibirDado($dado, $placeholder = 'Não informado')
{
    if (is_null($dado) || $dado === '') {
        return $placeholder;
    }
    return htmlspecialchars($dado, ENT_QUOTES, 'UTF-8');
}

$notificacoes_recentes = [];
$total_notificacoes = 0;

// Busca notificações recentes e total de notificações caso o usuário tenha níveis específicos
if ($usuario['nivel'] == ALUNO || $usuario['nivel'] == PROFESSOR || $usuario['nivel'] == ADMIN) {
    // Consulta total de notificações para o usuário
    $query_total_notificacoes = "SELECT COUNT(*) AS total FROM notificacoes WHERE id_usuario_destino = ?";
    $stmt_total_notificacoes = mysqli_prepare($conexao, $query_total_notificacoes);
    if ($stmt_total_notificacoes) {
        mysqli_stmt_bind_param($stmt_total_notificacoes, "i", $id_usuario);
        mysqli_stmt_execute($stmt_total_notificacoes);
        $result_total_notificacoes = mysqli_stmt_get_result($stmt_total_notificacoes);
        $row_total = mysqli_fetch_assoc($result_total_notificacoes);
        $total_notificacoes = (int) $row_total['total'];
        mysqli_stmt_close($stmt_total_notificacoes);
    } else {
        error_log("Erro ao preparar consulta de total de notificações: " . mysqli_error($conexao));
    }

    // Consulta as 3 notificações mais recentes para exibição no perfil
    $query_notificacoes_recentes_db = "SELECT id, mensagem, data_criacao FROM notificacoes WHERE id_usuario_destino = ? ORDER BY data_criacao DESC LIMIT 3";
    $stmt_notificacoes_recentes_db = mysqli_prepare($conexao, $query_notificacoes_recentes_db);

    if ($stmt_notificacoes_recentes_db) {
        mysqli_stmt_bind_param($stmt_notificacoes_recentes_db, "i", $id_usuario);
        mysqli_stmt_execute($stmt_notificacoes_recentes_db);
        $result_notificacoes_recentes_db = mysqli_stmt_get_result($stmt_notificacoes_recentes_db);

        while ($row = mysqli_fetch_assoc($result_notificacoes_recentes_db)) {
            $notificacoes_recentes[] = [
                'id' => $row['id'],
                'mensagem' => $row['mensagem'],
                'data' => $row['data_criacao']
            ];
        }
        mysqli_stmt_close($stmt_notificacoes_recentes_db);
    } else {
        error_log("Erro ao preparar a consulta de notificações recentes do DB: " . mysqli_error($conexao));
    }
}

$conquistas_recentes = [];
$total_conquistas_usuario = 0;

// Caso o usuário seja ALUNO, busca conquistas e o total de alunos para calcular porcentagens
if ($usuario['nivel'] == ALUNO) {
    $total_alunos = 0;
    $query_total_alunos = "SELECT COUNT(*) AS total FROM usuarios WHERE nivel = ?";
    $stmt_total_alunos = mysqli_prepare($conexao, $query_total_alunos);
    if ($stmt_total_alunos) {
        $nivel_aluno_param_total = ALUNO;
        mysqli_stmt_bind_param($stmt_total_alunos, "i", $nivel_aluno_param_total);
        mysqli_stmt_execute($stmt_total_alunos);
        $result_total_alunos = mysqli_stmt_get_result($stmt_total_alunos);
        $row_total_alunos = mysqli_fetch_assoc($result_total_alunos);
        $total_alunos = (int) $row_total_alunos['total'];
        mysqli_stmt_close($stmt_total_alunos);
    } else {
        error_log("Erro ao preparar consulta para total de alunos: " . mysqli_error($conexao));
    }

    // Busca as conquistas do usuário, incluindo o total de alunos que possuem cada conquista para cálculo de raridade
    $query_conquistas = "
        SELECT
            cm.nome,
            cm.descricao,
            cm.raridade,
            (SELECT COUNT(DISTINCT uc2.id_usuario) 
             FROM usuario_conquistas uc2 
             JOIN usuarios u2 ON uc2.id_usuario = u2.id_usuario 
             WHERE uc2.id_conquista = uc.id_conquista AND u2.nivel = ?) AS total_usuarios_com_conquista,
            uc.data_conquista
        FROM
            usuario_conquistas uc
        JOIN
            conquistas_master cm ON uc.id_conquista = cm.id_conquista
        WHERE
            uc.id_usuario = ?
        ORDER BY
            uc.data_conquista DESC
    ";

    $stmt_conquistas = mysqli_prepare($conexao, $query_conquistas);

    if ($stmt_conquistas) {
        $nivel_aluno_subquery = ALUNO;
        mysqli_stmt_bind_param($stmt_conquistas, "ii", $nivel_aluno_subquery, $id_usuario);

        mysqli_stmt_execute($stmt_conquistas);
        $result_conquistas = mysqli_stmt_get_result($stmt_conquistas);

        $all_conquistas_raw = [];
        while ($row = mysqli_fetch_assoc($result_conquistas)) {
            $all_conquistas_raw[] = $row;
        }
        mysqli_stmt_close($stmt_conquistas);

        $total_conquistas_usuario = count($all_conquistas_raw);

        // Limita a exibição a 4 conquistas recentes no perfil
        $conquistas_para_exibir = array_slice($all_conquistas_raw, 0, 4);

        // Calcula a porcentagem de alunos que possuem cada conquista para exibição
        foreach ($conquistas_para_exibir as $conquista) {
            $porcentagem = 0;
            if ($total_alunos > 0) {
                $porcentagem = min(100, round(($conquista['total_usuarios_com_conquista'] / $total_alunos) * 100, 1));
            }

            $conquistas_recentes[] = [
                'nome' => $conquista['nome'],
                'descricao' => $conquista['descricao'],
                'porcentagem' => $porcentagem,
                'raridade' => $conquista['raridade'],
                'data_conquista' => $conquista['data_conquista']
            ];
        }
    } else {
        error_log("Erro ao preparar a consulta de conquistas: " . mysqli_error($conexao));
    }
}

// Define a foto de perfil padrão e atualiza se o usuário tiver uma personalizada válida
$foto_perfil_src = 'imagens/FotoPerfilGen.jpg';
if (!empty($usuario['foto_perfil'])) {
    $temp_foto_path = 'uploads/perfis/' . htmlspecialchars($usuario['foto_perfil']);
    if (file_exists($temp_foto_path)) {
        $foto_perfil_src = $temp_foto_path;
    }
}

mysqli_close($conexao);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil do Usuário - <?= exibirDado($usuario['nome_usuario']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles/Perfil.css">
    <link rel="icon" href="imagens/Logo_Neez.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body class="<?= basename($_SERVER['PHP_SELF']) == 'Perfil.php' ? 'perfil-page' : '' ?>">
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

    <div class="container perfil-container">
        <div class="cabecalho-perfil">
            <img class="foto-perfil" src="<?= $foto_perfil_src ?>" alt="Foto de Perfil">
            <h1 class="nome-usuario"><?= exibirDado($usuario['nome_usuario']) ?></h1>
            <span class="nivel-usuario">
                <?= match ((int) $usuario['nivel']) {
                    ALUNO => 'Aluno',
                    ADMIN => 'Administrador',
                    PROFESSOR => 'Professor',
                    default => 'Indefinido'
                } ?>
            </span>
        </div>

        <div class="dados-basicos">
            <div class="dado-item">
                <strong>RM: &nbsp</strong> <span><?= exibirDado($usuario['rm_usuario']) ?></span>
            </div>
            <div class="dado-item">
                <strong>ETEC: &nbsp</strong> <span><?= exibirDado($usuario['etec_usuario']) ?></span>
            </div>
            <div class="dado-item">
                <strong>Email: &nbsp</strong> <span><?= exibirDado($usuario['email_usuario']) ?></span>
            </div>
            <div class="dado-item">
                <strong>Telefone: &nbsp</strong> <span><?= exibirDado($usuario['telefone_usuario']) ?></span>
            </div>

            <?php if (isset($dias_desde_criacao)): ?>
                <div class="dado-item">
                    <strong>Tempo de conta: &nbsp</strong>
                    <span>
                        <?php if ($dias_desde_criacao === 0): ?>
                            &nbsp; Hoje! Seja bem-vindo(a)!
                        <?php elseif ($dias_desde_criacao === 1): ?>
                            Há 1 dia
                        <?php else: ?>
                            Há <?= htmlspecialchars($dias_desde_criacao) ?> dias
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($usuario['nivel'] == ALUNO || $usuario['nivel'] == PROFESSOR || $usuario['nivel'] == ADMIN): ?>
            <div class="secao-notificacoes">
                <h2><i class="fas fa-bell"></i> Notificações Recentes</h2>
                <?php if (!empty($notificacoes_recentes)): ?>
                    <ul>
                        <?php foreach ($notificacoes_recentes as $notificacao): ?>
                            <li>
                                <span class="data-notificacao"><?= date('d/m/Y', strtotime($notificacao['data'])) ?></span> -
                                <?= htmlspecialchars($notificacao['mensagem']) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>Nenhuma notificação recente.</p>
                <?php endif; ?>

                <button class="btn-mostrar-mais" onclick="window.location.href='Notificacoes.php'">Mostrar todas as
                    notificações (<?= $total_notificacoes ?>)</button>

            </div>
        <?php endif; ?>

        <?php if ($usuario['nivel'] == ALUNO): ?>
            <div class="secao-conquistas">
                <h2><i class="fas fa-trophy"></i> Minhas Conquistas Recentes</h2>
                <?php if (!empty($conquistas_recentes)): ?>
                    <div class="grid-conquistas">
                        <?php foreach ($conquistas_recentes as $conquista): ?>
                            <div class="card-conquista">
                                <h3><?= htmlspecialchars($conquista['nome']) ?></h3>
                                <p class="descricao-conquista"><?= htmlspecialchars($conquista['descricao']) ?></p>
                                <p class="detalhes-conquista">
                                    <span class="data-desbloqueio">Desbloqueada em:
                                        <?= date('d/m/Y', strtotime($conquista['data_conquista'])) ?></span><br>
                                    <span class="porcentagem"><?= htmlspecialchars($conquista['porcentagem']) ?>% dos alunos
                                        possuem</span><br>
                                    <span
                                        class="raridade raridade-<?= strtolower(str_replace(' ', '', $conquista['raridade'])) ?>"><?= htmlspecialchars($conquista['raridade']) ?></span>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>Nenhuma conquista desbloqueada ainda.</p>
                <?php endif; ?>
                <button class="btn-mostrar-mais" onclick="window.location.href='Conquistas.php'">
                    Ver todas as conquistas (<?= $total_conquistas_usuario ?> desbloqueadas)
                </button>
            </div>
        <?php endif; ?>

        <a href="EditarPerfil.php" class="btn-editar">Editar Perfil</a>
        <a href="Login.php" class="btn-sair">Sair da conta</a>
    </div>

</body>

</html>