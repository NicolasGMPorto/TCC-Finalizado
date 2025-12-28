<?php

function verificar_e_desbloquear_conquistas($conexao, $id_usuario, $criterio_tipo, $valor_adicional = null)
{
    $conquistas_desbloqueadas_nesta_chamada = [];

    $progresso_atual = 0; // Variável para armazenar o progresso atual do usuário para o critério

    // Avalia o tipo de critério para calcular o progresso atual do usuário
    switch ($criterio_tipo) {
        case 'dias_ativo':
            // Consulta a data de criação do usuário para calcular quantos dias ativo
            $query_data_criacao = "SELECT data_criacao FROM usuarios WHERE id_usuario = ?";
            $stmt_data_criacao = mysqli_prepare($conexao, $query_data_criacao);
            if ($stmt_data_criacao) {
                mysqli_stmt_bind_param($stmt_data_criacao, "i", $id_usuario);
                mysqli_stmt_execute($stmt_data_criacao);
                $result_data_criacao = mysqli_stmt_get_result($stmt_data_criacao);
                $row_data_criacao = mysqli_fetch_assoc($result_data_criacao);
                mysqli_stmt_close($stmt_data_criacao);

                if ($row_data_criacao && !empty($row_data_criacao['data_criacao'])) {
                    // Calcula a diferença em dias entre a data de criação e a data atual
                    $data_criacao_obj = new DateTime($row_data_criacao['data_criacao']);
                    $data_atual_obj = new DateTime();
                    $intervalo = $data_criacao_obj->diff($data_atual_obj);
                    $progresso_atual = $intervalo->days;
                }
            }
            break;

        case 'posts_criados':
            // Conta quantos posts o usuário criou
            $query = "SELECT COUNT(*) AS total FROM posts WHERE id_aluno = ?";
            $stmt = mysqli_prepare($conexao, $query);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "i", $id_usuario);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $row = mysqli_fetch_assoc($result);
                $progresso_atual = $row['total'];
                mysqli_stmt_close($stmt);
            }
            break;

        case 'uploads_feitos':
            // Conta quantos arquivos o usuário enviou
            $query = "SELECT COUNT(*) AS total FROM arquivos_usuario WHERE id_usuario = ?";
            $stmt = mysqli_prepare($conexao, $query);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "i", $id_usuario);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $row = mysqli_fetch_assoc($result);
                $progresso_atual = $row['total'];
                mysqli_stmt_close($stmt);
            }
            break;

        case 'questionarios_respondidos':
            // Conta quantos questionários distintos o usuário respondeu
            $query = "SELECT COUNT(DISTINCT id_questionario) AS total FROM resultados_questionarios WHERE id_usuario = ?";
            $stmt = mysqli_prepare($conexao, $query);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "i", $id_usuario);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $row = mysqli_fetch_assoc($result);
                $progresso_atual = $row['total'];
                mysqli_stmt_close($stmt);
            }
            break;

        case 'comentarios_feitos':
            // Conta quantos comentários o usuário fez
            $query = "SELECT COUNT(*) AS total FROM comentarios WHERE usuario_id = ?";
            $stmt = mysqli_prepare($conexao, $query);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "i", $id_usuario);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $row = mysqli_fetch_assoc($result);
                $progresso_atual = $row['total'];
                mysqli_stmt_close($stmt);
            }
            break;

        case 'questionarios_100_porcento':
            // Conta quantos questionários o usuário respondeu com pontuação máxima
            $query = "
                SELECT COUNT(*) AS total
                FROM resultados_questionarios rq
                JOIN questionarios q ON rq.id_questionario = q.id
                WHERE rq.id_usuario = ? AND rq.pontuacao = q.pontuacao_maxima;
            ";
            $stmt = mysqli_prepare($conexao, $query);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "i", $id_usuario);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $row = mysqli_fetch_assoc($result);
                $progresso_atual = $row['total'];
                mysqli_stmt_close($stmt);
            }
            break;

        case 'nivel_alterado':
            // Verifica se o nível do usuário corresponde ao valor adicional passado
            $query_nivel = "SELECT nivel FROM usuarios WHERE id_usuario = ?";
            $stmt_nivel = mysqli_prepare($conexao, $query_nivel);
            if ($stmt_nivel) {
                mysqli_stmt_bind_param($stmt_nivel, "i", $id_usuario);
                mysqli_stmt_execute($stmt_nivel);
                $result_nivel = mysqli_stmt_get_result($stmt_nivel);
                $row_nivel = mysqli_fetch_assoc($result_nivel);
                mysqli_stmt_close($stmt_nivel);
                if ($row_nivel && $row_nivel['nivel'] == $valor_adicional) {
                    $progresso_atual = 1; // Marca que o nível foi atingido
                }
            }
            break;

        case 'comentario_post_viral':
            // Recupera o número de curtidas do post para verificar se comentário em post viral foi feito
            if ($valor_adicional) {
                $query = "SELECT curtidas FROM posts WHERE id = ?";
                $stmt = mysqli_prepare($conexao, $query);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "i", $valor_adicional);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $row = mysqli_fetch_assoc($result);
                    mysqli_stmt_close($stmt);
                    $progresso_atual = $row ? $row['curtidas'] : 0;
                }
            }
            break;

        case 'post_viral':
            // Verifica se o post é viral (mais de 100 curtidas ou 500 visualizações)
            if ($valor_adicional) {
                $query = "SELECT curtidas, visualizacoes FROM posts WHERE id = ?";
                $stmt = mysqli_prepare($conexao, $query);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "i", $valor_adicional);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $row = mysqli_fetch_assoc($result);
                    mysqli_stmt_close($stmt);
                    if ($row && ($row['curtidas'] >= 100 || $row['visualizacoes'] >= 500)) {
                        $progresso_atual = 1; // Critério atingido
                    }
                }
            }
            break;

        case 'post_as_3h':
            // Verifica se o post foi criado exatamente às 3h da manhã
            if ($valor_adicional) {
                $query = "SELECT data_criacao FROM posts WHERE id = ?";
                $stmt = mysqli_prepare($conexao, $query);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "i", $valor_adicional);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $row = mysqli_fetch_assoc($result);
                    mysqli_stmt_close($stmt);
                    if ($row) {
                        $hora_criacao = date('H', strtotime($row['data_criacao']));
                        if ($hora_criacao == '03') {
                            $progresso_atual = 1; // Critério cumprido
                        }
                    }
                }
            }
            break;

        // Casos para critérios não implementados no momento são ignorados
        case 'dias_consecutivos_login':
        case 'primeiro_comentario_em_posts':
            break;
    }

    // Consulta as conquistas elegíveis para desbloqueio que ainda não foram conquistadas pelo usuário
    $query_conquistas_elegiveis = "
        SELECT cm.id_conquista, cm.criterio_valor, cm.nome, cm.raridade
        FROM conquistas_master cm
        LEFT JOIN usuario_conquistas uc ON cm.id_conquista = uc.id_conquista AND uc.id_usuario = ?
        WHERE cm.criterio_tipo = ? AND uc.id_conquista IS NULL;
    ";
    $stmt_elegiveis = mysqli_prepare($conexao, $query_conquistas_elegiveis);

    if (!$stmt_elegiveis) {
        error_log("Erro ao preparar consulta de conquistas elegíveis: " . mysqli_error($conexao));
        return $conquistas_desbloqueadas_nesta_chamada; // Retorna lista vazia em caso de erro
    }

    mysqli_stmt_bind_param($stmt_elegiveis, "is", $id_usuario, $criterio_tipo);
    mysqli_stmt_execute($stmt_elegiveis);
    $result_elegiveis = mysqli_stmt_get_result($stmt_elegiveis);

    // Para cada conquista elegível, verifica se o progresso do usuário é suficiente para desbloquear
    while ($conquista = mysqli_fetch_assoc($result_elegiveis)) {
        if ($progresso_atual >= $conquista['criterio_valor']) {
            // Insere a conquista na tabela de conquistas do usuário
            $query_desbloquear = "INSERT INTO usuario_conquistas (id_usuario, id_conquista) VALUES (?, ?)";
            $stmt_desbloquear = mysqli_prepare($conexao, $query_desbloquear);
            if ($stmt_desbloquear) {
                mysqli_stmt_bind_param($stmt_desbloquear, "ii", $id_usuario, $conquista['id_conquista']);
                if (mysqli_stmt_execute($stmt_desbloquear)) {
                    $conquistas_desbloqueadas_nesta_chamada[] = $conquista['nome']; // Armazena conquista desbloqueada
                    error_log("Conquista '{$conquista['nome']}' desbloqueada para o usuário {$id_usuario}.");
                } else {
                    // Ignora erro de duplicidade (usuário já possui a conquista)
                    if (mysqli_errno($conexao) != 1062) {
                        error_log("Erro ao desbloquear conquista '{$conquista['nome']}' para o usuário {$id_usuario}: " . mysqli_error($conexao));
                    }
                }
                mysqli_stmt_close($stmt_desbloquear);
            }
        }
    }
    mysqli_stmt_close($stmt_elegiveis);

    // Retorna o array com as conquistas que foram desbloqueadas nesta chamada da função
    return $conquistas_desbloqueadas_nesta_chamada;
}
?>