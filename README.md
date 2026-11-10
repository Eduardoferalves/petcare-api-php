# Clínica Veterinária PetCare — API REST em PHP Puro

Trabalho 1 da disciplina de Desenvolvimento Back-End (Prof. Jr) — API REST completa desenvolvida em **PHP nativo (sem frameworks)** utilizando **PDO + MySQL** (Prepared Statements), roteamento manual com expressões regulares e autenticação via **Bearer Token** com expiração em banco de dados.

---

## Integrantes do Grupo

- **Integrante 1:** Eduardo Fernandes Alves
- **Integrante 2:** (adicionar se aplicável)
- **Integrante 3:** (adicionar se aplicável)

---

## Estrutura de Arquivos (`petcare/`)

```text
petcare/
├── .htaccess          # Reescrita de URLs para index.php e repasse do header Authorization
├── banco.sql          # Script idempotente (DROP/CREATE database, 4 tabelas e seeds)
├── conexao.php        # Conexão PDO, constante OPCOES_JSON, responder(), lerCorpoJson() e formatadores
├── autenticacao.php   # Extração do cabeçalho Bearer e função única exigirToken()
├── validacao.php      # Validações de domínio (422 com lista acumulativa de erros)
├── index.php          # Roteador manual ($metodo, $caminho, preg_match) e handlers das rotas
├── requisicoes.http   # Suíte de testes para a extensão REST Client (sucesso e erros)
└── README.md          # Documentação do projeto
```

---

## Como Rodar o Projeto no XAMPP

**Requisitos:**

- **XAMPP** com **PHP >= 8.0** (a API utiliza funções do PHP 8, como `str_starts_with`) e **MySQL/MariaDB**.
- A pasta do projeto deve ficar em `C:\xampp\htdocs\petcare\` para que a URL base seja `http://localhost/petcare/` (conforme o arquivo `requisicoes.http`).

1. **Iniciar os serviços no XAMPP:**
   - Abra o **XAMPP Control Panel** e inicie os módulos **Apache** e **MySQL**.

2. **Posicionar a pasta no `htdocs`:**
   - Abra um terminal dentro de `C:\xampp\htdocs\` e clone o repositório já com o nome correto da pasta:
     ```bash
     cd C:\xampp\htdocs
     git clone <url-do-repositorio> petcare
     ```
   - O caminho final deve ficar:
     ```text
     C:\xampp\htdocs\petcare\index.php
     ```
   - Alternativamente, copie a pasta `petcare` deste repositório para dentro de `C:\xampp\htdocs\`.
   - A API responderá no endereço base: `http://localhost/petcare/`

3. **Criar e popular o banco de dados (`banco.sql`):**
   - Acesse o **phpMyAdmin** (`http://localhost/phpmyadmin`) ou utilize o cliente de linha de comando do MySQL e execute o script `petcare/banco.sql`:
     ```bash
     C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\petcare\banco.sql
     ```
   - O script apaga (`DROP DATABASE IF EXISTS petcare`) e recria o banco `petcare` do zero com codificação `utf8mb4`, criando as tabelas `tutores`, `pets`, `usuarios` e `tokens`, além de inserir **6 tutores**, **10 pets** e **2 usuários** de teste.
   - *Observação:* A conexão em `conexao.php` utiliza a configuração padrão do XAMPP (`host=localhost`, `port=3306`, usuário `root` e senha vazia `''`).

4. **Testar as rotas (`requisicoes.http`):**
   - Abra o arquivo `petcare/requisicoes.http` no VS Code com a extensão **REST Client** instalada.
   - Execute a requisição **1.1 (`POST /login`)**, copie o valor de `"token"` retornado no JSON e cole na variável `@token` no topo do arquivo `requisicoes.http`.
   - Execute todas as demais requisições de sucesso (`200`, `201`, `204`) e de erro (`400`, `401`, `404`, `409`, `422`).

---

## Credenciais do Usuário de Teste (Login)

Para testar a rota `POST /login`, utilize qualquer uma das contas abaixo (cadastradas no `banco.sql` com hash gerado por `password_hash('123456', PASSWORD_DEFAULT)`):

| Nome | E-mail | Senha (texto puro) |
| :--- | :--- | :--- |
| Administrador PetCare | `admin@petcare.com.br` | `123456` |
| Recepção Clínica PetCare | `recepcao@petcare.com.br` | `123456` |

Exemplo de corpo para `POST http://localhost/petcare/login`:
```json
{
    "email": "admin@petcare.com.br",
    "senha": "123456"
}
```

---

## Decisão e Justificativa Técnica: Exclusão de Tutores (`ON DELETE RESTRICT`)

Na modelagem da chave estrangeira `pets.tutor_id -> tutores.id`, o grupo adotou a regra **`ON DELETE RESTRICT`** (impedindo a exclusão de um tutor que possua pets cadastrados e retornando **`409 Conflict`**).

### Justificativa:
1. **Proteção do Histórico Clínico e Prontuários Veterinários:** Em um sistema real de clínica veterinária, excluir acidentalmente o cadastro de um tutor jamais deve apagar em cascata (`ON DELETE CASCADE`) os animais sob sua tutela, pois isso destruiria registros clínicos, histórico de peso e rastreabilidade dos pacientes atendidos.
2. **Semântica REST e Código HTTP `409 Conflict`:** Quando um funcionário autenticado tenta executar `DELETE /tutores/{id}` para um tutor que ainda possui pets vinculados (por exemplo, o tutor `id = 1`), o MySQL bloqueia a operação pela constraint referencial (código `1451`) e a API captura a `PDOException`, respondendo:
   ```json
   {
       "detail": "Não é possível excluir tutor que possui pets vinculados"
   }
   ```
   com status HTTP **`409 Conflict`**, informando claramente ao cliente que é necessário transferir ou remover os pets antes de excluir o tutor.
3. **Teste de Exclusão Bem-Sucedida (`204 No Content`):** Caso o tutor não possua pets vinculados (como o tutor de exemplo `id = 6` ou um tutor recém-cadastrado), o `DELETE /tutores/{id}` conclui normalmente com status **`204 No Content`**.

---

## Resumo das Rotas Implementadas

| Método | Rota | Token | Sucesso | Erros Tratados |
| :--- | :--- | :---: | :--- | :--- |
| `POST` | `/login` | Não | `200` com `token`, `tipo` e `expira_em` | `400`, `401`, `422` |
| `POST` | `/logout` | **Sim** | `204` (apaga o token do banco) | `401` |
| `GET` | `/tutores` | Não | `200` com `total` e `tutores` | — |
| `GET` | `/tutores/{id}` | Não | `200` com os dados do tutor | `404` |
| `GET` | `/tutores/{id}/pets` | Não | `200` com os pets do tutor | `404` |
| `POST` | `/tutores` | **Sim** | `201` com o tutor criado e seu `id` | `400`, `401`, `409`, `422` |
| `PUT` | `/tutores/{id}` | **Sim** | `200` com o tutor atualizado | `400`, `401`, `404`, `409`, `422` |
| `DELETE` | `/tutores/{id}` | **Sim** | `204` (sem corpo) | `401`, `404`, `409` |
| `GET` | `/pets` | Não | `200` com `total` e `pets` (aceita `?especie=`) | `422` (se `?especie=` inválida) |
| `GET` | `/pets/{id}` | Não | `200` com dados do pet + `tutor_nome` (`JOIN`) | `404` |
| `POST` | `/pets` | **Sim** | `201` com o pet criado e seu `id` | `400`, `401`, `422` |
| `PUT` | `/pets/{id}` | **Sim** | `200` com o pet atualizado | `400`, `401`, `404`, `422` |
| `DELETE` | `/pets/{id}` | **Sim** | `204` (sem corpo) | `401`, `404` |
| `*` | *Qualquer outra* | — | — | `404` (`{"detail": "Rota não encontrada"}`) |
