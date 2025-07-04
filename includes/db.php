<?php
/**
 * Mass Mailer Database Class
 *
 * Handles database connection and provides basic CRUD operations
 * for the Mass Mailer plugin. Uses PDO for secure database interactions.
 *
 * Updated to use WordPress's global database constants and $wpdb->prefix.
 *
 * @package Mass_Mailer
 * @subpackage Includes
 */

// Ensure WordPress is loaded and global $wpdb is available.
// In a typical WordPress plugin, this file would be included after WordPress has initialized.
if (!defined('ABSPATH')) {
    // If ABSPATH is not defined, it means WordPress is not loaded.
    // In a real WordPress plugin, this file should only be loaded within the WP context.
    // For standalone testing, you might define it here, but it's not recommended for production WP plugins.
    // error_log('MassMailerDB: ABSPATH not defined. This file should be loaded within WordPress context.');
    die('This plugin file cannot be accessed directly. It must be loaded via WordPress.');
}

// Define the Mass Mailer table prefix using WordPress's global $wpdb object.
// This ensures consistency with other WordPress tables.
global $wpdb;
if (!defined('MM_TABLE_PREFIX')) {
    define('MM_TABLE_PREFIX', $wpdb->prefix . 'mm_');
}


class MassMailerDB {
    private $pdo;
    private static $instance = null;

    /**
     * Constructor: Establishes a PDO database connection using WordPress credentials.
     */
    private function __construct() {
        // Use WordPress's defined constants for database connection
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch rows as associative arrays
            PDO::ATTR_EMULATE_PREPARES   => false,                  // Disable emulation for better security and performance
        ];
        try {
            // Use DB_USER and DB_PASSWORD from wp-config.php
            $this->pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
        } catch (PDOException $e) {
            // In a real WordPress plugin, you would use wp_die() or trigger a WordPress error.
            // For now, we'll log and die as per the original structure.
            error_log('MassMailerDB: Database connection failed: ' . $e->getMessage());
            die('Mass Mailer: Database connection failed. Please check your WordPress configuration.');
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
     * Execute a SQL query.
     *
     * @param string $sql The SQL query string.
     * @param array $params An associative array of parameters for prepared statement.
     * @return PDOStatement The PDOStatement object on success.
     * @throws PDOException On query execution failure.
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log('MassMailerDB: Query failed: ' . $e->getMessage() . ' SQL: ' . $sql . ' Params: ' . json_encode($params));
            throw $e; // Re-throw to allow calling functions to handle
        }
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
     * Start a new database transaction.
     * @return bool True on success, false on failure.
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit the current database transaction.
     * @return bool True on success, false on failure.
     */
    public function commit() {
        return $this->pdo->commit();
    }

    /**
     * Roll back the current database transaction.
     * @return bool True on success, false on failure.
     */
    public function rollBack() {
        return $this->pdo->rollBack();
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
