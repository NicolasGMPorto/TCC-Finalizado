-- --------------------------------------------------------
-- Servidor:                     127.0.0.1
-- Versão do servidor:           8.0.30 - MySQL Community Server - GPL
-- OS do Servidor:               Win64
-- HeidiSQL Versão:              12.1.0.6537
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Copiando estrutura do banco de dados para tcc
CREATE DATABASE IF NOT EXISTS `tcc` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `tcc`;

-- Copiando estrutura para tabela tcc.arquivos_usuario
CREATE TABLE IF NOT EXISTS `arquivos_usuario` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `nome_original` varchar(255) NOT NULL,
  `nome_salvo` varchar(255) NOT NULL,
  `tipo_mime` varchar(100) NOT NULL,
  `tamanho_kb` int NOT NULL,
  `caminho_arquivo` varchar(255) NOT NULL,
  `data_upload` datetime DEFAULT CURRENT_TIMESTAMP,
  `id_materia` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_usuario` (`id_usuario`),
  KEY `id_materia` (`id_materia`),
  CONSTRAINT `arquivos_usuario_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `arquivos_usuario_ibfk_2` FOREIGN KEY (`id_materia`) REFERENCES `materias` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela tcc.arquivos_usuario: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela tcc.comentarios
CREATE TABLE IF NOT EXISTS `comentarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `post_id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `conteudo` text NOT NULL,
  `data_criacao` datetime DEFAULT CURRENT_TIMESTAMP,
  `data_ultima_edicao` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `post_id` (`post_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `comentarios_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comentarios_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela tcc.comentarios: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela tcc.conquistas_master
CREATE TABLE IF NOT EXISTS `conquistas_master` (
  `id_conquista` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `descricao` text NOT NULL,
  `criterio_tipo` varchar(50) NOT NULL,
  `criterio_valor` varchar(50) DEFAULT NULL,
  `raridade` varchar(20) NOT NULL,
  `icone_url` varchar(255) DEFAULT NULL,
  `data_criacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_conquista`)
) ENGINE=InnoDB AUTO_INCREMENT=204 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela tcc.conquistas_master: ~37 rows (aproximadamente)
INSERT INTO `conquistas_master` (`id_conquista`, `nome`, `descricao`, `criterio_tipo`, `criterio_valor`, `raridade`, `icone_url`, `data_criacao`) VALUES
	(1, 'Primeiro Passo', 'Crie sua conta no nosso site.', 'dias_ativo', '0', 'Comum', NULL, '2025-07-13 20:45:57'),
	(2, 'Um Mês de Jornada', 'Conta ativa há 30 dias.', 'dias_ativo', '30', 'Comum', NULL, '2025-07-13 20:45:57'),
	(3, 'Cem Dias de Progresso', 'Conta ativa há 100 dias.', 'dias_ativo', '100', 'Incomum', NULL, '2025-07-13 20:45:57'),
	(4, 'Veterano de 1º Ano', 'Conta ativa há 1 ano.', 'dias_ativo', '365', 'Rara', NULL, '2025-07-13 20:45:57'),
	(5, 'Trienal Consciente', 'Conta ativa há 3 anos.', 'dias_ativo', '1095', 'Épica', NULL, '2025-07-13 20:45:57'),
	(6, 'Lenda da Comunidade', 'Conta ativa há 5 anos.', 'dias_ativo', '1825', 'Expert', NULL, '2025-07-13 20:45:57'),
	(7, 'Estreante Literário', 'Crie seu primeiro post.', 'posts_criados', '1', 'Comum', NULL, '2025-07-13 20:45:57'),
	(8, 'Caneta Ativa', 'Crie 5 posts.', 'posts_criados', '5', 'Comum', NULL, '2025-07-13 20:45:57'),
	(9, 'Autor Produtivo', 'Crie 25 posts.', 'posts_criados', '25', 'Incomum', NULL, '2025-07-13 20:45:57'),
	(10, 'Narrador Oficial', 'Crie 50 posts.', 'posts_criados', '50', 'Rara', NULL, '2025-07-13 20:45:57'),
	(11, 'Escritor Prolífico', 'Crie 100 posts.', 'posts_criados', '100', 'Épica', NULL, '2025-07-13 20:45:57'),
	(12, 'Mestre dos Tópicos', 'Crie 250 posts.', 'posts_criados', '250', 'Expert', NULL, '2025-07-13 20:45:57'),
	(13, 'Contribuidor Iniciante', 'Faça seu primeiro upload.', 'uploads_feitos', '1', 'Comum', NULL, '2025-07-13 20:45:57'),
	(14, 'Carregando Conhecimento', 'Faça 20 uploads.', 'uploads_feitos', '20', 'Incomum', NULL, '2025-07-13 20:45:57'),
	(15, 'Servidor de Saberes', 'Faça 50 uploads.', 'uploads_feitos', '50', 'Rara', NULL, '2025-07-13 20:45:57'),
	(16, 'Curador Digital', 'Faça 100 uploads.', 'uploads_feitos', '100', 'Rara', NULL, '2025-07-13 20:45:57'),
	(17, 'Arquiteto de Arquivos', 'Faça 200 uploads.', 'uploads_feitos', '200', 'Épica', NULL, '2025-07-13 20:45:57'),
	(18, 'Guardião do Conteúdo', 'Faça 300 uploads.', 'uploads_feitos', '300', 'Expert', NULL, '2025-07-13 20:45:57'),
	(19, 'Testando as Águas', 'Responda seu primeiro questionário.', 'questionarios_respondidos', '1', 'Comum', NULL, '2025-07-13 20:45:57'),
	(20, 'Aprendiz Curioso', 'Responda 5 questionários.', 'questionarios_respondidos', '5', 'Comum', NULL, '2025-07-13 20:45:57'),
	(21, 'Desafiador Frequente', 'Responda 10 questionários.', 'questionarios_respondidos', '10', 'Incomum', NULL, '2025-07-13 20:45:57'),
	(22, 'Explorador de Conhecimento', 'Responda 25 questionários.', 'questionarios_respondidos', '25', 'Rara', NULL, '2025-07-13 20:45:57'),
	(23, 'Questionador Nato', 'Responda 50 questionários.', 'questionarios_respondidos', '50', 'Épica', NULL, '2025-07-13 20:45:57'),
	(24, 'Mestre dos Desafios', 'Responda 100 questionários.', 'questionarios_respondidos', '100', 'Expert', NULL, '2025-07-13 20:45:57'),
	(25, 'Primeira Voz', 'Faça seu primeiro comentário.', 'comentarios_feitos', '1', 'Comum', NULL, '2025-07-13 20:45:57'),
	(26, 'Participante Ativo', 'Faça 20 comentários.', 'comentarios_feitos', '20', 'Comum', NULL, '2025-07-13 20:45:57'),
	(27, 'Comentador Engajado', 'Faça 50 comentários.', 'comentarios_feitos', '50', 'Incomum', NULL, '2025-07-13 20:45:57'),
	(28, 'Influenciador de Ideias', 'Faça 100 comentários.', 'comentarios_feitos', '100', 'Rara', NULL, '2025-07-13 20:45:57'),
	(29, 'Coluna da Comunidade', 'Faça 250 comentários.', 'comentarios_feitos', '250', 'Épica', NULL, '2025-07-13 20:45:57'),
	(30, 'Voz da Sabedoria', 'Faça 500 comentários.', 'comentarios_feitos', '500', 'Expert', NULL, '2025-07-13 20:45:57'),
	(31, 'Do Salão de Aula ao Trono', 'Seja promovido de aluno para ADM.', 'nivel_alterado', '2', 'Lendária', NULL, '2025-07-13 20:45:57'),
	(32, 'Fiel à Missão', 'Acesse o site todos os dias por um mês consecutivo.', 'dias_consecutivos_login', '30', 'Rara', NULL, '2025-07-13 20:45:57'),
	(33, 'Comentário Dourado', 'Comente em um post com mais de 100 curtidas.', 'comentario_post_viral', '100', 'Épica', NULL, '2025-07-13 20:45:57'),
	(34, 'Autor Iluminado', 'Crie um post que viralize (ex: 100 curtidas ou 500 visualizações).', 'post_viral', '1', 'Épica', NULL, '2025-07-13 20:45:57'),
	(35, '100 de 100, perfeito', 'Responda 100 questionários com 100% de acerto.', 'questionarios_100_porcento', '100', 'Lendária', NULL, '2025-07-13 20:45:57'),
	(36, 'Sempre à Frente', 'Seja o primeiro a comentar em 10 posts diferentes.', 'primeiro_comentario_em_posts', '10', 'Incomum', NULL, '2025-07-13 20:45:57'),
	(37, 'Coruja do Conhecimento', 'Poste algo às 3h da manhã.', 'post_as_3h', '1', 'Rara', NULL, '2025-07-13 20:45:57');

-- Copiando estrutura para tabela tcc.desempenho_questionario
CREATE TABLE IF NOT EXISTS `desempenho_questionario` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_aluno` int NOT NULL,
  `id_questionario` int NOT NULL,
  `pontuacao_total` int NOT NULL,
  `total_perguntas` int NOT NULL,
  `porcentagem_acertos` decimal(5,2) NOT NULL,
  `data_conclusao` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_aluno` (`id_aluno`,`id_questionario`),
  KEY `id_questionario` (`id_questionario`),
  CONSTRAINT `desempenho_questionario_ibfk_1` FOREIGN KEY (`id_aluno`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `desempenho_questionario_ibfk_2` FOREIGN KEY (`id_questionario`) REFERENCES `questionarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela tcc.desempenho_questionario: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela tcc.materias
CREATE TABLE IF NOT EXISTS `materias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela tcc.materias: ~15 rows (aproximadamente)
INSERT INTO `materias` (`id`, `nome`) VALUES
	(8, 'Arte'),
	(11, 'Biologia'),
	(5, 'Ciências'),
	(7, 'Educação Física'),
	(12, 'Filosofia'),
	(10, 'Física'),
	(4, 'Geografia'),
	(3, 'História'),
	(14, 'Informática'),
	(6, 'Inglês'),
	(2, 'Matemática'),
	(15, 'Outros'),
	(1, 'Português'),
	(9, 'Química'),
	(13, 'Sociologia');

-- Copiando estrutura para tabela tcc.notificacoes
CREATE TABLE IF NOT EXISTS `notificacoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_usuario_destino` int NOT NULL,
  `mensagem` varchar(255) NOT NULL,
  `data_criacao` datetime DEFAULT CURRENT_TIMESTAMP,
  `lida` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `id_usuario_destino` (`id_usuario_destino`),
  CONSTRAINT `notificacoes_ibfk_1` FOREIGN KEY (`id_usuario_destino`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela tcc.notificacoes: ~18 rows (aproximadamente)
INSERT INTO `notificacoes` (`id`, `id_usuario_destino`, `mensagem`, `data_criacao`, `lida`) VALUES
	(44, 22, 'Sua postagem \'1+1\' foi aprovada e está visível para a comunidade!', '2025-09-29 18:34:33', 1),
	(45, 22, 'Parabéns! Você desbloqueou a conquista: "Estreante Literário".', '2025-11-20 13:50:20', 1),
	(46, 22, 'Sua postagem \'1+1\' foi aprovada e está visível para a comunidade!', '2025-11-20 13:50:32', 1),
	(47, 22, 'Alguém curtiu sua postagem: "1+1".', '2025-11-20 13:51:51', 1),
	(48, 22, 'Alguém curtiu sua postagem: "1+1".', '2025-11-20 13:51:52', 1),
	(49, 22, 'Alguém curtiu sua postagem: "1+1".', '2025-11-20 13:52:26', 1),
	(50, 22, 'Alguém curtiu sua postagem: "1+1".', '2025-11-20 13:52:27', 1),
	(51, 22, 'Alguém curtiu sua postagem: "1+1".', '2025-11-20 13:53:26', 1),
	(52, 22, 'Alguém curtiu sua postagem: "1+1".', '2025-11-20 13:53:35', 1),
	(53, 22, 'Alguém curtiu sua postagem: "1+1".', '2025-11-20 13:53:50', 1),
	(54, 22, 'Alguém curtiu sua postagem: "1+1".', '2025-11-20 13:55:08', 1),
	(55, 22, 'Alguém curtiu sua postagem: "1+1".', '2025-11-20 13:55:08', 1),
	(56, 22, 'Alguém curtiu sua postagem: "1+1".', '2025-11-20 13:58:29', 1),
	(57, 20, 'Parabéns! Você desbloqueou a conquista: "Estreante Literário".', '2025-11-20 14:10:19', 1),
	(58, 20, 'Sua postagem \'1+1\' foi aprovada e está visível para a comunidade!', '2025-11-20 14:13:10', 1),
	(59, 23, 'Parabéns! Você desbloqueou a conquista: "Estreante Literário".', '2025-11-20 16:44:14', 1),
	(60, 23, 'Sua postagem \'exemplo postagem\' foi aprovada e está visível para a comunidade!', '2025-11-20 16:44:28', 1),
	(61, 20, 'Sua postagem \'1+1\' foi removida. Entre em contato com a administração para mais detalhes.', '2025-11-20 17:30:57', 0);

-- Copiando estrutura para tabela tcc.opcoes_resposta
CREATE TABLE IF NOT EXISTS `opcoes_resposta` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_pergunta` int NOT NULL,
  `texto_opcao` varchar(255) NOT NULL,
  `is_correta` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `id_pergunta` (`id_pergunta`),
  CONSTRAINT `opcoes_resposta_ibfk_1` FOREIGN KEY (`id_pergunta`) REFERENCES `perguntas_questionario` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela tcc.opcoes_resposta: ~4 rows (aproximadamente)
INSERT INTO `opcoes_resposta` (`id`, `id_pergunta`, `texto_opcao`, `is_correta`) VALUES
	(34, 11, '1', 1),
	(35, 11, '2', 0),
	(36, 12, '1', 1),
	(37, 12, '3', 0);

-- Copiando estrutura para tabela tcc.perguntas_questionario
CREATE TABLE IF NOT EXISTS `perguntas_questionario` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_questionario` int NOT NULL,
  `texto_pergunta` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_questionario` (`id_questionario`),
  CONSTRAINT `perguntas_questionario_ibfk_1` FOREIGN KEY (`id_questionario`) REFERENCES `questionarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela tcc.perguntas_questionario: ~2 rows (aproximadamente)
INSERT INTO `perguntas_questionario` (`id`, `id_questionario`, `texto_pergunta`) VALUES
	(11, 8, 'aaaaa'),
	(12, 8, 'aaaaa');

-- Copiando estrutura para tabela tcc.postagens
CREATE TABLE IF NOT EXISTS `postagens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) NOT NULL,
  `descricao` text NOT NULL,
  `imagem` varchar(255) NOT NULL,
  `data_criacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `criador_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `criador_id` (`criador_id`),
  CONSTRAINT `postagens_ibfk_1` FOREIGN KEY (`criador_id`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela tcc.postagens: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela tcc.posts
CREATE TABLE IF NOT EXISTS `posts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_aluno` int NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `conteudo` text NOT NULL,
  `status_moderacao` enum('pendente','aprovado','recusado') DEFAULT 'pendente',
  `id_adm_moderador` int DEFAULT NULL,
  `motivo_recusa` text,
  `data_criacao` datetime DEFAULT CURRENT_TIMESTAMP,
  `data_ultima_atualizacao` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `curtidas` int DEFAULT '0',
  `visualizacoes` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `id_aluno` (`id_aluno`),
  KEY `id_adm_moderador` (`id_adm_moderador`),
  CONSTRAINT `posts_ibfk_1` FOREIGN KEY (`id_aluno`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `posts_ibfk_2` FOREIGN KEY (`id_adm_moderador`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela tcc.posts: ~1 rows (aproximadamente)
INSERT INTO `posts` (`id`, `id_aluno`, `titulo`, `conteudo`, `status_moderacao`, `id_adm_moderador`, `motivo_recusa`, `data_criacao`, `data_ultima_atualizacao`, `curtidas`, `visualizacoes`) VALUES
	(22, 23, 'exemplo postagem', 'exemplo', 'aprovado', NULL, NULL, '2025-11-20 16:44:14', '2025-11-20 16:44:28', 0, 0);

-- Copiando estrutura para tabela tcc.posts_pendentes
CREATE TABLE IF NOT EXISTS `posts_pendentes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `descricao` text NOT NULL,
  `imagem` varchar(255) NOT NULL,
  `status` enum('pendente','aprovado','negado') DEFAULT 'pendente',
  `motivo_negacao` text,
  `data_envio` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `posts_pendentes_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela tcc.posts_pendentes: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela tcc.post_likes
CREATE TABLE IF NOT EXISTS `post_likes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `post_id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `data_curtida` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `post_id` (`post_id`,`usuario_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `post_likes_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_likes_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela tcc.post_likes: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela tcc.post_tags
CREATE TABLE IF NOT EXISTS `post_tags` (
  `post_id` int NOT NULL,
  `tag_id` int NOT NULL,
  PRIMARY KEY (`post_id`,`tag_id`),
  KEY `tag_id` (`tag_id`),
  CONSTRAINT `post_tags_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela tcc.post_tags: ~1 rows (aproximadamente)
INSERT INTO `post_tags` (`post_id`, `tag_id`) VALUES
	(22, 13);

-- Copiando estrutura para tabela tcc.questionarios
CREATE TABLE IF NOT EXISTS `questionarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_video` int NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `pontuacao_maxima` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `id_video` (`id_video`),
  CONSTRAINT `questionarios_ibfk_1` FOREIGN KEY (`id_video`) REFERENCES `videos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela tcc.questionarios: ~1 rows (aproximadamente)
INSERT INTO `questionarios` (`id`, `id_video`, `titulo`, `pontuacao_maxima`) VALUES
	(8, 20, 'teste', 1);

-- Copiando estrutura para tabela tcc.respostas_aluno
CREATE TABLE IF NOT EXISTS `respostas_aluno` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_aluno` int NOT NULL,
  `id_pergunta` int NOT NULL,
  `id_opcao_selecionada` int NOT NULL,
  `data_resposta` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_aluno` (`id_aluno`),
  KEY `id_pergunta` (`id_pergunta`),
  KEY `id_opcao_selecionada` (`id_opcao_selecionada`),
  CONSTRAINT `respostas_aluno_ibfk_1` FOREIGN KEY (`id_aluno`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `respostas_aluno_ibfk_2` FOREIGN KEY (`id_pergunta`) REFERENCES `perguntas_questionario` (`id`) ON DELETE CASCADE,
  CONSTRAINT `respostas_aluno_ibfk_3` FOREIGN KEY (`id_opcao_selecionada`) REFERENCES `opcoes_resposta` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela tcc.respostas_aluno: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela tcc.resultados_questionarios
CREATE TABLE IF NOT EXISTS `resultados_questionarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `id_questionario` int NOT NULL,
  `pontuacao` int NOT NULL,
  `data_resposta` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_usuario` (`id_usuario`),
  KEY `id_questionario` (`id_questionario`),
  CONSTRAINT `resultados_questionarios_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `resultados_questionarios_ibfk_2` FOREIGN KEY (`id_questionario`) REFERENCES `questionarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela tcc.resultados_questionarios: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela tcc.tags
CREATE TABLE IF NOT EXISTS `tags` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela tcc.tags: ~15 rows (aproximadamente)
INSERT INTO `tags` (`id`, `nome`) VALUES
	(10, 'Artes'),
	(5, 'Biologia'),
	(15, 'ENEM'),
	(11, 'Filosofia'),
	(2, 'Física'),
	(8, 'Geografia'),
	(7, 'História'),
	(9, 'Inglês'),
	(1, 'Matemática'),
	(4, 'Português'),
	(6, 'Programação'),
	(3, 'Química'),
	(12, 'Sociologia'),
	(13, 'TCC'),
	(14, 'Vestibular');

-- Copiando estrutura para tabela tcc.tentativas_questionarios
CREATE TABLE IF NOT EXISTS `tentativas_questionarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `id_questionario` int NOT NULL,
  `pontuacao_obtida` int NOT NULL,
  `total_perguntas` int NOT NULL,
  `data_tentativa` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_usuario` (`id_usuario`,`id_questionario`),
  KEY `id_questionario` (`id_questionario`),
  CONSTRAINT `tentativas_questionarios_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `tentativas_questionarios_ibfk_2` FOREIGN KEY (`id_questionario`) REFERENCES `questionarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela tcc.tentativas_questionarios: ~1 rows (aproximadamente)
INSERT INTO `tentativas_questionarios` (`id`, `id_usuario`, `id_questionario`, `pontuacao_obtida`, `total_perguntas`, `data_tentativa`) VALUES
	(4, 23, 8, 1, 2, '2025-11-20 17:31:59');

-- Copiando estrutura para tabela tcc.usuarios
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id_usuario` int NOT NULL AUTO_INCREMENT,
  `nome_usuario` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `email_usuario` varchar(100) NOT NULL,
  `rm_usuario` int NOT NULL,
  `etec_usuario` int NOT NULL,
  `telefone_usuario` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `senha_usuario` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `nivel` int DEFAULT '1',
  `foto_perfil` varchar(255) DEFAULT 'FotoPerfilGen.jpg',
  `total_uploads_feitos` int DEFAULT '0',
  `data_criacao` datetime DEFAULT CURRENT_TIMESTAMP,
  `data_ultimo_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `id_usuario` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela tcc.usuarios: ~5 rows (aproximadamente)
INSERT INTO `usuarios` (`id_usuario`, `nome_usuario`, `email_usuario`, `rm_usuario`, `etec_usuario`, `telefone_usuario`, `senha_usuario`, `nivel`, `foto_perfil`, `total_uploads_feitos`, `data_criacao`, `data_ultimo_login`) VALUES
	(20, 'ADM - Eduardo', 'neez1926@gmail.com', 11111, 262, '11977837647', '$2y$10$4IqJBX/Kd9i6fZVT.uikOuoztv1/3Wor9w1BbgGgb9hp/z7GbGsMi', 2, 'FotoPerfilGen.jpg', 1, '2025-09-29 17:43:07', '2025-11-20 17:33:30'),
	(21, 'ADM - Nicolas', 'neez1926@gmail.com', 22222, 262, '11978690623', '$2y$10$GS6Sf5a6Egc2bk.wxc8gT..YCj4Xmn8V1gim.dSmbSj1zpvQNVy3y', 2, 'FotoPerfilGen.jpg', 0, '2025-09-29 17:46:51', NULL),
	(22, 'Notícias', 'neez1926@gmail.com', 33333, 262, '11977837647', '$2y$10$4IqJBX/Kd9i6fZVT.uikOuoztv1/3Wor9w1BbgGgb9hp/z7GbGsMi', 3, 'FotoPerfilGen.jpg', 0, '2025-09-29 17:48:04', '2025-11-20 16:43:00'),
	(23, 'Usuário', 'email@gmail.com', 23000, 262, '999999999', '$2y$10$4IqJBX/Kd9i6fZVT.uikOuoztv1/3Wor9w1BbgGgb9hp/z7GbGsMi', 1, 'FotoPerfilGen.jpg', 1, '2025-09-29 18:33:43', '2025-11-20 17:31:51'),
	(24, 'teste', 'teste1234@gmail.com', 12345, 262, '99999999999', '$2y$10$3DLKZPkimO7FCTL.1WzvauFlKPwSiNCiU4oJSAwuGhGBNUaZgUpV6', 1, 'FotoPerfilGen.jpg', 0, '2025-11-20 13:27:07', '2025-11-20 13:28:21');

-- Copiando estrutura para tabela tcc.usuario_conquistas
CREATE TABLE IF NOT EXISTS `usuario_conquistas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `id_conquista` int NOT NULL,
  `data_desbloqueio` datetime DEFAULT CURRENT_TIMESTAMP,
  `data_conquista` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_usuario` (`id_usuario`,`id_conquista`),
  UNIQUE KEY `id_usuario_2` (`id_usuario`,`id_conquista`),
  KEY `id_conquista` (`id_conquista`),
  CONSTRAINT `usuario_conquistas_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `usuario_conquistas_ibfk_2` FOREIGN KEY (`id_conquista`) REFERENCES `conquistas_master` (`id_conquista`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela tcc.usuario_conquistas: ~12 rows (aproximadamente)
INSERT INTO `usuario_conquistas` (`id`, `id_usuario`, `id_conquista`, `data_desbloqueio`, `data_conquista`) VALUES
	(33, 20, 1, '2025-09-30 19:56:14', '2025-09-30 19:56:14'),
	(34, 20, 2, '2025-11-17 20:00:04', '2025-11-17 20:00:04'),
	(35, 22, 1, '2025-11-20 12:09:00', '2025-11-20 12:09:00'),
	(36, 22, 2, '2025-11-20 12:09:00', '2025-11-20 12:09:00'),
	(37, 24, 1, '2025-11-20 13:27:14', '2025-11-20 13:27:14'),
	(38, 22, 7, '2025-11-20 13:50:20', '2025-11-20 13:50:20'),
	(39, 20, 7, '2025-11-20 14:10:19', '2025-11-20 14:10:19'),
	(40, 20, 13, '2025-11-20 14:20:28', '2025-11-20 14:20:28'),
	(41, 23, 1, '2025-11-20 16:21:20', '2025-11-20 16:21:20'),
	(42, 23, 2, '2025-11-20 16:21:20', '2025-11-20 16:21:20'),
	(43, 23, 7, '2025-11-20 16:44:14', '2025-11-20 16:44:14'),
	(44, 23, 13, '2025-11-20 17:32:12', '2025-11-20 17:32:12');

-- Copiando estrutura para tabela tcc.videos
CREATE TABLE IF NOT EXISTS `videos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) NOT NULL,
  `url_video` varchar(255) NOT NULL,
  `descricao` text,
  `id_professor` int NOT NULL,
  `data_publicacao` datetime DEFAULT CURRENT_TIMESTAMP,
  `status_moderacao` enum('aprovado','pendente','rejeitado') DEFAULT 'pendente',
  `id_materia` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_professor` (`id_professor`),
  KEY `fk_videos_materias` (`id_materia`),
  CONSTRAINT `fk_videos_materias` FOREIGN KEY (`id_materia`) REFERENCES `materias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `videos_ibfk_1` FOREIGN KEY (`id_professor`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela tcc.videos: ~1 rows (aproximadamente)
INSERT INTO `videos` (`id`, `titulo`, `url_video`, `descricao`, `id_professor`, `data_publicacao`, `status_moderacao`, `id_materia`) VALUES
	(20, 'a', 'https://youtu.be/CHFeQegCo00?si=akufzajccN52aT4D', 'noslen', 20, '2025-11-20 17:31:10', 'aprovado', 1);

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
