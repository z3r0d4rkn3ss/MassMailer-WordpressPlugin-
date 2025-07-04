<?php
/**
 * Mass Mailer Database Class
 *
 * Handles database connection and provides basic CRUD operations
 * for the Mass Mailer plugin. Uses PDO for secure database interactions.
 *
 * @package Mass_Mailer
 * @subpackage Includes
 */

// Ensure the config file is loaded
if (!defined('DB_HOST')) {
    require_once dirname(__FILE__) . '/../config.php';
}

class MassMailerDB {
    private $pdo;
    private static $instance = null;

    /**
     * Constructor: Establishes a PDO database connection.
     */
    private function __construct() {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch rows as associative arrays
            PDO::ATTR_EMULATE_PREPARES   => false,                  // Disable emulation for better security and performance
        ];
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // In a real application, log this error and display a user-friendly message
            error_log('Database connection failed: ' . $e->getMessage());
            die('Database connection failed. Please try again later.');
        }
    }

    /**
     * Get the singleton instance of the MassMailerDB class.
     *
     * @return MassMailerDB
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Execute a prepared SQL query.
     *
     * @param string $sql The SQL query string.
     * @param array $params An associative array of parameters for the prepared statement.
     * @return PDOStatement The PDOStatement object.
     */
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch a single row from the database.
     *
     * @param string $sql The SQL query string.
     * @param array $params An associative array of parameters.
     * @return array|false The fetched row as an associative array, or false if no row.
     */
    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    /**
     * Fetch all rows from the database.
     *
     * @param string $sql The SQL query string.
     * @param array $params An associative array of parameters.
     * @return array An array of fetched rows, or an empty array.
     */
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Get the ID of the last inserted row.
     *
     * @return string The ID of the last inserted row.
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    /**
     * Prevent cloning of the instance.
     */
    private function __clone() {}

    /**
     * Prevent unserialization of the instance.
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize a singleton.");
    }
}

// Example usage (for testing, remove in production code if not needed)
/*
try {
    $db = MassMailerDB::getInstance();
    // $lists = $db->fetchAll("SELECT * FROM " . MM_TABLE_PREFIX . "lists");
    // print_r($lists);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
*/
?>
