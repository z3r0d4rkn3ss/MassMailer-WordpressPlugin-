<?php
/**
 * Mass Mailer Template Manager
 *
 * Provides functions for managing email templates (CRUD operations).
 *
 * @package Mass_Mailer
 * @subpackage Includes
 */

// Ensure DB class is loaded
if (!class_exists('MassMailerDB')) {
    require_once dirname(__FILE__) . '/db.php';
}

class MassMailerTemplateManager {
    private $db;
    private $table_name;

    public function __construct() {
        $this->db = MassMailerDB::getInstance();
        $this->table_name = MM_TABLE_PREFIX . 'templates'; // Assuming a new 'mm_templates' table
    }

    /**
     * Creates the mm_templates table if it doesn't exist.
     * This would typically be called during plugin activation.
     */
    public function createTemplateTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->table_name}` (
            `template_id` INT AUTO_INCREMENT PRIMARY KEY,
            `template_name` VARCHAR(255) NOT NULL UNIQUE,
            `template_subject` VARCHAR(255) NOT NULL,
            `template_html` LONGTEXT,
            `template_text` LONGTEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        try {
            $this->db->query($sql);
            error_log('MassMailerTemplateManager: mm_templates table created/checked successfully.');
            return true;
        } catch (PDOException $e) {
            error_log('MassMailerTemplateManager: Error creating mm_templates table: ' . $e->getMessage());
            return false;
        }
    }


    /**
     * Create a new email template.
     *
     * @param string $name The name of the template.
     * @param string $subject The default subject line for the template.
     * @param string $html_content The HTML content of the template.
     * @param string $text_content The plain text content of the template (optional).
     * @return int|false The ID of the newly created template on success, false on failure.
     */
    public function createTemplate($name, $subject, $html_content, $text_content = '') {
        if (empty($name) || empty($subject) || empty($html_content)) {
            error_log('MassMailerTemplateManager: Template name, subject, and HTML content cannot be empty.');
            return false;
        }

        // Check if template with this name already exists
        $existing_template = $this->db->fetch(
            "SELECT template_id FROM {$this->table_name} WHERE template_name = :template_name",
            [':template_name' => $name]
        );
        if ($existing_template) {
            error_log('MassMailerTemplateManager: Template with this name already exists.');
            return false;
        }

        try {
            $sql = "INSERT INTO {$this->table_name} (template_name, template_subject, template_html, template_text) VALUES (:template_name, :template_subject, :template_html, :template_text)";
            $this->db->query($sql, [
                ':template_name' => $name,
                ':template_subject' => $subject,
                ':template_html' => $html_content,
                ':template_text' => $text_content
            ]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('MassMailerTemplateManager: Error creating template: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a single email template by ID or name.
     *
     * @param int|string $identifier The template ID or template name.
     * @return array|false The template data on success, false if not found.
     */
    public function getTemplate($identifier) {
        $field = is_numeric($identifier) ? 'template_id' : 'template_name';
        $sql = "SELECT * FROM {$this->table_name} WHERE {$field} = :identifier";
        try {
            return $this->db->fetch($sql, [':identifier' => $identifier]);
        } catch (PDOException $e) {
            error_log('MassMailerTemplateManager: Error getting template: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all email templates.
     *
     * @return array An array of all template data, or an empty array.
     */
    public function getAllTemplates() {
        $sql = "SELECT * FROM {$this->table_name} ORDER BY template_name ASC";
        try {
            return $this->db->fetchAll($sql);
        } catch (PDOException $e) {
            error_log('MassMailerTemplateManager: Error getting all templates: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Update an existing email template.
     *
     * @param int $template_id The ID of the template to update.
     * @param string $name The new name of the template.
     * @param string $subject The new default subject line.
     * @param string $html_content The new HTML content.
     * @param string $text_content The new plain text content.
     * @return bool True on success, false on failure.
     */
    public function updateTemplate($template_id, $name, $subject, $html_content, $text_content = '') {
        if (empty($template_id) || empty($name) || empty($subject) || empty($html_content)) {
            error_log('MassMailerTemplateManager: Template ID, name, subject, and HTML content cannot be empty for update.');
            return false;
        }

        // Check if template with this name already exists for a different ID
        $existing_template = $this->db->fetch(
            "SELECT template_id FROM {$this->table_name} WHERE template_name = :template_name AND template_id != :template_id",
            [':template_name' => $name, ':template_id' => $template_id]
        );
        if ($existing_template) {
            error_log('MassMailerTemplateManager: Another template with this name already exists.');
            return false;
        }

        try {
            $sql = "UPDATE {$this->table_name} SET template_name = :template_name, template_subject = :template_subject, template_html = :template_html, template_text = :template_text WHERE template_id = :template_id";
            $stmt = $this->db->query($sql, [
                ':template_name' => $name,
                ':template_subject' => $subject,
                ':template_html' => $html_content,
                ':template_text' => $text_content,
                ':template_id' => $template_id
            ]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('MassMailerTemplateManager: Error updating template: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete an email template.
     *
     * @param int $template_id The ID of the template to delete.
     * @return bool True on success, false on failure.
     */
    public function deleteTemplate($template_id) {
        if (empty($template_id)) {
            error_log('MassMailerTemplateManager: Template ID cannot be empty for delete.');
            return false;
        }
        try {
            $sql = "DELETE FROM {$this->table_name} WHERE template_id = :template_id";
            $stmt = $this->db->query($sql, [':template_id' => $template_id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('MassMailerTemplateManager: Error deleting template: ' . $e->getMessage());
            return false;
        }
    }
}
