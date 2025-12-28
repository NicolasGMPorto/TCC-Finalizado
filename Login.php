<?php
include_once('Conexao.php');
include_once('FuncoesNotificacao.php');
include_once('FuncoesConquistas.php');
session_start();

$erro = "";

if (isset($_POST['login'])) {
    $rm_usuario = $_POST['rm']; // RM enviado pelo usuário
    $senha_digitada = $_POST['senha']; // Senha digitada pelo usuário

    // Prepara a consulta para buscar usuário pelo RM
    $stmt = mysqli_prepare($conexao, "SELECT id_usuario, senha_usuario, nivel FROM usuarios WHERE rm_usuario = ?");
    mysqli_stmt_bind_param($stmt, "s", $rm_usuario);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    // Verifica se o usuário foi encontrado
    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);

        // Verifica se a senha digitada corresponde ao hash armazenado
        if (password_verify($senha_digitada, $user['senha_usuario'])) {
            // Define variáveis de sessão para manter usuário logado
            $_SESSION['usuario'] = $user['id_usuario'];
            $_SESSION['nivel'] = $user['nivel'];

            $id_usuario_logado = $user['id_usuario'];

            // Atualiza data do último login para o usuário
            $query_update_login = "UPDATE usuarios SET data_ultimo_login = NOW() WHERE id_usuario = ?";
            $stmt_update_login = mysqli_prepare($conexao, $query_update_login);
            if ($stmt_update_login) {
                mysqli_stmt_bind_param($stmt_update_login, "i", $id_usuario_logado);
                mysqli_stmt_execute($stmt_update_login);
                mysqli_stmt_close($stmt_update_login);
            }

            // Verifica se o usuário desbloqueou conquistas relacionadas a dias ativos
            $conquistas_ganhas = verificar_e_desbloquear_conquistas($conexao, $id_usuario_logado, 'dias_ativo');

            // Se houver conquistas ganhas, armazena na sessão para mostrar ao usuário
            if (!empty($conquistas_ganhas)) {
                $_SESSION['novas_conquistas'] = $conquistas_ganhas;
            }

            // Redireciona para página principal após login bem-sucedido
            header("Location: MenuPrincipal.php");
            exit();
        } else {
            $erro = "Senha incorreta"; // Senha não confere
        }
    } else {
        $erro = "RM não cadastrado"; // Usuário não encontrado pelo RM
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;600&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="styles/Login.css">
</head>

<body>
<br><br><br><br><br><br><br>
    <header>
        <div class="LogoDiv">
            <img class="LogoEtec" src="imagens/Etec_Logo.webp" alt="Logo ETEC">
        </div>
    </header>

    <div class="container">
        <form action="Login.php" method="POST">
            <h2>Login</h2>

            <div>
                <label for="rm" class="esconder">RM</label>
                <input type="number" id="rm" name="rm" placeholder="RM:" min="10000" max="99999" required
                    onkeydown="event.key !== 'e'">
            </div>

            <div>
                <label for="senha" class="esconder">Senha</label>
                <div style="position: relative;">
                    <input type="password" id="senha" name="senha" placeholder="Senha:" required>
                    <span
                        style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer;"
                        onclick="document.getElementById('senha').type = document.getElementById('senha').type === 'password' ? 'text' : 'password'">
                        👁️
                    </span>
                </div>
            </div>

            <br><center>
            <button class="btn" type="submit" name="login">Entrar</button><br><br>
            <a href="index.php" class="btn-cadastro">Não possui conta?</a></center>

            <?php if ($erro): ?>
                <p class="erro"><?php echo htmlspecialchars($erro); ?></p>
            <?php endif; ?>
        </form>
    </div>

    <footer>
        &copy; <?= date('Y') ?>, Eduardo Enrique Casimiro Silva e Nicolas Gabriel Morales Porto.
    </footer>

</body>

</html>
