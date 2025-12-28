<?php
include_once('Conexao.php'); // Importa a conexão com o banco
session_start(); // Inicia sessão para armazenar mensagens entre páginas

if (isset($_POST['enviar'])) { // Verifica se o formulário foi enviado

    // Recebe e armazena os dados do formulário
    $nome_usuario = $_POST['nome'];
    $email_usuario = $_POST['email'];
    $rm_usuario = $_POST['rm'];
    $etec_usuario = $_POST['etec'];
    $telefone_usuario = $_POST['telefone'];
    $senha_hash = password_hash($_POST['senha'], PASSWORD_DEFAULT); // Criptografa a senha

    // Valida o formato do e-mail
    if (!filter_var($email_usuario, FILTER_VALIDATE_EMAIL)) {
        die("E-mail institucional inválido!");
    }

    // Verifica se o RM já existe no banco de dados
    $stmt_check = mysqli_prepare($conexao, "SELECT rm_usuario FROM usuarios WHERE rm_usuario = ?");
    mysqli_stmt_bind_param($stmt_check, "s", $rm_usuario);
    mysqli_stmt_execute($stmt_check);
    mysqli_stmt_store_result($stmt_check);

    if (mysqli_stmt_num_rows($stmt_check) > 0) {
        die("RM já cadastrado!"); // Impede cadastro duplicado
    }

    // Insere novo usuário no banco de dados
    $stmt = mysqli_prepare($conexao, "INSERT INTO usuarios (nome_usuario, email_usuario, rm_usuario, etec_usuario, telefone_usuario, senha_usuario) VALUES (?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssssss", $nome_usuario, $email_usuario, $rm_usuario, $etec_usuario, $telefone_usuario, $senha_hash);
    mysqli_stmt_execute($stmt);

    // Verifica se o cadastro foi bem-sucedido
    if (mysqli_stmt_affected_rows($stmt) > 0) {
        $_SESSION['mensagem'] = "Cadastro realizado com sucesso! Faça login."; // Mensagem de sucesso
        header("Location: Login.php"); // Redireciona para a página de login
        exit();
    } else {
        echo "Erro ao cadastrar: " . mysqli_error($conexao); // Exibe erro caso falhe
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;600&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro</title>
    <link rel="stylesheet" href="styles/Cadastro.css">
</head>

<body>
    <header>
        <div class="LogoDiv">
            <img class="LogoEtec" src="imagens/Etec_Logo.webp">
        </div>
    </header>

    <div class="container">
        <form action="index.php" method="POST">
            <h2> Cadastro </h2>
            <div>
                <label for="nome" class="esconder">Nome</label>
                <input type="text" id="nome" name="nome" placeholder="Nome:" required>
            </div>

            <div>
                <label for="email" class="esconder">Email Institucional</label>
                <input type="email" id="email" name="email" placeholder="Email Institucional:" required>
            </div>

            <div>
                <label for="rm" class="esconder">RM</label>
                <input type="number" id="rm" name="rm" placeholder="RM:" min="10000" max="99999"
                    onkeydown="bloquearE(event)" required>
            </div>

            <div>
                <label for="etec" class="esconder">ID da ETEC</label>
                <input type="number" id="etec" name="etec" placeholder="ID da ETEC:" min="100" max="999"
                    onkeydown="bloquearE(event)" required>
            </div>

            <div>
                <label for="telefone" class="esconder">Telefone</label>
                <input type="tel" id="telefone" name="telefone" placeholder="Telefone:" required>
            </div>

            <div>
                <label for="senha" class="esconder">Senha</label>
                <div style="position: relative;">
                    <input type="password" id="senha" name="senha" placeholder="Senha:" required>
                    <span
                        style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer;"
                        onclick="toggleSenha()">👁️</span>
                </div>
            </div>
            <br>
            <center>
            <button class="btn" type="submit" name="enviar" id="enviar">Cadastrar</button><br><br>
            <a href="Login.php" class="btn-login">Já possui uma conta?</a></center>
        </form>
    </div>
    <script src="script/Cadastro.js"></script>

    <footer>
        &copy; <?= date('Y') ?>, Eduardo Enrique Casimiro Silva e Nicolas Gabriel Morales Porto.
    </footer>
</body>

</html>