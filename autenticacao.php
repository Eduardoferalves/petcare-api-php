<?php

date_default_timezone_set('America/Sao_Paulo');

/**
 * Extrai o token Bearer do cabeçalho HTTP Authorization.
 * Considera $_SERVER['HTTP_AUTHORIZATION'] (repassado pelo .htaccess) e getallheaders().
 */
function extrairBearerToken(): ?string
{
    $cabecalho = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';

    if ($cabecalho === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $nome => $valor) {
            if (strcasecmp($nome, 'Authorization') === 0) {
                $cabecalho = $valor;
                break;
            }
        }
    }

    if (preg_match('/^Bearer\s+(\S+)$/i', trim($cabecalho), $partes)) {
        return $partes[1];
    }

    return null;
}

/**
 * Verifica se a requisição possui um token Bearer válido e não expirado.
 * Encerra com 401 caso o token não seja informado, não exista ou esteja expirado.
 */
function exigirToken(PDO $pdo): array
{
    $token = extrairBearerToken();
    if ($token === null) {
        responder(['detail' => 'Token não informado'], 401);
    }

    $stmt = $pdo->prepare(
        'SELECT t.id AS token_id, t.token, t.expira_em, u.id AS usuario_id, u.nome, u.email
         FROM tokens t
         INNER JOIN usuarios u ON u.id = t.usuario_id
         WHERE t.token = ? AND t.expira_em > NOW()'
    );
    $stmt->execute([$token]);
    $registro = $stmt->fetch();

    if (!$registro) {
        responder(['detail' => 'Token inválido ou expirado'], 401);
    }

    return $registro;
}
