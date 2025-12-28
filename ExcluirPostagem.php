<?php
include_once('Conexao.php'); // Inclui o arquivo de conexão com o banco de dados
session_start(); // Inicia a sessão para controle de usuário

// Verifica se o usuário tem nível 3 (professor), caso contrário bloqueia acesso
if ($_SESSION['nivel'] != 3) {
    $_SESSION['erro'] = "Acesso negado!";
    header("Location: MenuPrincipal.php");
    exit();
}

// Verifica se o parâmetro 'id' foi passado na URL e se é numérico
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID inválido!"); // Termina o script caso o ID seja inválido
}
$id = (int) $_GET['id']; // Converte o ID para inteiro para segurança

// Busca apenas a coluna 'imagem' da postagem com o ID especificado
$query = "SELECT imagem FROM postagens WHERE id = ?";
$stmt = mysqli_prepare($conexao, $query);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Caso não encontre a postagem, informa erro e redireciona
if (mysqli_num_rows($result) == 0) {
    $_SESSION['erro'] = "Postagem não encontrada!";
    header("Location: MenuPrincipal.php");
    exit();
}

$post = mysqli_fetch_assoc($result); // Obtém os dados da postagem

try {
    // Se existir uma imagem associada e o arquivo existir no servidor, apaga o arquivo físico
    if (!empty($post['imagem']) && file_exists("uploads/postagens/" . $post['imagem'])) {
        unlink("uploads/postagens" . $post['imagem']); // Remove arquivo da imagem
    }

    // Prepara comando para deletar a postagem do banco de dados
    $delete_query = "DELETE FROM postagens WHERE id = ?";
    $stmt = mysqli_prepare($conexao, $delete_query);
    mysqli_stmt_bind_param($stmt, "i", $id);

    // Executa a exclusão e seta mensagem de sucesso ou lança exceção em caso de erro
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['sucesso'] = "Postagem excluída com sucesso!";
    } else {
        throw new Exception("Erro ao excluir do banco de dados");
    }
} catch (Exception $e) {
    // Captura erros e armazena mensagem na sessão para exibir ao usuário
    $_SESSION['erro'] = "Erro: " . $e->getMessage();
}

// Redireciona para a página principal após o processo
header("Location: MenuPrincipal.php");
exit();
?>