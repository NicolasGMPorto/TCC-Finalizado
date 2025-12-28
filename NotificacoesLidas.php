<?php
include_once('Conexao.php');
session_start();

header('Content-Type: application/json'); // Define o tipo de conteúdo da resposta como JSON

// Verifica se o usuário está logado; se não, retorna erro em JSON e encerra
if (empty($_SESSION['usuario'])) {
    echo json_encode(['status' => 'error', 'message' => 'Usuário não logado.']);
    exit();
}

$id_usuario = (int) $_SESSION['usuario'];

// Verifica se a conexão com o banco está disponível e válida
if (!isset($conexao) || !$conexao || mysqli_ping($conexao) === false) {
    echo json_encode(['status' => 'error', 'message' => 'Erro: Banco de dados indisponível ou conexão inválida.']);
    exit();
}

// Query para marcar todas as notificações não lidas desse usuário como lidas
$query = "UPDATE notificacoes SET lida = 1 WHERE id_usuario_destino = ? AND lida = 0";
$stmt = mysqli_prepare($conexao, $query);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $id_usuario);
    if (mysqli_stmt_execute($stmt)) {
        // Sucesso: retorna status success em JSON
        echo json_encode(['status' => 'success', 'message' => 'Notificações marcadas como lidas.']);
    } else {
        // Erro ao executar a query; loga o erro e retorna mensagem genérica
        error_log("Erro ao marcar notificações como lidas: " . mysqli_error($conexao));
        echo json_encode(['status' => 'error', 'message' => 'Erro ao marcar notificações como lidas.']);
    }
    mysqli_stmt_close($stmt);
} else {
    // Erro ao preparar a query; loga e retorna erro interno
    error_log("Erro ao preparar a query para marcar notificações como lidas: " . mysqli_error($conexao));
    echo json_encode(['status' => 'error', 'message' => 'Erro interno do servidor.']);
}

mysqli_close($conexao); // Fecha conexão com banco
?>