<?php
/**
 * JSONL Parser for Claude Code CLI conversation files
 *
 * @package Claude_Conversations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Claude_JsonlParser
 *
 * Parses Claude Code CLI JSONL session files into structured conversation data.
 */
class Claude_JsonlParser {

    /**
     * Path to the JSONL file
     *
     * @var string
     */
    private $filepath;

    /**
     * Constructor
     *
     * @param string $filepath Path to JSONL file.
     */
    public function __construct(string $filepath) {
        $this->filepath = $filepath;
    }

    /**
     * Parse the JSONL file and extract conversation data
     *
     * @return array|WP_Error Structured conversation data or error.
     */
    public function parse() {
        // Validate filepath - prevent directory traversal
        if (preg_match('/\.\./', $this->filepath)) {
            return new WP_Error('invalid_path', 'Directory traversal not allowed in file path.');
        }

        if (!file_exists($this->filepath)) {
            return new WP_Error('file_not_found', sprintf('File not found: %s', $this->filepath));
        }

        if (!is_readable($this->filepath)) {
            return new WP_Error('file_not_readable', sprintf('File not readable: %s', $this->filepath));
        }

        // Validate file extension
        if (substr($this->filepath, -6) !== '.jsonl') {
            return new WP_Error('invalid_format', 'File must have .jsonl extension.');
        }

        $session_id = basename($this->filepath, '.jsonl');
        $messages = array();
        $thinking = array();
        $metadata = array(
            'project' => '',
            'git_branch' => '',
            'start_time' => PHP_INT_MAX,
            'end_time' => 0,
        );

        try {
            $handle = @fopen($this->filepath, 'r');
            if (!$handle) {
                return new WP_Error('file_open_failed', sprintf('Cannot open file: %s', $this->filepath));
            }
        } catch (Exception $e) {
            return new WP_Error('file_read_error', sprintf('Error reading file: %s', $e->getMessage()));
        }

        $line_number = 0;
        $valid_lines = 0;
        $invalid_lines = 0;

        try {
            while (($line = fgets($handle)) !== false) {
                $line_number++;
                $line = trim($line);

                if (empty($line)) {
                    continue;
                }

                $entry = json_decode($line, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    // Track invalid lines but continue parsing
                    $invalid_lines++;
                    continue;
                }

                $valid_lines++;

                // Extract metadata from first valid entry
                if (empty($metadata['project']) && isset($entry['cwd'])) {
                    $metadata['project'] = basename($entry['cwd']);
                }
                if (empty($metadata['git_branch']) && isset($entry['gitBranch'])) {
                    $metadata['git_branch'] = $entry['gitBranch'];
                }

                // Track timestamps
                if (isset($entry['timestamp'])) {
                    $timestamp = strtotime($entry['timestamp']);
                    if ($timestamp !== false) {
                        $metadata['start_time'] = min($metadata['start_time'], $timestamp);
                        $metadata['end_time'] = max($metadata['end_time'], $timestamp);
                    }
                }

                // Process user messages
                if (isset($entry['type']) && $entry['type'] === 'user') {
                    $content = $this->extract_user_content($entry);
                    if ($content !== '') {
                        $messages[] = array(
                            'role' => 'user',
                            'content' => $content,
                            'timestamp' => isset($entry['timestamp']) ? strtotime($entry['timestamp']) : 0,
                        );
                    }
                }

                // Process assistant messages
                if (isset($entry['type']) && $entry['type'] === 'assistant') {
                    $this->extract_assistant_content($entry, $messages, $thinking);
                }
            }
        } catch (Exception $e) {
            fclose($handle);
            return new WP_Error('parse_error', sprintf('Error parsing file at line %d: %s', $line_number, $e->getMessage()));
        }

        fclose($handle);

        // Check for empty conversation (no valid JSONL lines)
        if ($valid_lines === 0) {
            return new WP_Error('empty_file', 'File contains no valid JSONL entries.');
        }

        // Fix timestamps if none were found
        if ($metadata['start_time'] === PHP_INT_MAX) {
            $metadata['start_time'] = 0;
        }
        if ($metadata['end_time'] === 0) {
            $metadata['end_time'] = 0;
        }

        return array(
            'session_id' => $session_id,
            'messages' => $messages,
            'thinking' => $thinking,
            'metadata' => $metadata,
        );
    }

    /**
     * Extract content from user message
     *
     * @param array $entry JSONL entry.
     * @return string Extracted content.
     */
    private function extract_user_content(array $entry): string {
        if (!isset($entry['message'])) {
            return '';
        }

        $message = $entry['message'];

        // Content can be a string directly
        if (isset($message['content']) && is_string($message['content'])) {
            return $message['content'];
        }

        // Or an array of content blocks
        if (isset($message['content']) && is_array($message['content'])) {
            $texts = array();
            foreach ($message['content'] as $block) {
                if (isset($block['type']) && $block['type'] === 'text' && isset($block['text'])) {
                    $texts[] = $block['text'];
                }
            }
            return implode("\n", $texts);
        }

        return '';
    }

    /**
     * Extract content from assistant message (text and thinking blocks)
     *
     * @param array $entry JSONL entry.
     * @param array &$messages Array to append text messages to.
     * @param array &$thinking Array to append thinking blocks to.
     */
    private function extract_assistant_content(array $entry, array &$messages, array &$thinking): void {
        if (!isset($entry['message']['content']) || !is_array($entry['message']['content'])) {
            return;
        }

        $timestamp = isset($entry['timestamp']) ? strtotime($entry['timestamp']) : 0;
        $text_parts = array();

        foreach ($entry['message']['content'] as $block) {
            if (!isset($block['type'])) {
                continue;
            }

            // Extract thinking blocks
            if ($block['type'] === 'thinking' && isset($block['thinking'])) {
                $thinking[] = array(
                    'content' => $block['thinking'],
                );
            }

            // Extract text blocks
            if ($block['type'] === 'text' && isset($block['text'])) {
                $text_parts[] = $block['text'];
            }
        }

        // Add combined text as message if we have any
        if (!empty($text_parts)) {
            $messages[] = array(
                'role' => 'assistant',
                'content' => implode("\n", $text_parts),
                'timestamp' => $timestamp,
            );
        }
    }

    /**
     * Extract title from conversation (first 80 chars of first user message)
     *
     * @param array $conversation Parsed conversation data.
     * @return string Title (max 80 chars).
     */
    public function extract_title(array $conversation): string {
        if (!isset($conversation['messages']) || empty($conversation['messages'])) {
            return 'Untitled Conversation';
        }

        // Find first user message
        foreach ($conversation['messages'] as $message) {
            if (isset($message['role']) && $message['role'] === 'user') {
                $content = $message['content'];
                // Strip any markdown or HTML for cleaner title
                $content = strip_tags($content);
                $content = preg_replace('/[#*_`~\[\]]/', '', $content);
                $content = trim($content);

                if (strlen($content) > 80) {
                    return substr($content, 0, 77) . '...';
                }
                return $content ?: 'Untitled Conversation';
            }
        }

        return 'Untitled Conversation';
    }
}
