<?php
/**
 * Mass Mailer Authentication Class
 *
 * Handles user login, logout, session management, and password hashing.
 *
 * @package Mass_Mailer
 * @subpackage Includes
 */

// Ensure DB class is loaded
if (!class_exists('MassMailerDB')) {
    require_once dirname(__FILE__) . '/db.php';
}

class MassMailerAuth {
    private $db;
    private $users_table;

    public function __construct() {
        $this->db = MassMailerDB::getInstance();
        $this->users_table = MM_TABLE_PREFIX . 'users'; // New table for users
    }

    /**
     * Creates the mm_users table if it doesn't exist.
     * This would typically be called during plugin activation.
     */
    public function createUsersTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->users_table}` (
            `user_id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(100) NOT NULL UNIQUE,
            `password_hash` VARCHAR(255) NOT NULL,
            `role` ENUM('admin', 'editor') DEFAULT 'editor', -- Example roles
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        try {
            $this->db->query($sql);
            error_log('MassMailerAuth: mm_users table created/checked successfully.');

            // Optional: Create a default admin user if no users exist
            if ($this->getUserByUsername('admin') === false) {
                $this->createUser('admin', 'password123', 'admin'); // IMPORTANT: Change default password!
                error_log('MassMailerAuth: Default admin user created. Please change password!');
            }
            return true;
        } catch (PDOException $e) {
            error_log('MassMailerAuth: Error creating mm_users table: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Hashes a password using a strong, one-way hashing algorithm.
     *
     * @param string $password The plain-text password.
     * @return string The hashed password.
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * Verifies a plain-text password against a hashed password.
     *
     * @param string $password The plain-text password.
     * @param string $hash The hashed password.
     * @return bool True if the password matches the hash, false otherwise.
     */
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Creates a new user.
     *
     * @param string $username The username.
     * @param string $password The plain-text password.
     * @param string $role The user's role.
     * @return int|false The new user's ID on success, false on failure.
     */
    public function createUser($username, $password, $role = 'editor') {
        if (empty($username) || empty($password)) {
            error_log('MassMailerAuth: Username and password cannot be empty.');
            return false;
        }
        if ($this->getUserByUsername($username)) {
            error_log('MassMailerAuth: Username already exists.');
            return false;
        }

        $password_hash = $this->hashPassword($password);
        try {
            $sql = "INSERT INTO {$this->users_table} (username, password_hash, role) VALUES (:username, :password_hash, :role)";
            $this->db->query($sql, [
                ':username' => $username,
                ':password_hash' => $password_hash,
                ':role' => $role
            ]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('MassMailerAuth: Error creating user: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves a user by username.
     *
     * @param string $username The username.
     * @return array|false User data on success, false if not found.
     */
    public function getUserByUsername($username) {
        $sql = "SELECT * FROM {$this->users_table} WHERE username = :username";
        try {
            return $this->db->fetch($sql, [':username' => $username]);
        } catch (PDOException $e) {
            error_log('MassMailerAuth: Error getting user by username: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Attempts to log in a user.
     *
     * @param string $username The username.
     * @param string $password The plain-text password.
     * @return bool True on successful login, false otherwise.
     */
    public function login($username, $password) {
        $user = $this->getUserByUsername($username);
        if ($user && $this->verifyPassword($password, $user['password_hash'])) {
            // Start session if not already started
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            session_regenerate_id(true); // Regenerate session ID for security
            error_log('MassMailerAuth: User ' . $username . ' logged in successfully.');
            return true;
        }
        error_log('MassMailerAuth: Login failed for user ' . $username . '.');
        return false;
    }

    /**
     * Logs out the current user.
     */
    public function logout() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = []; // Clear all session variables
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy(); // Destroy the session
        error_log('MassMailerAuth: User logged out.');
    }

    /**
     * Checks if a user is currently logged in.
     *
     * @return bool True if logged in, false otherwise.
     */
    public function isLoggedIn() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_id']);
    }

    /**
     * Gets the currently logged-in user's data.
     *
     * @return array|false User data if logged in, false otherwise.
     */
    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            return [
                'user_id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'role' => $_SESSION['role']
            ];
        }
        return false;
    }

    /**
     * Checks if the current user has a specific role or higher.
     *
     * @param string $required_role The role to check against (e.g., 'admin', 'editor').
     * @return bool True if the user has the required role or higher, false otherwise.
     */
    public function hasRole($required_role) {
        $user = $this->getCurrentUser();
        if (!$user) {
            return false;
        }
        $roles = ['editor' => 1, 'admin' => 2]; // Define hierarchy
        return isset($roles[$user['role']]) && isset($roles[$required_role]) && $roles[$user['role']] >= $roles[$required_role];
    }
}
