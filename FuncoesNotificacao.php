<?php
function inserirNotificacao($conexao, $id_usuario_destino, $mensagem)
{
    // Prepara a query para inserir uma nova notificação no banco
    $stmt = mysqli_prepare($conexao, "INSERT INTO notificacoes (id_usuario_destino, mensagem, data_criacao, lida) VALUES (?, ?, NOW(), 0)");
    if ($stmt) {
        // Associa os parâmetros (id do usuário destino e mensagem)
        mysqli_stmt_bind_param($stmt, "is", $id_usuario_destino, $mensagem);

        // Executa a query
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return true; // Inserção bem-sucedida
        } else {
            // Registra erro de execução da query no log de erros
            error_log("Erro ao executar inserção de notificação: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            return false; // Falha ao executar
        }
    } else {
        // Registra erro de preparação da query no log de erros
        error_log("Erro ao preparar a consulta de inserção de notificação: " . mysqli_error($conexao));
        return false; // Falha na preparação da query
    }
}
?>