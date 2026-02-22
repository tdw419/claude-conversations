<?php
/**
 * Simple Test Runner for Claude_JsonlParser
 *
 * No PHPUnit dependency - standalone test script.
 *
 * Run: php test-jsonl-parser.php
 *
 * @package Claude_Conversations_Tests
 */

// Define ABSPATH to allow loading WordPress plugin classes outside WP context
define('ABSPATH', '/tmp/fake-wordpress/');

// Disable output buffering for Docker CLI
if (ob_get_level() > 0) {
    ob_end_flush();
}

// Simple WP_Error polyfill for standalone testing
if (!class_exists('WP_Error')) {
    class WP_Error {
        public $errors = array();
        public $error_data = array();

        public function __construct($code = '', $message = '', $data = '') {
            if (!empty($code)) {
                $this->errors[$code][] = $message;
                if (!empty($data)) {
                    $this->error_data[$code] = $data;
                }
            }
        }

        public function get_error_code() {
            $codes = array_keys($this->errors);
            return empty($codes) ? '' : $codes[0];
        }

        public function get_error_message($code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            return isset($this->errors[$code]) ? $this->errors[$code][0] : '';
        }

        public function is_wp_error() {
            return true;
        }
    }
}

// Polyfill is_wp_error function
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return ($thing instanceof WP_Error);
    }
}

// Include the parser class
require_once dirname(__DIR__) . '/includes/class-jsonl-parser.php';

/**
 * Simple Test Runner Class
 */
class JsonlParserTestRunner {

    private $passed = 0;
    private $failed = 0;
    private $errors = array();
    private $fixtures_dir;

    public function __construct() {
        $this->fixtures_dir = __DIR__ . '/fixtures';
    }

    /**
     * Assert condition is true
     */
    private function assert($condition, $message) {
        if ($condition) {
            $this->passed++;
            echo "  PASS: {$message}\n";
            return true;
        } else {
            $this->failed++;
            $this->errors[] = $message;
            echo "  FAIL: {$message}\n";
            return false;
        }
    }

    /**
     * Assert two values are equal
     */
    private function assertEqual($expected, $actual, $message) {
        return $this->assert(
            $expected === $actual,
            "{$message} (expected: " . var_export($expected, true) . ", got: " . var_export($actual, true) . ")"
        );
    }

    /**
     * Assert value is not empty
     */
    private function assertNotEmpty($value, $message) {
        return $this->assert(!empty($value), $message);
    }

    /**
     * Assert value is a WP_Error
     */
    private function assertError($value, $message) {
        return $this->assert(is_wp_error($value), $message);
    }

    /**
     * Assert value is not a WP_Error
     */
    private function assertNotError($value, $message) {
        return $this->assert(!is_wp_error($value), $message);
    }

    /**
     * Test 1: Parse valid JSONL file
     */
    public function testParseValidFile() {
        echo "\nTest 1: Parse valid JSONL file\n";

        $filepath = $this->fixtures_dir . '/sample.jsonl';
        $parser = new Claude_JsonlParser($filepath);
        $result = $parser->parse();

        $this->assertNotError($result, 'Parse should not return error');
        $this->assertNotEmpty($result, 'Parse result should not be empty');
        $this->assertEqual('sample', $result['session_id'], 'Session ID should be extracted from filename');
        $this->assert(isset($result['messages']), 'Result should have messages key');
        $this->assert(isset($result['thinking']), 'Result should have thinking key');
        $this->assert(isset($result['metadata']), 'Result should have metadata key');
    }

    /**
     * Test 2: Parse file with malformed lines (skip gracefully)
     */
    public function testParseMalformedLines() {
        echo "\nTest 2: Parse file with malformed lines\n";

        // Create test file with malformed lines
        $malformed_file = $this->fixtures_dir . '/malformed.jsonl';
        $content = "{\"type\":\"user\",\"message\":{\"role\":\"user\",\"content\":\"Good line 1\"}}\n";
        $content .= "this is not valid json\n";
        $content .= "{\"type\":\"assistant\",\"message\":{\"role\":\"assistant\",\"content\":[{\"type\":\"text\",\"text\":\"Good line 2\"}]}}\n";
        $content .= "{broken json here\n";
        file_put_contents($malformed_file, $content);

        $parser = new Claude_JsonlParser($malformed_file);
        $result = $parser->parse();

        $this->assertNotError($result, 'Parse should skip malformed lines without error');
        $this->assertEqual(2, count($result['messages']), 'Should extract 2 messages (skipping 2 malformed lines)');

        // Cleanup
        unlink($malformed_file);
    }

    /**
     * Test 3: Extract user messages correctly
     */
    public function testExtractUserMessages() {
        echo "\nTest 3: Extract user messages correctly\n";

        $filepath = $this->fixtures_dir . '/sample.jsonl';
        $parser = new Claude_JsonlParser($filepath);
        $result = $parser->parse();

        $user_messages = array_filter($result['messages'], function($msg) {
            return $msg['role'] === 'user';
        });

        $this->assertEqual(2, count($user_messages), 'Should have 2 user messages');

        // Check first user message content
        $first_user = reset($user_messages);
        $this->assert(
            strpos($first_user['content'], 'Hello') !== false,
            'First user message should contain "Hello"'
        );
    }

    /**
     * Test 4: Extract assistant text blocks
     */
    public function testExtractAssistantTextBlocks() {
        echo "\nTest 4: Extract assistant text blocks\n";

        $filepath = $this->fixtures_dir . '/sample.jsonl';
        $parser = new Claude_JsonlParser($filepath);
        $result = $parser->parse();

        $assistant_messages = array_filter($result['messages'], function($msg) {
            return $msg['role'] === 'assistant';
        });

        $this->assertEqual(2, count($assistant_messages), 'Should have 2 assistant messages');

        // Check that assistant messages have content
        foreach ($assistant_messages as $msg) {
            $this->assertNotEmpty($msg['content'], 'Assistant message should have content');
        }

        // Check second assistant message contains code block
        $messages = array_values($result['messages']);
        $last_assistant = end($messages);
        $this->assert(
            strpos($last_assistant['content'], 'factorial') !== false,
            'Assistant message should contain factorial function'
        );
    }

    /**
     * Test 5: Extract thinking blocks
     */
    public function testExtractThinkingBlocks() {
        echo "\nTest 5: Extract thinking blocks\n";

        $filepath = $this->fixtures_dir . '/sample.jsonl';
        $parser = new Claude_JsonlParser($filepath);
        $result = $parser->parse();

        $this->assertEqual(2, count($result['thinking']), 'Should have 2 thinking blocks');

        // Check thinking block content
        $first_thinking = $result['thinking'][0]['content'];
        $this->assert(
            strpos($first_thinking, 'think') !== false,
            'First thinking block should contain "think"'
        );

        $second_thinking = $result['thinking'][1]['content'];
        $this->assert(
            strpos($second_thinking, 'Factorial') !== false,
            'Second thinking block should mention "Factorial"'
        );
    }

    /**
     * Test 6: Extract metadata (session_id, gitBranch, timestamps)
     */
    public function testExtractMetadata() {
        echo "\nTest 6: Extract metadata\n";

        $filepath = $this->fixtures_dir . '/sample.jsonl';
        $parser = new Claude_JsonlParser($filepath);
        $result = $parser->parse();

        $this->assertEqual('sample', $result['session_id'], 'Session ID should be sample');
        $this->assertEqual('project', $result['metadata']['project'], 'Project should be extracted from cwd');
        $this->assertEqual('main', $result['metadata']['git_branch'], 'Git branch should be extracted from first entry');
        $this->assert($result['metadata']['start_time'] > 0, 'Start time should be set');
        $this->assert($result['metadata']['end_time'] > 0, 'End time should be set');
        $this->assert(
            $result['metadata']['end_time'] >= $result['metadata']['start_time'],
            'End time should be >= start time'
        );
    }

    /**
     * Test 7: Handle empty file
     */
    public function testHandleEmptyFile() {
        echo "\nTest 7: Handle empty file\n";

        // Create empty file
        $empty_file = $this->fixtures_dir . '/empty.jsonl';
        file_put_contents($empty_file, '');

        $parser = new Claude_JsonlParser($empty_file);
        $result = $parser->parse();

        $this->assertError($result, 'Empty file should return WP_Error');
        $this->assertEqual('empty_file', $result->get_error_code(), 'Error code should be empty_file');

        // Cleanup
        unlink($empty_file);
    }

    /**
     * Test 8: Handle file not found
     */
    public function testHandleFileNotFound() {
        echo "\nTest 8: Handle file not found\n";

        $parser = new Claude_JsonlParser('/nonexistent/path/to/file.jsonl');
        $result = $parser->parse();

        $this->assertError($result, 'Nonexistent file should return WP_Error');
        $this->assertEqual('file_not_found', $result->get_error_code(), 'Error code should be file_not_found');
    }

    /**
     * Test 9: Extract title from conversation
     */
    public function testExtractTitle() {
        echo "\nTest 9: Extract title from conversation\n";

        $filepath = $this->fixtures_dir . '/sample.jsonl';
        $parser = new Claude_JsonlParser($filepath);
        $result = $parser->parse();

        $title = $parser->extract_title($result);

        $this->assertNotEmpty($title, 'Title should not be empty');
        $this->assert(
            strpos($title, 'Hello') !== false,
            'Title should contain first user message content'
        );
        $this->assert(
            strlen($title) <= 83, // 80 + 3 for potential "..."
            'Title should be max 80 chars (plus ellipsis)'
        );
    }

    /**
     * Test 10: Title extraction handles empty conversation
     */
    public function testExtractTitleEmpty() {
        echo "\nTest 10: Title extraction handles empty conversation\n";

        $parser = new Claude_JsonlParser($this->fixtures_dir . '/sample.jsonl');

        // Test with empty messages array
        $empty_conversation = array('messages' => array());
        $title = $parser->extract_title($empty_conversation);

        $this->assertEqual('Untitled Conversation', $title, 'Empty conversation should return default title');
    }

    /**
     * Test 11: Handle file with only whitespace lines
     */
    public function testHandleWhitespaceOnlyFile() {
        echo "\nTest 11: Handle file with only whitespace lines\n";

        $ws_file = $this->fixtures_dir . '/whitespace.jsonl';
        file_put_contents($ws_file, "   \n\n\t\n   \n");

        $parser = new Claude_JsonlParser($ws_file);
        $result = $parser->parse();

        $this->assertError($result, 'Whitespace-only file should return WP_Error');
        $this->assertEqual('empty_file', $result->get_error_code(), 'Error code should be empty_file');

        // Cleanup
        unlink($ws_file);
    }

    /**
     * Test 12: Directory traversal protection
     */
    public function testDirectoryTraversalProtection() {
        echo "\nTest 12: Directory traversal protection\n";

        $parser = new Claude_JsonlParser('/some/path/../../../etc/passwd.jsonl');
        $result = $parser->parse();

        $this->assertError($result, 'Directory traversal should return WP_Error');
        $this->assertEqual('invalid_path', $result->get_error_code(), 'Error code should be invalid_path');
    }

    /**
     * Test 13: Invalid file extension
     */
    public function testInvalidExtension() {
        echo "\nTest 13: Invalid file extension\n";

        $txt_file = $this->fixtures_dir . '/test.txt';
        file_put_contents($txt_file, '{"type":"user"}');

        $parser = new Claude_JsonlParser($txt_file);
        $result = $parser->parse();

        $this->assertError($result, 'Non-JSONL extension should return WP_Error');
        $this->assertEqual('invalid_format', $result->get_error_code(), 'Error code should be invalid_format');

        // Cleanup
        unlink($txt_file);
    }

    /**
     * Run all tests
     */
    public function run() {
        echo "====================================\n";
        echo "Claude_JsonlParser Unit Tests\n";
        echo "====================================\n";

        $methods = get_class_methods($this);
        foreach ($methods as $method) {
            if (strpos($method, 'test') === 0) {
                $this->$method();
            }
        }

        echo "\n====================================\n";
        echo "Results: {$this->passed} passed, {$this->failed} failed\n";
        echo "====================================\n";

        if ($this->failed > 0) {
            echo "\nFailed tests:\n";
            foreach ($this->errors as $error) {
                echo "  - {$error}\n";
            }
            exit(1);
        }

        echo "\nAll {$this->passed} tests passed\n";
        exit(0);
    }
}

// Run tests
$runner = new JsonlParserTestRunner();
$runner->run();
