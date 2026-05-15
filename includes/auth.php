<?php
/**
 * SISTEMA DE AUTENTICAÇÃO E AUTORIZAÇÃO
 * Ficheiro: includes/auth.php
 * 
 * Gere login, logout, sessões e permissões de utilizadores
 * Usa bcrypt para hash de passwords
 */

require_once __DIR__ . '/functions.php';

/**
 * Classe AuthManager
 * Gere toda a autenticação e autorização do sistema
 */
class AuthManager
{
    // Conexão à base de dados
    private $pdo;
    
    // Tempo de expiração da sessão (1 hora)
    private $session_timeout = 3600;
    
    /**
     * Construtor - inicializa conexão à base de dados
     */
    public function __construct()
    {
        $this->pdo = db_connect();
    }
    
    /**
     * Regista um novo utilizador no sistema
     * 
     * @param string $email Email do utilizador
     * @param string $password Password em texto limpo
     * @param string $name Nome do utilizador
     * @param int|null $role_id ID do cargo (null = caixa)
     * @return array Resultado com success e message/user_id
     */
    public function register($email, $password, $name, $role_id = null)
    {
        // Verifica se email já existe
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email já registado'];
        }
        
        // Define cargo padrão: caixa
        if ($role_id === null) {
            $stmt = $this->pdo->prepare('SELECT id FROM roles WHERE name = ?');
            $stmt->execute(['caixa']);
            $role = $stmt->fetch();
            $role_id = $role['id'] ?? 1;
        }
        
        // Cria hash seguro da password (bcrypt com custo 12)
        $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        
        try {
            // Insere utilizador na base de dados
            $stmt = $this->pdo->prepare('INSERT INTO users (email, password_hash, name, role_id, active) VALUES (?, ?, ?, ?, 1)');
            $stmt->execute([$email, $password_hash, $name, $role_id]);
            return ['success' => true, 'user_id' => $this->pdo->lastInsertId()];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Erro ao registar utilizador'];
        }
    }
    
    /**
     * Autentica utilizador com email e password
     * 
     * @param string $email Email do utilizador
     * @param string $password Password em texto limpo
     * @return array Resultado com success e session_id/message
     */
    public function login($email, $password)
    {
        // Procura utilizador pelo email
        $stmt = $this->pdo->prepare('SELECT id, email, password_hash, role_id, active FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        // Verifica se utilizador existe e está ativo
        if (!$user || !$user['active']) {
            $this->log_audit('login', 'users', null, 'FAILED', $_SERVER['REMOTE_ADDR'] ?? '', 'Email inválido ou utilizador inativo');
            return ['success' => false, 'message' => 'Email ou senha inválidos'];
        }
        
        // Verifica password
        if (!password_verify($password, $user['password_hash'])) {
            $this->log_audit('login', 'users', null, 'FAILED', $_SERVER['REMOTE_ADDR'] ?? '', 'Password inválida');
            return ['success' => false, 'message' => 'Email ou senha inválidos'];
        }
        
        // Cria nova sessão
        $session_id = bin2hex(random_bytes(32));  // Token de 64 caracteres
        
        // Guarda sessão na base de dados (usa NOW() do MySQL para evitar problemas de timezone)
        $stmt = $this->pdo->prepare('INSERT INTO sessions (id, user_id, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))');
        $stmt->execute([
            $session_id,
            $user['id'],
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        // Atualiza data do último login
        $stmt = $this->pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
        $stmt->execute([$user['id']]);
        
        // Regista login no log de auditoria
        $this->log_audit('login', 'users', $user['id'], 'SUCCESS', $_SERVER['REMOTE_ADDR'] ?? '', 'Utilizador entrou no sistema');
        
        // Guarda dados na sessão PHP
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['session_id'] = $session_id;
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['email'] = $user['email'];
        
        return ['success' => true, 'session_id' => $session_id, 'user_id' => $user['id']];
    }
    
    /**
     * Verifica se utilizador está autenticado
     * 
     * @return bool True se autenticado, false caso contrário
     */
    public function is_authenticated()
    {
        // Verifica se existem dados de sessão
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_id'])) {
            return false;
        }
        
        // Verifica se sessão existe e não expirou
        $stmt = $this->pdo->prepare('SELECT id FROM sessions WHERE id = ? AND user_id = ? AND expires_at > NOW()');
        $stmt->execute([$_SESSION['session_id'], $_SESSION['user_id']]);
        
        if (!$stmt->fetch()) {
            // Sessão expirada - faz logout
            $this->logout();
            return false;
        }
        
        // Atualiza última atividade
        $stmt = $this->pdo->prepare('UPDATE sessions SET last_activity = NOW() WHERE id = ?');
        $stmt->execute([$_SESSION['session_id']]);
        
        return true;
    }
    
    /**
     * Obtém informações do utilizador atual
     * 
     * @return array|null Dados do utilizador ou null se não autenticado
     */
    public function get_current_user()
    {
        if (!$this->is_authenticated()) {
            return null;
        }
        
        // Obtém dados do utilizador com nome do cargo
        $stmt = $this->pdo->prepare('SELECT u.id, u.name, u.email, u.role_id, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
    
    /**
     * Verifica se utilizador tem permissão para ação
     * 
     * @param string $resource Recurso (módulo) a aceder
     * @param string $action Ação a executar (view, create, edit, delete)
     * @return bool True se tem permissão
     */
    public function has_permission($resource, $action)
    {
        $user = $this->get_current_user();
        if (!$user) {
            return false;
        }
        
        // Admin tem todas as permissões
        if (strtolower($user['role_name']) === 'admin') {
            return true;
        }
        
        // Verifica permissão específica na base de dados
        $stmt = $this->pdo->prepare('SELECT id FROM permissions WHERE role_id = ? AND (module = ? OR module = "all") AND (action = ? OR action = "all")');
        $stmt->execute([$user['role_id'], $resource, $action]);
        return $stmt->fetch() !== false;
    }
    
    /**
     * Exige autenticação e permissão (para APIs)
     * Termina execução se não autorizado
     * 
     * @param string|null $resource Recurso requerido
     * @param string|null $action Ação requerida
     */
    public function require_auth($resource = null, $action = null)
    {
        // Verifica autenticação
        if (!$this->is_authenticated()) {
            http_response_code(401);
            die(json_encode(['error' => 'Não autenticado']));
        }
        
        // Verifica permissão se especificada
        if ($resource && $action && !$this->has_permission($resource, $action)) {
            http_response_code(403);
            die(json_encode(['error' => 'Permissão negada']));
        }
    }
    
    /**
     * Termina sessão do utilizador (logout)
     * 
     * @return bool Sempre retorna true
     */
    public function logout()
    {
        // Remove sessão da base de dados
        if (isset($_SESSION['session_id'])) {
            $this->pdo->prepare('DELETE FROM sessions WHERE id = ?')->execute([$_SESSION['session_id']]);
            $this->log_audit('logout', 'users', $_SESSION['user_id'] ?? null, 'SUCCESS', $_SERVER['REMOTE_ADDR'] ?? '', 'Utilizador saiu do sistema');
        }
        
        // Destrói sessão PHP
        session_destroy();
        return true;
    }
    
    /**
     * Regista evento no log de auditoria
     * 
     * @param string $action Ação executada
     * @param string $resource Recurso afetado
     * @param int|null $resource_id ID do recurso
     * @param string $status Estado (SUCCESS, FAILED)
     * @param string $ip_address IP do utilizador
     * @param string|null $changes Descrição das alterações
     * @return bool Sucesso ou falha
     */
    public function log_audit($action, $resource, $resource_id, $status, $ip_address, $changes = null)
    {
        // Desativado temporariamente - tabela será criada mais tarde
        return true;
    }
    
    /**
     * Obtém logs de auditoria
     * 
     * @param int $limit Número máximo de registos
     * @param string|null $resource Filtrar por recurso
     * @param int|null $user_id Filtrar por utilizador
     * @return array Lista de logs
     */
    public function get_audit_logs($limit = 100, $resource = null, $user_id = null)
    {
        $sql = 'SELECT a.*, u.name as user_name FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id WHERE 1=1';
        $params = [];
        
        // Adiciona filtro de recurso
        if ($resource) {
            $sql .= ' AND a.resource = ?';
            $params[] = $resource;
        }
        
        // Adiciona filtro de utilizador
        if ($user_id) {
            $sql .= ' AND a.user_id = ?';
            $params[] = $user_id;
        }
        
        $sql .= ' ORDER BY a.created_at DESC LIMIT ' . (int)$limit;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}

// Instância global do gestor de autenticação
$auth = new AuthManager();
