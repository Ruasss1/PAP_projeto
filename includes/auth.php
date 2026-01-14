<?php
// includes/auth.php
// Authentication and authorization functions

require_once __DIR__ . '/functions.php';

class AuthManager
{
    private $pdo;
    private $session_timeout = 3600; // 1 hour
    
    public function __construct()
    {
        $this->pdo = db_connect();
    }
    
    /**
     * Register a new user
     */
    public function register($email, $password, $name, $role_id = null)
    {
        // Check if email already exists
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email já registado'];
        }
        
        // Default role: caixa
        if ($role_id === null) {
            $stmt = $this->pdo->prepare('SELECT id FROM roles WHERE name = ?');
            $stmt->execute(['caixa']);
            $role = $stmt->fetch();
            $role_id = $role['id'] ?? 1;
        }
        
        // Hash password with bcrypt
        $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        
        try {
            $stmt = $this->pdo->prepare('INSERT INTO users (email, password_hash, name, role_id, active) VALUES (?, ?, ?, ?, 1)');
            $stmt->execute([$email, $password_hash, $name, $role_id]);
            return ['success' => true, 'user_id' => $this->pdo->lastInsertId()];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Erro ao registar utilizador'];
        }
    }
    
    /**
     * Authenticate user with email and password
     */
    public function login($email, $password)
    {
        $stmt = $this->pdo->prepare('SELECT id, email, password_hash, role_id, active FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user || !$user['active']) {
            // Log failed attempt
            $this->log_audit('login', 'users', null, 'FAILED', $_SERVER['REMOTE_ADDR'] ?? '', 'Invalid email or inactive user');
            return ['success' => false, 'message' => 'Email ou senha inválidos'];
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            $this->log_audit('login', 'users', null, 'FAILED', $_SERVER['REMOTE_ADDR'] ?? '', 'Invalid password');
            return ['success' => false, 'message' => 'Email ou senha inválidos'];
        }
        
        // Create session
        $session_id = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', time() + 3600); // 1 hour from now
        $stmt = $this->pdo->prepare('INSERT INTO sessions (id, user_id, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            $session_id,
            $user['id'],
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $expires_at
        ]);
        
        // Update last login
        $stmt = $this->pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
        $stmt->execute([$user['id']]);
        
        // Log successful login
        $this->log_audit('login', 'users', $user['id'], 'SUCCESS', $_SERVER['REMOTE_ADDR'] ?? '', 'User logged in');
        
        // Store in session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['session_id'] = $session_id;
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['email'] = $user['email'];
        
        return ['success' => true, 'session_id' => $session_id, 'user_id' => $user['id']];
    }
    
    /**
     * Check if user is authenticated
     */
    public function is_authenticated()
    {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_id'])) {
            return false;
        }
        
        // Verify session exists and is not expired
        $stmt = $this->pdo->prepare('SELECT id FROM sessions WHERE id = ? AND user_id = ? AND expires_at > NOW()');
        $stmt->execute([$_SESSION['session_id'], $_SESSION['user_id']]);
        
        if (!$stmt->fetch()) {
            $this->logout();
            return false;
        }
        
        // Update last activity
        $stmt = $this->pdo->prepare('UPDATE sessions SET last_activity = UNIX_TIMESTAMP() WHERE id = ?');
        $stmt->execute([$_SESSION['session_id']]);
        
        return true;
    }
    
    /**
     * Get current user info
     */
    public function get_current_user()
    {
        if (!$this->is_authenticated()) {
            return null;
        }
        
        $stmt = $this->pdo->prepare('SELECT u.id, u.name, u.email, u.role_id, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
    
    /**
     * Check if user has permission
     */
    public function has_permission($resource, $action)
    {
        $user = $this->get_current_user();
        if (!$user) {
            return false;
        }
        
        // Admin has all permissions
        if ($user['role_name'] === 'admin') {
            return true;
        }
        
        // Check specific permission
        $stmt = $this->pdo->prepare('SELECT id FROM permissions WHERE role_id = ? AND (resource = ? OR resource = "all") AND (action = ? OR action = "all")');
        $stmt->execute([$user['role_id'], $resource, $action]);
        return $stmt->fetch() !== false;
    }
    
    /**
     * Require authentication and permission
     */
    public function require_auth($resource = null, $action = null)
    {
        if (!$this->is_authenticated()) {
            http_response_code(401);
            die(json_encode(['error' => 'Não autenticado']));
        }
        
        if ($resource && $action && !$this->has_permission($resource, $action)) {
            http_response_code(403);
            die(json_encode(['error' => 'Permissão negada']));
        }
    }
    
    /**
     * Logout user
     */
    public function logout()
    {
        if (isset($_SESSION['session_id'])) {
            $this->pdo->prepare('DELETE FROM sessions WHERE id = ?')->execute([$_SESSION['session_id']]);
            $this->log_audit('logout', 'users', $_SESSION['user_id'] ?? null, 'SUCCESS', $_SERVER['REMOTE_ADDR'] ?? '', 'User logged out');
        }
        session_destroy();
        return true;
    }
    
    /**
     * Log audit event
     */
    public function log_audit($action, $resource, $resource_id, $status, $ip_address, $changes = null)
    {
        $user_id = $_SESSION['user_id'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $this->pdo->prepare('INSERT INTO audit_logs (user_id, action, resource, resource_id, changes, ip_address, user_agent, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        return $stmt->execute([
            $user_id,
            $action,
            $resource,
            $resource_id,
            is_array($changes) ? json_encode($changes) : $changes,
            $ip_address,
            $user_agent,
            $status
        ]);
    }
    
    /**
     * Get audit logs
     */
    public function get_audit_logs($limit = 100, $resource = null, $user_id = null)
    {
        $sql = 'SELECT a.*, u.name as user_name FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id WHERE 1=1';
        $params = [];
        
        if ($resource) {
            $sql .= ' AND a.resource = ?';
            $params[] = $resource;
        }
        
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

// Global auth instance
$auth = new AuthManager();
