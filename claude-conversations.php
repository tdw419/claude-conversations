<?php
/**
 * Plugin Name: Claude Conversations
 * Description: Import Claude Code CLI conversations from ~/.claude/projects/ into WordPress as formatted posts with syntax highlighting
 * Version: 1.0.0
 * Author: Geometry OS
 *
 * This plugin imports Claude Code CLI conversation sessions (JSONL format)
 * into WordPress posts with:
 * - User and assistant message formatting
 * - Thinking block extraction and display
 * - Prism.js syntax highlighting for code blocks
 * - Session metadata preservation (project, branch, timestamps)
 *
 * "Every conversation is a learning artifact worth preserving."
 *
 * @package Claude_Conversations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include required class files
require_once plugin_dir_path(__FILE__) . 'includes/class-jsonl-parser.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-html-formatter.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-importer.php';

/**
 * Class Claude_Conversations_Admin
 *
 * Main admin class for the Claude Conversations plugin.
 */
class Claude_Conversations_Admin {

    /**
     * Initialize the plugin
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_init', array($this, 'handle_import'));
        add_action('admin_init', array($this, 'test_parse'));
    }

    /**
     * Add admin menu page
     */
    public function add_menu() {
        add_menu_page(
            'Claude Chats',
            'Claude Chats',
            'manage_options',
            'claude-conversations',
            array($this, 'render_page'),
            'dashicons-format-chat',
            30
        );
    }

    /**
     * Get the Claude projects directory path
     *
     * @return string Expanded path
     */
    private function get_claude_dir(): string {
        return str_replace('~', getenv('HOME'), '~/.claude/projects/');
    }

    /**
     * Count total session files
     *
     * @return int Number of .jsonl files
     */
    private function count_sessions(): int {
        $claude_dir = $this->get_claude_dir();
        $pattern = rtrim($claude_dir, '/') . '/*/*.jsonl';
        $files = glob($pattern);
        return $files ? count($files) : 0;
    }

    /**
     * Count imported posts
     *
     * @return int Number of posts with _claude_session_id meta
     */
    private function count_imported(): int {
        $query = new WP_Query(array(
            'post_type' => 'post',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_claude_session_id',
                    'compare' => 'EXISTS',
                ),
            ),
            'fields' => 'ids',
        ));
        return $query->found_posts;
    }

    /**
     * Render the admin page
     */
    public function render_page() {
        // Get counts
        $claude_dir = $this->get_claude_dir();
        $session_count = $this->count_sessions();
        $imported_count = $this->count_imported();

        ?>
        <div class="wrap">
            <h1>Claude Conversations Importer</h1>

            <?php
            // Display admin notices
            if (isset($_GET['claude_imported'])) {
                $imported = intval($_GET['claude_imported']);
                $skipped = intval($_GET['claude_skipped']);
                $errors = intval($_GET['claude_errors']);
                $notice_class = ($errors > 0) ? 'notice-warning' : 'notice-success';
                echo '<div class="notice ' . esc_attr($notice_class) . ' is-dismissible"><p>';
                echo esc_html(sprintf(
                    'Import complete: %d imported, %d skipped (duplicates), %d errors.',
                    $imported, $skipped, $errors
                ));
                echo '</p></div>';
            }

            // Display error notices
            if (isset($_GET['claude_error'])) {
                $error_type = sanitize_key($_GET['claude_error']);
                $error_msg = isset($_GET['claude_error_msg']) ? sanitize_text_field(urldecode($_GET['claude_error_msg'])) : '';
                echo '<div class="notice notice-error is-dismissible"><p>';
                echo '<strong>Error:</strong> ';
                switch ($error_type) {
                    case 'no_files':
                        echo 'No .jsonl files found in the Claude directory.';
                        break;
                    case 'parse_error':
                        echo 'Failed to parse session file.';
                        break;
                    case 'import_error':
                        echo esc_html($error_msg) ?: 'An error occurred during import.';
                        break;
                    case 'empty_conversation':
                        echo 'The conversation has no messages.';
                        break;
                    default:
                        echo esc_html($error_msg) ?: 'An unknown error occurred.';
                }
                echo '</p></div>';
            }
            ?>

            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>Status</h2>
                <p>Import Claude Code CLI conversations into WordPress posts.</p>

                <table class="form-table">
                    <tr>
                        <th scope="row">Claude Directory</th>
                        <td><code><?php echo esc_html($claude_dir); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row">Session Count</th>
                        <td><?php echo esc_html($session_count); ?> .jsonl files found</td>
                    </tr>
                    <tr>
                        <th scope="row">Imported Count</th>
                        <td><?php echo esc_html($imported_count); ?> posts imported</td>
                    </tr>
                </table>

                <h3>Actions</h3>

                <!-- Import All Sessions Form -->
                <form method="post" action="" style="margin-bottom: 20px;">
                    <?php wp_nonce_field('claude_import_all', 'claude_import_nonce'); ?>
                    <input type="hidden" name="claude_action" value="import_all">
                    <button type="submit" class="button button-primary">
                        Import All Sessions
                    </button>
                    <p class="description">
                        Import all .jsonl sessions from the Claude directory. Duplicates will be skipped.
                    </p>
                </form>

                <!-- Test Parse First Session Form -->
                <form method="post" action="">
                    <?php wp_nonce_field('claude_test_parse', 'claude_test_nonce'); ?>
                    <input type="hidden" name="claude_action" value="test_parse">
                    <button type="submit" class="button">
                        Test Parse First Session
                    </button>
                    <p class="description">
                        Parse and preview the first .jsonl file found without creating a post.
                    </p>
                </form>

                <?php
                // Display test parse preview if available
                if (isset($_GET['claude_preview']) && $_GET['claude_preview'] === '1') {
                    $preview = get_transient('claude_preview_html');
                    if ($preview) {
                        echo '<h3>Preview</h3>';
                        echo '<div style="background: #fff; border: 1px solid #ccc; padding: 15px; max-height: 500px; overflow: auto;">';
                        echo $preview;
                        echo '</div>';
                        delete_transient('claude_preview_html');
                    }
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Handle import all sessions action
     */
    public function handle_import() {
        // Check if this is our action
        if (!isset($_POST['claude_action']) || $_POST['claude_action'] !== 'import_all') {
            return;
        }

        // Verify nonce
        if (!isset($_POST['claude_import_nonce']) || !wp_verify_nonce($_POST['claude_import_nonce'], 'claude_import_all')) {
            wp_die('Security check failed');
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        // Run import
        $importer = new Claude_Importer();
        $stats = $importer->import_all('~/.claude/projects/');

        // Handle WP_Error from import_all
        if (is_wp_error($stats)) {
            wp_redirect(add_query_arg(array(
                'page' => 'claude-conversations',
                'claude_error' => 'import_error',
                'claude_error_msg' => urlencode($stats->get_error_message()),
            ), admin_url('admin.php')));
            exit;
        }

        // Redirect with stats
        wp_redirect(add_query_arg(array(
            'page' => 'claude-conversations',
            'claude_imported' => $stats['imported'],
            'claude_skipped' => $stats['skipped'],
            'claude_errors' => $stats['errors'],
        ), admin_url('admin.php')));
        exit;
    }

    /**
     * Handle test parse action
     */
    public function test_parse() {
        // Check if this is our action
        if (!isset($_POST['claude_action']) || $_POST['claude_action'] !== 'test_parse') {
            return;
        }

        // Verify nonce
        if (!isset($_POST['claude_test_nonce']) || !wp_verify_nonce($_POST['claude_test_nonce'], 'claude_test_parse')) {
            wp_die('Security check failed');
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        // Find first .jsonl file
        $claude_dir = $this->get_claude_dir();
        $pattern = rtrim($claude_dir, '/') . '/*/*.jsonl';
        $files = glob($pattern);

        if (empty($files)) {
            wp_redirect(add_query_arg(array(
                'page' => 'claude-conversations',
                'claude_error' => 'no_files',
            ), admin_url('admin.php')));
            exit;
        }

        // Parse first file with error handling
        $filepath = $files[0];
        $parser = new Claude_JsonlParser($filepath);
        $conversation = $parser->parse();

        if (is_wp_error($conversation)) {
            wp_redirect(add_query_arg(array(
                'page' => 'claude-conversations',
                'claude_error' => 'parse_error',
                'claude_error_msg' => urlencode($conversation->get_error_message()),
            ), admin_url('admin.php')));
            exit;
        }

        // Check for empty conversation
        if (empty($conversation['messages'])) {
            wp_redirect(add_query_arg(array(
                'page' => 'claude-conversations',
                'claude_error' => 'empty_conversation',
            ), admin_url('admin.php')));
            exit;
        }

        // Format with HTML formatter
        $formatter = new Claude_HtmlFormatter();
        $preview = '<div style="background: #f9f9f9; padding: 10px; margin-bottom: 15px; border-radius: 4px;">';
        $preview .= '<strong>File:</strong> ' . esc_html(basename($filepath)) . '<br>';
        $preview .= '<strong>Session ID:</strong> ' . esc_html($conversation['session_id']) . '<br>';
        $preview .= '<strong>Messages:</strong> ' . count($conversation['messages']) . '<br>';
        $preview .= '<strong>Thinking Blocks:</strong> ' . count($conversation['thinking']);
        $preview .= '</div>';
        $preview .= $formatter->get_css();
        $preview .= $formatter->format($conversation);

        // Store in transient for display (sanitized content)
        set_transient('claude_preview_html', $preview, 60);

        // Redirect to show preview
        wp_redirect(add_query_arg(array(
            'page' => 'claude-conversations',
            'claude_preview' => '1',
        ), admin_url('admin.php')));
        exit;
    }
}

/**
 * Enqueue Prism.js for syntax highlighting on Claude Conversation posts
 */
function claude_conversations_enqueue_prism() {
    // Only load on single posts
    if (!is_single()) {
        return;
    }

    // Get the current post
    $post = get_post();
    if (!$post) {
        return;
    }

    // Check if post has "Claude Conversations" category
    if (!has_category('Claude Conversations', $post)) {
        return;
    }

    // Enqueue Prism CSS (Tomorrow Night theme)
    wp_enqueue_style(
        'prism-css',
        'https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css',
        array(),
        '1.29.0'
    );

    // Enqueue Prism JS core
    wp_enqueue_script(
        'prism-js',
        'https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js',
        array(),
        '1.29.0',
        true
    );

    // Enqueue language components
    $languages = array('python', 'bash', 'javascript', 'rust');
    foreach ($languages as $lang) {
        wp_enqueue_script(
            "prism-js-{$lang}",
            "https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-{$lang}.min.js",
            array('prism-js'),
            '1.29.0',
            true
        );
    }

    // Add inline CSS for message styling
    $formatter = new Claude_HtmlFormatter();
    wp_add_inline_style('prism-css', $formatter->get_css());
}
add_action('wp_enqueue_scripts', 'claude_conversations_enqueue_prism');

// Initialize the plugin
new Claude_Conversations_Admin();
