<?php
include_once('Conexao.php'); // Conecta ao banco de dados
session_start(); // Inicia a sessão para controle de acesso

// Verifica se o usuário é um professor (nível 3). Se não for, bloqueia o acesso.
if ($_SESSION['nivel'] != 3) {
    $_SESSION['erro'] = "Acesso negado!";
    header("Location: MenuPrincipal.php");
    exit();
}

// Verifica se o formulário foi enviado via POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Escapa caracteres especiais para evitar injeção de SQL
    $titulo = mysqli_real_escape_string($conexao, $_POST['titulo']);
    $descricao = mysqli_real_escape_string($conexao, $_POST['descricao']);

    // Verifica se uma imagem foi enviada
    if (empty($_FILES['imagem']['name'])) {
        $_SESSION['erro'] = "Nenhuma imagem enviada!";
        header("Location: MenuPrincipal.php");
        exit();
    }

    // Diretório onde a imagem será salva
    $upload_dir = 'uploads/postagens/';
    // Tipos de arquivos permitidos
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // Tamanho máximo: 5MB

    // Verifica o tipo MIME real do arquivo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES['imagem']['tmp_name']);
    finfo_close($finfo);

    // Verifica se o tipo MIME é permitido
    if (!in_array($mime, $allowed_types)) {
        $_SESSION['erro'] = "Apenas imagens JPEG, PNG e WEBP são permitidas!";
        header("Location: MenuPrincipal.php");
        exit();
    }

    // Verifica o tamanho do arquivo
    if ($_FILES['imagem']['size'] > $max_size) {
        $_SESSION['erro'] = "Arquivo muito grande (máx. 5MB)!";
        header("Location: MenuPrincipal.php");
        exit();
    }

    // Determina a extensão com base no tipo MIME
    $ext = '';
    switch ($mime) {
        case 'image/jpeg':
            $ext = 'jpg';
            break;
        case 'image/png':
            $ext = 'png';
            break;
        case 'image/webp':
            $ext = 'webp';
            break;
        default:
            $_SESSION['erro'] = "Tipo de arquivo inválido após validação MIME.";
            header("Location: MenuPrincipal.php");
            exit();
    }

    // Gera um nome único para o arquivo
    $nome_arquivo = uniqid() . '.' . $ext;

    // Move o arquivo enviado para o diretório de destino
    if (move_uploaded_file($_FILES['imagem']['tmp_name'], $upload_dir . $nome_arquivo)) {
        // Insere a nova postagem no banco de dados
        $query = "INSERT INTO postagens (titulo, descricao, imagem, criador_id, data_criacao) 
                  VALUES (?, ?, ?, ?, NOW())";
        $stmt = mysqli_prepare($conexao, $query);
        mysqli_stmt_bind_param(
            $stmt,
            "sssi",
            $titulo,
            $descricao,
            $nome_arquivo,
            $_SESSION['usuario']
        );

        // Executa a query e trata o sucesso ou erro
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['sucesso'] = "Postagem criada com sucesso!";
        } else {
            unlink($upload_dir . $nome_arquivo); // Remove a imagem se falhar a gravação
            $_SESSION['erro'] = "Erro ao salvar no banco de dados: " . mysqli_error($conexao);
        }
    } else {
        $_SESSION['erro'] = "Erro ao enviar a imagem.";
    }

    // Redireciona de volta para o menu principal
    header("Location: MenuPrincipal.php");
    exit();
}
?>