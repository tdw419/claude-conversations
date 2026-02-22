<?php
/**
 * Import Engine for Claude conversations
 *
 * @package Claude_Conversations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Claude_Importer
 *
 * Imports Claude Code CLI conversations into WordPress posts.
 */
class Claude_Importer {

    /**
     * Category ID cache
     *
     * @var int|null
     */
    private $category_id = null;

    /**
     * Import all sessions from Claude directory
     *
     * @param string $claude_dir Base Claude directory (e.g., ~/.claude/projects/).
     * @return array|WP_Error Stats array with imported, skipped, errors counts, or WP_Error.
     */
    public function import_all(string $claude_dir) {
        $stats = array(
            'imported' => 0,
            'skipped' => 0,
            'errors' => 0,
            'error_details' => array(),
        );

        // Expand ~ to home directory
        $claude_dir = str_replace('~', getenv('HOME'), $claude_dir);

        // Validate directory path - prevent directory traversal
        if (preg_match('/\.\./', $claude_dir)) {
            return new WP_Error('invalid_path', 'Directory traversal not allowed in directory path.');
        }

        // Check if directory exists
        if (!is_dir($claude_dir)) {
            return new WP_Error('dir_not_found', sprintf('Claude directory not found: %s', $claude_dir));
        }

        // Find all .jsonl files recursively using RecursiveDirectoryIterator
        $files = array();
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($claude_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'jsonl') {
                    $files[] = $file->getPathname();
                }
            }
        } catch (Exception $e) {
            return new WP_Error('scan_error', sprintf('Failed to scan directory: %s', $e->getMessage()));
        }

        if (empty($files)) {
            return $stats;
        }

        // Sort files by modification time (newest first) for consistent ordering
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $batch_start_time = time();
        $max_execution_time = (int) ini_get('max_execution_time');
        // Reserve 5 seconds for cleanup
        $safe_execution_time = max(30, $max_execution_time - 5);

        foreach ($files as $filepath) {
            // Check execution time before each batch operation
            if ($max_execution_time > 0 && (time() - $batch_start_time) > $safe_execution_time) {
                $stats['error_details'][] = 'Import paused due to execution time limit. Some files not imported.';
                break;
            }

            // Extract project name from path (project is the top-level directory under projects/)
            $relative_path = str_replace($claude_dir, '', $filepath);
            $parts = explode('/', trim($relative_path, '/'));
            $project = isset($parts[0]) ? $parts[0] : 'unknown';

            $result = $this->import_session($filepath, $project);

            if ($result === true) {
                $stats['imported']++;
            } elseif ($result === 'skipped') {
                $stats['skipped']++;
            } elseif (is_wp_error($result)) {
                $stats['errors']++;
                $stats['error_details'][] = sprintf(
                    '%s: %s',
                    basename($filepath),
                    $result->get_error_message()
                );
            } else {
                $stats['errors']++;
            }
        }

        return $stats;
    }

    /**
     * Import a single session file
     *
     * @param string $filepath Path to JSONL file.
     * @param string $project Project name.
     * @return bool|string|WP_Error True on success, 'skipped' if duplicate, WP_Error on error.
     */
    public function import_session(string $filepath, string $project) {
        // Validate filepath - prevent directory traversal
        if (preg_match('/\.\./', $filepath)) {
            return new WP_Error('invalid_path', 'Directory traversal not allowed in file path.');
        }

        // Extract session_id from filename
        $session_id = basename($filepath, '.jsonl');

        // Validate session_id format (should be alphanumeric with dashes/underscores)
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $session_id)) {
            return new WP_Error('invalid_session_id', sprintf('Invalid session ID format: %s', $session_id));
        }

        // Check for existing post (duplicate detection)
        $existing = $this->find_existing_post($session_id);
        if ($existing !== false) {
            return 'skipped';
        }

        // Parse the file
        $parser = new Claude_JsonlParser($filepath);
        $conversation = $parser->parse();

        if (is_wp_error($conversation)) {
            return $conversation;
        }

        // Check if conversation has any messages
        if (empty($conversation['messages'])) {
            return 'skipped';
        }

        // Format with HTML formatter
        $formatter = new Claude_HtmlFormatter();
        $content = $formatter->format($conversation);
        $content = $formatter->get_css() . $content;

        // Extract title (already truncated to 80 chars by parser)
        $title = $parser->extract_title($conversation);

        // Create the post
        $post_id = $this->create_post($title, $content, $session_id, $project, $conversation['metadata']);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        return $post_id !== false;
    }

    /**
     * Find existing post by session_id
     *
     * @param string $session_id Session ID to search for.
     * @return int|false Post ID if found, false otherwise.
     */
    public function find_existing_post(string $session_id) {
        $query = new WP_Query(array(
            'post_type' => 'post',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'meta_query' => array(
                array(
                    'key' => '_claude_session_id',
                    'value' => $session_id,
                    'compare' => '=',
                ),
            ),
            'fields' => 'ids',
        ));

        if ($query->have_posts()) {
            return $query->posts[0];
        }

        return false;
    }

    /**
     * Create a WordPress post from conversation data
     *
     * @param string $title Post title.
     * @param string $content Post content (HTML).
     * @param string $session_id Session ID.
     * @param string $project Project name.
     * @param array $metadata Additional metadata.
     * @return int|WP_Error Post ID on success, WP_Error on failure.
     */
    public function create_post(string $title, string $content, string $session_id, string $project, array $metadata) {
        $category_id = $this->ensure_category();

        // Truncate title to 80 chars with ellipsis (safety check)
        if (strlen($title) > 80) {
            $title = substr($title, 0, 77) . '...';
        }

        // Sanitize inputs
        $title = sanitize_text_field($title);
        $content = wp_kses_post($content);
        $session_id = sanitize_key($session_id);
        $project = sanitize_file_name($project);

        // Set post date from conversation start time
        $post_date = current_time('mysql');
        if (!empty($metadata['start_time']) && $metadata['start_time'] > 0) {
            $post_date = date('Y-m-d H:i:s', $metadata['start_time']);
        }

        $post_data = array(
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish',
            'post_category' => array($category_id),
            'post_type' => 'post',
            'post_date' => $post_date,
            'post_date_gmt' => get_gmt_from_date($post_date),
        );

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Add post meta
        update_post_meta($post_id, '_claude_session_id', $session_id);
        update_post_meta($post_id, '_claude_project', $project);
        update_post_meta($post_id, '_claude_imported_at', current_time('mysql'));

        if (!empty($metadata['git_branch'])) {
            update_post_meta($post_id, '_claude_branch', $metadata['git_branch']);
        }

        if (!empty($metadata['start_time'])) {
            update_post_meta($post_id, '_claude_start_time', date('Y-m-d H:i:s', $metadata['start_time']));
        }

        if (!empty($metadata['end_time'])) {
            update_post_meta($post_id, '_claude_end_time', date('Y-m-d H:i:s', $metadata['end_time']));
        }

        return $post_id;
    }

    /**
     * Ensure the "Claude Conversations" category exists
     *
     * @return int Category ID.
     */
    public function ensure_category(): int {
        if ($this->category_id !== null) {
            return $this->category_id;
        }

        $category_name = 'Claude Conversations';
        $category_slug = 'claude-conversations';

        // Check if category exists
        $existing = get_term_by('name', $category_name, 'category');

        if ($existing) {
            $this->category_id = $existing->term_id;
            return $this->category_id;
        }

        // Create category using wp_insert_term (available in CLI context)
        $result = wp_insert_term(
            $category_name,
            'category',
            array(
                'slug' => $category_slug,
                'description' => 'Conversations imported from Claude Code CLI',
            )
        );

        if (is_wp_error($result)) {
            // Fallback to uncategorized
            $this->category_id = 1;
        } else {
            $this->category_id = $result['term_id'];
        }

        return $this->category_id;
    }
}
