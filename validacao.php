<?php

const ESPECIES_VALIDAS = ['cachorro', 'gato', 'ave', 'roedor', 'outro'];

/**
 * Valida os campos enviados na rota POST /login.
 * Retorna uma lista de mensagens de erro (vazia se tudo estiver válido).
 */
function validarLogin(array $dados): array
{
    $erros = [];

    if (!isset($dados['email']) || !is_string($dados['email']) || trim($dados['email']) === '') {
        $erros[] = 'O campo email é obrigatório.';
    } elseif (filter_var(trim($dados['email']), FILTER_VALIDATE_EMAIL) === false) {
        $erros[] = 'O campo email deve conter um endereço de e-mail válido.';
    }

    if (!isset($dados['senha']) || !is_string($dados['senha']) || $dados['senha'] === '') {
        $erros[] = 'O campo senha é obrigatório.';
    }

    return $erros;
}

/**
 * Valida os dados de entrada de um tutor (POST /tutores e PUT /tutores/{id}).
 * Regras:
 * - nome: string obrigatória entre 2 e 100 caracteres (após trim)
 * - telefone: string obrigatória não vazia (até 30 caracteres)
 * - email: string obrigatória com formato de e-mail válido
 */
function validarTutor(array $dados): array
{
    $erros = [];

    if (
        !isset($dados['nome'])
        || !is_string($dados['nome'])
        || !preg_match('/^.{2,100}$/u', trim($dados['nome']))
    ) {
        $erros[] = 'O campo nome é obrigatório e deve ter entre 2 e 100 caracteres.';
    }

    if (
        !isset($dados['telefone'])
        || !is_string($dados['telefone'])
        || !preg_match('/^.{1,30}$/u', trim($dados['telefone']))
    ) {
        $erros[] = 'O campo telefone é obrigatório e deve ter até 30 caracteres.';
    }

    if (
        !isset($dados['email'])
        || !is_string($dados['email'])
        || filter_var(trim($dados['email']), FILTER_VALIDATE_EMAIL) === false
    ) {
        $erros[] = 'O campo email é obrigatório e deve ser um endereço de e-mail válido.';
    }

    return $erros;
}

/**
 * Valida os dados de entrada de um pet (POST /pets e PUT /pets/{id}).
 * Regras:
 * - nome: string obrigatória entre 2 e 60 caracteres (após trim)
 * - especie: estritamente em ['cachorro', 'gato', 'ave', 'roedor', 'outro']
 * - raca: opcional/nulo; se informado, deve ser string de até 60 caracteres
 * - peso_kg: numérico (int ou float), maior que 0 e até 200
 * - data_nascimento: formato AAAA-MM-DD, data de calendário real e não futura
 * - tutor_id: inteiro positivo correspondente a um tutor existente no banco
 */
function validarPet(array $dados, PDO $pdo): array
{
    $erros = [];

    if (
        !isset($dados['nome'])
        || !is_string($dados['nome'])
        || !preg_match('/^.{2,60}$/u', trim($dados['nome']))
    ) {
        $erros[] = 'O campo nome é obrigatório e deve ter entre 2 e 60 caracteres.';
    }

    if (
        !isset($dados['especie'])
        || !is_string($dados['especie'])
        || !in_array($dados['especie'], ESPECIES_VALIDAS, true)
    ) {
        $erros[] = 'O campo especie deve ser um dos valores: cachorro, gato, ave, roedor ou outro.';
    }

    if (array_key_exists('raca', $dados) && $dados['raca'] !== null) {
        if (!is_string($dados['raca']) || !preg_match('/^.{0,60}$/u', trim($dados['raca']))) {
            $erros[] = 'O campo raca deve ser texto com no máximo 60 caracteres ou nulo.';
        }
    }

    if (
        !isset($dados['peso_kg'])
        || (!is_int($dados['peso_kg']) && !is_float($dados['peso_kg']))
        || $dados['peso_kg'] <= 0
        || $dados['peso_kg'] > 200
    ) {
        $erros[] = 'O campo peso_kg é obrigatório e deve ser um número maior que 0 e até 200.';
    }

    if (!isset($dados['data_nascimento']) || !is_string($dados['data_nascimento'])) {
        $erros[] = 'O campo data_nascimento é obrigatório no formato AAAA-MM-DD.';
    } else {
        $dataNasc = $dados['data_nascimento'];
        $dt = DateTime::createFromFormat('Y-m-d', $dataNasc);
        $errosData = DateTime::getLastErrors();
        $dataCalendarioValida = $dt !== false
            && ($errosData === false || ($errosData['warning_count'] === 0 && $errosData['error_count'] === 0))
            && $dt->format('Y-m-d') === $dataNasc;

        if (!$dataCalendarioValida) {
            $erros[] = 'O campo data_nascimento deve ser uma data válida no formato AAAA-MM-DD.';
        } elseif ($dataNasc > date('Y-m-d')) {
            $erros[] = 'O campo data_nascimento não pode ser uma data futura.';
        }
    }

    if (!isset($dados['tutor_id']) || !is_int($dados['tutor_id']) || $dados['tutor_id'] <= 0) {
        $erros[] = 'O campo tutor_id é obrigatório e deve ser um número inteiro positivo.';
    } else {
        $stmtTutor = $pdo->prepare('SELECT id FROM tutores WHERE id = ?');
        $stmtTutor->execute([$dados['tutor_id']]);
        if (!$stmtTutor->fetch()) {
            $erros[] = 'O tutor_id informado não existe.';
        }
    }

    return $erros;
}
