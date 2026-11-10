<?php

date_default_timezone_set('America/Sao_Paulo');

const OPCOES_JSON = JSON_PRETTY_PRINT
    | JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_PRESERVE_ZERO_FRACTION;

/**
 * Envia uma resposta HTTP padronizada em JSON (ou sem corpo para 204) e encerra a execução.
 */
function responder($dados = null, int $status = 200): void
{
    http_response_code($status);
    if ($status === 204) {
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados, OPCOES_JSON);
    exit;
}

/**
 * Lê e decodifica o corpo JSON da requisição.
 * Caso o corpo não seja um JSON válido, responde imediatamente com 400 Bad Request.
 */
function lerCorpoJson(): array
{
    $corpo = json_decode(file_get_contents('php://input'), true);
    if (!is_array($corpo)) {
        responder(['detail' => 'O corpo da requisição deve ser um JSON válido'], 400);
    }
    return $corpo;
}

/**
 * Cria e retorna uma conexão PDO com o banco MySQL petcare.
 */
function conectar(): PDO
{
    $opcoes = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    try {
        $pdo = new PDO(
            'mysql:host=localhost;port=3306;dbname=petcare;charset=utf8mb4',
            'root',
            '',
            $opcoes
        );
    } catch (PDOException $e) {
        try {
            $pdo = new PDO(
                'mysql:host=localhost;port=3307;dbname=petcare;charset=utf8mb4',
                'root',
                '',
                $opcoes
            );
        } catch (PDOException $e2) {
            responder(['detail' => 'Erro de conexão com o banco de dados. Verifique se o MySQL está ativo e se o script banco.sql foi executado.'], 500);
        }
    }

    $pdo->exec("SET time_zone = '-03:00'");

    return $pdo;
}

/**
 * Normaliza os tipos de um registro de tutor vindo do banco para serialização JSON.
 */
function formatarTutor(array $linha): array
{
    return [
        'id' => (int) $linha['id'],
        'nome' => $linha['nome'],
        'telefone' => $linha['telefone'],
        'email' => $linha['email'],
    ];
}

/**
 * Normaliza os tipos de um registro de pet vindo do banco para serialização JSON.
 */
function formatarPet(array $linha): array
{
    $pet = [
        'id' => (int) $linha['id'],
        'nome' => $linha['nome'],
        'especie' => $linha['especie'],
        'raca' => $linha['raca'],
        'data_nascimento' => $linha['data_nascimento'],
        'peso_kg' => (float) $linha['peso_kg'],
        'tutor_id' => (int) $linha['tutor_id'],
    ];

    if (array_key_exists('tutor_nome', $linha)) {
        $pet['tutor_nome'] = $linha['tutor_nome'];
    }

    return $pet;
}
