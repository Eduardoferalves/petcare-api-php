<?php

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/autenticacao.php';
require_once __DIR__ . '/validacao.php';

$pdo = conectar();

$metodo = $_SERVER['REQUEST_METHOD'];
$caminho = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if ($base !== '' && str_starts_with($caminho, $base)) {
    $caminho = substr($caminho, strlen($base)) ?: '/';
}
if ($caminho !== '/') {
    $caminho = rtrim($caminho, '/');
}

// ============================================================================
// 1. AUTENTICAÇÃO: POST /login e POST /logout
// ============================================================================

if ($metodo === 'POST' && $caminho === '/login') {
    $corpo = lerCorpoJson();
    $erros = validarLogin($corpo);
    if (!empty($erros)) {
        responder(['detail' => $erros], 422);
    }

    $stmt = $pdo->prepare('SELECT id, nome, email, senha_hash FROM usuarios WHERE email = ?');
    $stmt->execute([trim($corpo['email'])]);
    $usuario = $stmt->fetch();

    if (!$usuario || !password_verify($corpo['senha'], $usuario['senha_hash'])) {
        responder(['detail' => 'E-mail ou senha inválidos'], 401);
    }

    $token = bin2hex(random_bytes(32));
    $expiraEm = date('Y-m-d H:i:s', strtotime('+2 hours'));

    $stmtToken = $pdo->prepare(
        'INSERT INTO tokens (usuario_id, token, expira_em) VALUES (?, ?, ?)'
    );
    $stmtToken->execute([(int) $usuario['id'], $token, $expiraEm]);

    responder([
        'token' => $token,
        'tipo' => 'Bearer',
        'expira_em' => $expiraEm,
    ], 200);
}

if ($metodo === 'POST' && $caminho === '/logout') {
    $autenticado = exigirToken($pdo);

    $stmt = $pdo->prepare('DELETE FROM tokens WHERE token = ?');
    $stmt->execute([$autenticado['token']]);

    responder(null, 204);
}

// ============================================================================
// 2. ROTAS DE TUTORES
// ============================================================================

// GET /tutores - Lista todos os tutores (Pública)
if ($metodo === 'GET' && $caminho === '/tutores') {
    $stmt = $pdo->query('SELECT id, nome, telefone, email FROM tutores ORDER BY id ASC');
    $tutores = array_map('formatarTutor', $stmt->fetchAll());

    responder([
        'total' => count($tutores),
        'tutores' => $tutores,
    ], 200);
}

// GET /tutores/{id} - Busca um tutor pelo ID (Pública)
if ($metodo === 'GET' && preg_match('#^/tutores/(\d+)$#', $caminho, $partes)) {
    $id = (int) $partes[1];

    $stmt = $pdo->prepare('SELECT id, nome, telefone, email FROM tutores WHERE id = ?');
    $stmt->execute([$id]);
    $tutor = $stmt->fetch();

    if (!$tutor) {
        responder(['detail' => 'Tutor não encontrado'], 404);
    }

    responder(formatarTutor($tutor), 200);
}

// GET /tutores/{id}/pets - Lista os pets de um tutor específico (Pública)
if ($metodo === 'GET' && preg_match('#^/tutores/(\d+)/pets$#', $caminho, $partes)) {
    $tutorId = (int) $partes[1];

    $stmtTutor = $pdo->prepare('SELECT id, nome, telefone, email FROM tutores WHERE id = ?');
    $stmtTutor->execute([$tutorId]);
    $tutor = $stmtTutor->fetch();

    if (!$tutor) {
        responder(['detail' => 'Tutor não encontrado'], 404);
    }

    $stmtPets = $pdo->prepare(
        'SELECT id, nome, especie, raca, data_nascimento, peso_kg, tutor_id
         FROM pets
         WHERE tutor_id = ?
         ORDER BY id ASC'
    );
    $stmtPets->execute([$tutorId]);
    $pets = array_map('formatarPet', $stmtPets->fetchAll());

    responder([
        'tutor_id' => (int) $tutor['id'],
        'tutor_nome' => $tutor['nome'],
        'total' => count($pets),
        'pets' => $pets,
    ], 200);
}

// POST /tutores - Cadastra um novo tutor (Requer Token)
if ($metodo === 'POST' && $caminho === '/tutores') {
    exigirToken($pdo);
    $corpo = lerCorpoJson();

    $erros = validarTutor($corpo);
    if (!empty($erros)) {
        responder(['detail' => $erros], 422);
    }

    $nome = trim($corpo['nome']);
    $telefone = trim($corpo['telefone']);
    $email = trim($corpo['email']);

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO tutores (nome, telefone, email) VALUES (?, ?, ?)'
        );
        $stmt->execute([$nome, $telefone, $email]);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) === 1062) {
            responder(['detail' => 'E-mail já cadastrado'], 409);
        }
        throw $e;
    }

    $novoId = (int) $pdo->lastInsertId();

    responder([
        'id' => $novoId,
        'nome' => $nome,
        'telefone' => $telefone,
        'email' => $email,
    ], 201);
}

// PUT /tutores/{id} - Atualiza um tutor existente (Requer Token)
if ($metodo === 'PUT' && preg_match('#^/tutores/(\d+)$#', $caminho, $partes)) {
    exigirToken($pdo);
    $id = (int) $partes[1];

    $corpo = lerCorpoJson();

    $stmtBusca = $pdo->prepare('SELECT id FROM tutores WHERE id = ?');
    $stmtBusca->execute([$id]);
    if (!$stmtBusca->fetch()) {
        responder(['detail' => 'Tutor não encontrado'], 404);
    }

    $erros = validarTutor($corpo);
    if (!empty($erros)) {
        responder(['detail' => $erros], 422);
    }

    $nome = trim($corpo['nome']);
    $telefone = trim($corpo['telefone']);
    $email = trim($corpo['email']);

    try {
        $stmt = $pdo->prepare(
            'UPDATE tutores SET nome = ?, telefone = ?, email = ? WHERE id = ?'
        );
        $stmt->execute([$nome, $telefone, $email, $id]);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) === 1062) {
            responder(['detail' => 'E-mail já cadastrado'], 409);
        }
        throw $e;
    }

    responder([
        'id' => $id,
        'nome' => $nome,
        'telefone' => $telefone,
        'email' => $email,
    ], 200);
}

// DELETE /tutores/{id} - Exclui um tutor (Requer Token)
if ($metodo === 'DELETE' && preg_match('#^/tutores/(\d+)$#', $caminho, $partes)) {
    exigirToken($pdo);
    $id = (int) $partes[1];

    $stmtBusca = $pdo->prepare('SELECT id FROM tutores WHERE id = ?');
    $stmtBusca->execute([$id]);
    if (!$stmtBusca->fetch()) {
        responder(['detail' => 'Tutor não encontrado'], 404);
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM tutores WHERE id = ?');
        $stmt->execute([$id]);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) === 1451 || ($e->errorInfo[1] ?? null) === 1452) {
            responder(['detail' => 'Não é possível excluir tutor que possui pets vinculados'], 409);
        }
        throw $e;
    }

    responder(null, 204);
}

// ============================================================================
// 3. ROTAS DE PETS
// ============================================================================

// GET /pets - Lista todos os pets com filtro opcional ?especie= (Pública)
if ($metodo === 'GET' && $caminho === '/pets') {
    if (isset($_GET['especie']) && $_GET['especie'] !== '') {
        $especieFiltro = $_GET['especie'];
        if (!in_array($especieFiltro, ESPECIES_VALIDAS, true)) {
            responder([
                'detail' => [
                    'O filtro especie deve ser um dos valores: cachorro, gato, ave, roedor ou outro.',
                ],
            ], 422);
        }

        $stmt = $pdo->prepare(
            'SELECT p.id, p.nome, p.especie, p.raca, p.data_nascimento, p.peso_kg, p.tutor_id, t.nome AS tutor_nome
             FROM pets p
             INNER JOIN tutores t ON t.id = p.tutor_id
             WHERE p.especie = ?
             ORDER BY p.id ASC'
        );
        $stmt->execute([$especieFiltro]);
    } else {
        $stmt = $pdo->query(
            'SELECT p.id, p.nome, p.especie, p.raca, p.data_nascimento, p.peso_kg, p.tutor_id, t.nome AS tutor_nome
             FROM pets p
             INNER JOIN tutores t ON t.id = p.tutor_id
             ORDER BY p.id ASC'
        );
    }

    $pets = array_map('formatarPet', $stmt->fetchAll());

    responder([
        'total' => count($pets),
        'pets' => $pets,
    ], 200);
}

// GET /pets/{id} - Busca um pet pelo ID com o nome do tutor via JOIN (Pública)
if ($metodo === 'GET' && preg_match('#^/pets/(\d+)$#', $caminho, $partes)) {
    $id = (int) $partes[1];

    $stmt = $pdo->prepare(
        'SELECT p.id, p.nome, p.especie, p.raca, p.data_nascimento, p.peso_kg, p.tutor_id, t.nome AS tutor_nome
         FROM pets p
         INNER JOIN tutores t ON t.id = p.tutor_id
         WHERE p.id = ?'
    );
    $stmt->execute([$id]);
    $pet = $stmt->fetch();

    if (!$pet) {
        responder(['detail' => 'Pet não encontrado'], 404);
    }

    responder(formatarPet($pet), 200);
}

// POST /pets - Cadastra um novo pet (Requer Token)
if ($metodo === 'POST' && $caminho === '/pets') {
    exigirToken($pdo);
    $corpo = lerCorpoJson();

    $erros = validarPet($corpo, $pdo);
    if (!empty($erros)) {
        responder(['detail' => $erros], 422);
    }

    $nome = trim($corpo['nome']);
    $especie = $corpo['especie'];
    $raca = (isset($corpo['raca']) && is_string($corpo['raca']) && trim($corpo['raca']) !== '')
        ? trim($corpo['raca'])
        : null;
    $dataNascimento = $corpo['data_nascimento'];
    $pesoKg = (float) $corpo['peso_kg'];
    $tutorId = (int) $corpo['tutor_id'];

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO pets (nome, especie, raca, data_nascimento, peso_kg, tutor_id)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$nome, $especie, $raca, $dataNascimento, $pesoKg, $tutorId]);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) === 1452) {
            responder(['detail' => ['O tutor_id informado não existe.']], 422);
        }
        throw $e;
    }

    $novoId = (int) $pdo->lastInsertId();

    responder([
        'id' => $novoId,
        'nome' => $nome,
        'especie' => $especie,
        'raca' => $raca,
        'data_nascimento' => $dataNascimento,
        'peso_kg' => $pesoKg,
        'tutor_id' => $tutorId,
    ], 201);
}

// PUT /pets/{id} - Atualiza um pet existente (Requer Token)
if ($metodo === 'PUT' && preg_match('#^/pets/(\d+)$#', $caminho, $partes)) {
    exigirToken($pdo);
    $id = (int) $partes[1];

    $corpo = lerCorpoJson();

    $stmtBusca = $pdo->prepare('SELECT id FROM pets WHERE id = ?');
    $stmtBusca->execute([$id]);
    if (!$stmtBusca->fetch()) {
        responder(['detail' => 'Pet não encontrado'], 404);
    }

    $erros = validarPet($corpo, $pdo);
    if (!empty($erros)) {
        responder(['detail' => $erros], 422);
    }

    $nome = trim($corpo['nome']);
    $especie = $corpo['especie'];
    $raca = (isset($corpo['raca']) && is_string($corpo['raca']) && trim($corpo['raca']) !== '')
        ? trim($corpo['raca'])
        : null;
    $dataNascimento = $corpo['data_nascimento'];
    $pesoKg = (float) $corpo['peso_kg'];
    $tutorId = (int) $corpo['tutor_id'];

    try {
        $stmt = $pdo->prepare(
            'UPDATE pets
             SET nome = ?, especie = ?, raca = ?, data_nascimento = ?, peso_kg = ?, tutor_id = ?
             WHERE id = ?'
        );
        $stmt->execute([$nome, $especie, $raca, $dataNascimento, $pesoKg, $tutorId, $id]);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) === 1452) {
            responder(['detail' => ['O tutor_id informado não existe.']], 422);
        }
        throw $e;
    }

    responder([
        'id' => $id,
        'nome' => $nome,
        'especie' => $especie,
        'raca' => $raca,
        'data_nascimento' => $dataNascimento,
        'peso_kg' => $pesoKg,
        'tutor_id' => $tutorId,
    ], 200);
}

// DELETE /pets/{id} - Exclui um pet (Requer Token)
if ($metodo === 'DELETE' && preg_match('#^/pets/(\d+)$#', $caminho, $partes)) {
    exigirToken($pdo);
    $id = (int) $partes[1];

    $stmtBusca = $pdo->prepare('SELECT id FROM pets WHERE id = ?');
    $stmtBusca->execute([$id]);
    if (!$stmtBusca->fetch()) {
        responder(['detail' => 'Pet não encontrado'], 404);
    }

    $stmt = $pdo->prepare('DELETE FROM pets WHERE id = ?');
    $stmt->execute([$id]);

    responder(null, 204);
}

// ============================================================================
// 4. FALLBACK PARA ROTAS NÃO ENCONTRADAS
// ============================================================================

responder(['detail' => 'Rota não encontrada'], 404);
