<?php
/**
 * HTML Formatter for Claude conversations
 *
 * @package Claude_Conversations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Claude_HtmlFormatter
 *
 * Formats parsed Claude conversations as HTML with syntax highlighting.
 */
class Claude_HtmlFormatter {

    /**
     * Format a complete conversation as HTML
     *
     * @param array $conversation Parsed conversation data.
     * @return string Formatted HTML.
     */
    public function format(array $conversation): string {
        $html = '<div class="claude-conversation">';

        // Add metadata header
        if (isset($conversation['metadata'])) {
            $html .= $this->format_metadata($conversation['metadata']);
        }

        // Add messages
        if (isset($conversation['messages'])) {
            foreach ($conversation['messages'] as $message) {
                $html .= $this->format_message($message);
            }
        }

        // Add thinking blocks
        if (isset($conversation['thinking'])) {
            foreach ($conversation['thinking'] as $think) {
                $html .= $this->format_thinking($think);
            }
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Format metadata section
     *
     * @param array $meta Metadata array.
     * @return string HTML header section.
     */
    public function format_metadata(array $meta): string {
        $html = '<div class="claude-metadata">';

        if (!empty($meta['project'])) {
            $html .= sprintf(
                '<div class="claude-meta-item"><strong>Project:</strong> <span class="claude-project">%s</span></div>',
                esc_html(basename($meta['project']))
            );
        }

        if (!empty($meta['git_branch'])) {
            $html .= sprintf(
                '<div class="claude-meta-item"><strong>Branch:</strong> <span class="claude-branch">%s</span></div>',
                esc_html($meta['git_branch'])
            );
        }

        if (!empty($meta['start_time'])) {
            $html .= sprintf(
                '<div class="claude-meta-item"><strong>Started:</strong> <span class="claude-start">%s</span></div>',
                esc_html(date('Y-m-d H:i:s', $meta['start_time']))
            );
        }

        if (!empty($meta['end_time'])) {
            $html .= sprintf(
                '<div class="claude-meta-item"><strong>Ended:</strong> <span class="claude-end">%s</span></div>',
                esc_html(date('Y-m-d H:i:s', $meta['end_time']))
            );
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Format a single message
     *
     * @param array $msg Message array with role and content.
     * @return string HTML formatted message.
     */
    public function format_message(array $msg): string {
        $role = isset($msg['role']) ? $msg['role'] : 'unknown';
        $content = isset($msg['content']) ? $msg['content'] : '';

        // Apply role-based styling
        $class = 'claude-msg-' . esc_attr($role);

        $html = sprintf('<div class="%s">', $class);

        // Add role label
        $label = ucfirst($role);
        $html .= sprintf('<div class="claude-msg-role">%s</div>', esc_html($label));

        // Format content with code blocks and markdown
        $formatted_content = $this->format_code_blocks($content);
        $formatted_content = $this->format_markdown($formatted_content);

        $html .= sprintf('<div class="claude-msg-content">%s</div>', $formatted_content);

        $html .= '</div>';

        return $html;
    }

    /**
     * Format thinking block
     *
     * @param array $think Thinking block array.
     * @return string HTML formatted thinking block.
     */
    public function format_thinking(array $think): string {
        $content = isset($think['content']) ? $think['content'] : '';

        $html = '<blockquote class="claude-thinking">';
        $html .= '<span class="claude-thinking-icon">&#129504;</span> '; // Brain emoji
        $html .= '<strong>Thinking:</strong>';

        // Format content
        $formatted_content = $this->format_code_blocks($content);
        $formatted_content = $this->format_markdown($formatted_content);

        $html .= sprintf('<div class="claude-thinking-content">%s</div>', $formatted_content);
        $html .= '</blockquote>';

        return $html;
    }

    /**
     * Format code blocks in content
     * Converts ```language blocks to <pre><code class="language-X">
     * Converts inline `code` to <code>
     *
     * @param string $content Raw content.
     * @return string Content with formatted code blocks.
     */
    public function format_code_blocks(string $content): string {
        // First, handle fenced code blocks ```language\n...\n```
        $content = preg_replace_callback(
            '/```(\w*)\n(.*?)```/s',
            function ($matches) {
                $language = !empty($matches[1]) ? esc_attr($matches[1]) : 'plaintext';
                $code = esc_html($matches[2]);
                return sprintf('<pre><code class="language-%s">%s</code></pre>', $language, $code);
            },
            $content
        );

        // Then handle inline code `code`
        $content = preg_replace_callback(
            '/`([^`]+)`/',
            function ($matches) {
                return sprintf('<code class="claude-inline-code">%s</code>', esc_html($matches[1]));
            },
            $content
        );

        return $content;
    }

    /**
     * Format basic markdown elements
     * Converts **bold** and *italic*
     *
     * @param string $content Content with markdown.
     * @return string HTML formatted content.
     */
    public function format_markdown(string $content): string {
        // Bold: **text** or __text__
        $content = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content);
        $content = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $content);

        // Italic: *text* or _text_ (but not if preceded by word char to avoid breaking snake_case)
        $content = preg_replace('/(?<![a-zA-Z0-9])\*(.+?)\*(?![a-zA-Z0-9])/', '<em>$1</em>', $content);
        $content = preg_replace('/(?<![a-zA-Z0-9])_(.+?)_(?![a-zA-Z0-9])/', '<em>$1</em>', $content);

        // Convert newlines to <br> for readability (but preserve pre blocks)
        // This is a simple approach - don't convert if inside <pre> tags
        $content = preg_replace('/(?<!<\/pre>)\n(?!<pre>)/', "<br>\n", $content);

        return $content;
    }

    /**
     * Get inline CSS for styling
     *
     * @return string CSS styles.
     */
    public function get_css(): string {
        $css = '<style>';
        $css .= '.claude-conversation {';
        $css .= '    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif;';
        $css .= '    max-width: 800px;';
        $css .= '    margin: 0 auto;';
        $css .= '    padding: 20px;';
        $css .= '    line-height: 1.6;';
        $css .= '    color: #333;';
        $css .= '}';
        $css .= '.claude-metadata {';
        $css .= '    background: #f5f5f5;';
        $css .= '    border: 1px solid #ddd;';
        $css .= '    border-radius: 4px;';
        $css .= '    padding: 15px;';
        $css .= '    margin-bottom: 20px;';
        $css .= '    font-size: 14px;';
        $css .= '}';
        $css .= '.claude-meta-item {';
        $css .= '    margin-bottom: 5px;';
        $css .= '}';
        $css .= '.claude-meta-item:last-child {';
        $css .= '    margin-bottom: 0;';
        $css .= '}';
        $css .= '.claude-msg-user {';
        $css .= '    border-left: 4px solid #2271b1;';
        $css .= '    background: #f0f6fc;';
        $css .= '    padding: 15px;';
        $css .= '    margin-bottom: 15px;';
        $css .= '    border-radius: 0 4px 4px 0;';
        $css .= '}';
        $css .= '.claude-msg-assistant {';
        $css .= '    border-left: 4px solid #00a32a;';
        $css .= '    background: #edfaef;';
        $css .= '    padding: 15px;';
        $css .= '    margin-bottom: 15px;';
        $css .= '    border-radius: 0 4px 4px 0;';
        $css .= '}';
        $css .= '.claude-msg-role {';
        $css .= '    font-weight: bold;';
        $css .= '    font-size: 12px;';
        $css .= '    text-transform: uppercase;';
        $css .= '    color: #666;';
        $css .= '    margin-bottom: 8px;';
        $css .= '}';
        $css .= '.claude-msg-content {';
        $css .= '    white-space: pre-wrap;';
        $css .= '    word-wrap: break-word;';
        $css .= '}';
        $css .= '.claude-thinking {';
        $css .= '    background: #fff8e5;';
        $css .= '    border: 1px solid #ffb900;';
        $css .= '    border-left: 4px solid #ffb900;';
        $css .= '    padding: 15px;';
        $css .= '    margin: 15px 0;';
        $css .= '    border-radius: 4px;';
        $css .= '    font-style: italic;';
        $css .= '}';
        $css .= '.claude-thinking-icon {';
        $css .= '    font-size: 1.2em;';
        $css .= '}';
        $css .= '.claude-thinking-content {';
        $css .= '    margin-top: 10px;';
        $css .= '}';
        $css .= '.claude-code,';
        $css .= '.claude-conversation pre {';
        $css .= '    background: #282c34;';
        $css .= '    color: #abb2bf;';
        $css .= '    padding: 15px;';
        $css .= '    border-radius: 4px;';
        $css .= '    overflow-x: auto;';
        $css .= '    font-family: "Fira Code", "Consolas", "Monaco", monospace;';
        $css .= '    font-size: 14px;';
        $css .= '    line-height: 1.5;';
        $css .= '}';
        $css .= '.claude-conversation pre code {';
        $css .= '    background: transparent;';
        $css .= '    padding: 0;';
        $css .= '    color: inherit;';
        $css .= '}';
        $css .= '.claude-inline-code {';
        $css .= '    background: #f0f0f0;';
        $css .= '    padding: 2px 6px;';
        $css .= '    border-radius: 3px;';
        $css .= '    font-family: "Fira Code", "Consolas", "Monaco", monospace;';
        $css .= '    font-size: 0.9em;';
        $css .= '    color: #c7254e;';
        $css .= '}';
        $css .= '</style>';
        return $css;
    }
}
