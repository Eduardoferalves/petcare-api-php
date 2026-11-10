-- ============================================================================
-- Trabalho 1 - API REST em PHP - Clínica Veterinária PetCare
-- Script de criação e carga inicial do banco de dados MySQL (idempotente)
-- ============================================================================

DROP DATABASE IF EXISTS petcare;
CREATE DATABASE petcare
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE petcare;

-- ----------------------------------------------------------------------------
-- 1. Tabela: tutores
-- ----------------------------------------------------------------------------
CREATE TABLE tutores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    telefone VARCHAR(30) NOT NULL,
    email VARCHAR(150) NOT NULL,
    CONSTRAINT uq_tutores_email UNIQUE (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. Tabela: pets
-- Regra de exclusão: ON DELETE RESTRICT impede excluir um tutor que possua
-- pets cadastrados, preservando o histórico clínico e retornando 409 Conflict.
-- ----------------------------------------------------------------------------
CREATE TABLE pets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(60) NOT NULL,
    especie VARCHAR(20) NOT NULL,
    raca VARCHAR(60) NULL DEFAULT NULL,
    data_nascimento DATE NOT NULL,
    peso_kg DECIMAL(5,2) NOT NULL,
    tutor_id INT NOT NULL,
    CONSTRAINT fk_pets_tutor
        FOREIGN KEY (tutor_id) REFERENCES tutores(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. Tabela: usuarios
-- As senhas nunca são gravadas em texto puro (geradas com password_hash).
-- ----------------------------------------------------------------------------
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    CONSTRAINT uq_usuarios_email UNIQUE (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. Tabela: tokens
-- Armazena os tokens Bearer gerados com bin2hex(random_bytes(32)) e validade.
-- ----------------------------------------------------------------------------
CREATE TABLE tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    expira_em DATETIME NOT NULL,
    CONSTRAINT fk_tokens_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- CARGA DE DADOS DE EXEMPLO (SEEDS)
-- ============================================================================

-- 5 tutores iniciais (mais um 6º tutor sem pets para facilitar testes diretos de DELETE)
INSERT INTO tutores (id, nome, telefone, email) VALUES
(1, 'Ana Carolina Mendes', '(11) 98765-4321', 'ana.mendes@email.com'),
(2, 'Carlos Eduardo Rocha', '(11) 97654-3210', 'carlos.rocha@email.com'),
(3, 'Beatriz Lima Santos', '(21) 99876-5432', 'beatriz.santos@email.com'),
(4, 'Marcos Vinícius Oliveira', '(31) 98432-1098', 'marcos.oliveira@email.com'),
(5, 'Fernanda Costa Alves', '(41) 99123-4567', 'fernanda.alves@email.com'),
(6, 'Roberto Almeida Prado', '(51) 98234-5678', 'roberto.prado@email.com');

-- 10 pets distribuídos entre os tutores 1 a 5 (tutores 1, 2, 3 e 4 possuem mais de 1 pet)
INSERT INTO pets (id, nome, especie, raca, data_nascimento, peso_kg, tutor_id) VALUES
(1, 'Thor', 'cachorro', 'Golden Retriever', '2021-04-15', 32.50, 1),
(2, 'Luna', 'gato', 'Siamês', '2022-08-20', 4.20, 1),
(3, 'Bidu', 'cachorro', 'Schnauzer Miniatura', '2020-11-03', 7.80, 1),
(4, 'Mia', 'gato', NULL, '2023-01-12', 3.60, 2),
(5, 'Pipoca', 'roedor', 'Porquinho-da-índia', '2024-03-10', 0.95, 2),
(6, 'Louro', 'ave', 'Calopsita', '2022-05-28', 0.10, 3),
(7, 'Max', 'cachorro', 'Labrador', '2019-09-14', 29.40, 3),
(8, 'Nina', 'gato', 'Persa', '2021-12-01', 4.50, 4),
(9, 'Spike', 'outro', 'Iguana Verde', '2023-06-18', 2.30, 4),
(10, 'Mel', 'cachorro', 'Poodle', '2020-02-25', 6.10, 5);

-- Usuários de teste para autenticação (POST /login)
-- Senha em texto puro para ambos os usuários: 123456
-- Hash gerado via PHP 8.2: password_hash('123456', PASSWORD_DEFAULT)
INSERT INTO usuarios (id, nome, email, senha_hash) VALUES
(1, 'Administrador PetCare', 'admin@petcare.com.br', '$2y$10$WK.XpSj95dYQohI2LMv81uzJb9PcwEymQuXHJx3G8..vv3i.esTxq'),
(2, 'Recepção Clínica PetCare', 'recepcao@petcare.com.br', '$2y$10$WK.XpSj95dYQohI2LMv81uzJb9PcwEymQuXHJx3G8..vv3i.esTxq');
