# Claude Conversations Importer

A WordPress plugin that imports Claude Code CLI conversation sessions into WordPress as formatted posts with syntax highlighting.

## Description

This plugin reads Claude Code CLI conversation history from `~/.claude/projects/` and creates WordPress posts with:

- **User and assistant message formatting** with role-based styling (blue/green borders)
- **Thinking block extraction** displayed as blockquotes with brain emoji
- **Prism.js syntax highlighting** for code blocks (Python, Bash, JavaScript, Rust)
- **Session metadata preservation** (project name, git branch, timestamps)
- **Duplicate detection** prevents re-importing the same sessions

## Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher
- Claude Code CLI installed with conversation history in `~/.claude/projects/`

## Installation

1. Copy the `claude-conversations` folder to your WordPress plugins directory:
   ```
   wp-content/plugins/
   ```

2. Activate the plugin in WordPress Admin > Plugins

3. Navigate to **Claude Chats** in the admin sidebar

## Usage

### Import All Sessions

1. Go to **Claude Chats** in the WordPress admin menu
2. Review the session count and imported count displayed on the page
3. Click **Import All Sessions** to import all `.jsonl` files from the Claude directory
4. Wait for the import to complete (typically 10-15 seconds for 300+ sessions)
5. View the results: imported count, skipped duplicates, and any errors

### Test Parse First Session

1. Go to **Claude Chats** in the WordPress admin menu
2. Click **Test Parse First Session** to preview a session without importing
3. Review the formatted output including messages, thinking blocks, and metadata

### Viewing Imported Posts

1. Go to **Posts** in WordPress admin
2. Filter by the **Claude Conversations** category
3. Click any post to view the formatted conversation

On the frontend, posts will have:
- Syntax-highlighted code blocks (Prism.js Tomorrow theme)
- User messages with blue left border
- Assistant messages with green left border
- Thinking blocks with yellow background and brain emoji

## File Structure

```
claude-conversations/
|-- claude-conversations.php    # Main plugin file with admin UI
|-- includes/
|   |-- class-jsonl-parser.php  # JSONL parsing logic
|   |-- class-html-formatter.php # HTML formatting with code highlighting
|   |-- class-importer.php      # Import engine with duplicate detection
|-- tests/
|   |-- test-jsonl-parser.php   # Parser unit tests
|   |-- test-html-formatter.php # Formatter unit tests
|   |-- test-import-integration.php # Integration tests
|   |-- fixtures/
|       |-- sample.jsonl        # Test fixture
|-- README.md                   # This file
```

## Customization

### Changing the Category Name

Edit `includes/class-importer.php` and modify the `ensure_category()` method:

```php
$category_name = 'Claude Conversations';  // Change this
$category_slug = 'claude-conversations';  // And this
```

### Custom CSS Styling

Edit `includes/class-html-formatter.php` and modify the `get_css()` method to change:

- **User message styling**: `.claude-msg-user` (blue border, light blue background)
- **Assistant message styling**: `.claude-msg-assistant` (green border, light green background)
- **Thinking block styling**: `.claude-thinking` (yellow border, light yellow background)
- **Code block styling**: `.claude-conversation pre` (dark theme)
- **Inline code styling**: `.claude-inline-code` (gray background, red text)

### Adding More Prism.js Languages

Edit `claude-conversations.php` and modify the `claude_conversations_enqueue_prism()` function:

```php
$languages = array('python', 'bash', 'javascript', 'rust', 'go', 'typescript');
```

Available languages: https://prismjs.com/#supported-languages

## Troubleshooting

### "No .jsonl files found"

- Verify Claude Code CLI is installed and has been used
- Check that `~/.claude/projects/` exists and contains `.jsonl` files
- Ensure the web server has read permissions for the Claude directory

### "Failed to parse session file"

- The JSONL file may be corrupted or have an unexpected format
- Try the "Test Parse First Session" button to see detailed error messages
- Check the file manually with `cat ~/.claude/projects/*/your-session.jsonl`

### Import is slow or times out

- For 300+ sessions, the import may take 10-15 seconds
- PHP `max_execution_time` is checked automatically; large imports will pause gracefully
- Re-run the import to continue from where it stopped (duplicates are skipped)

### Code blocks not highlighted on frontend

- Verify the post has the "Claude Conversations" category assigned
- Check that Prism.js CSS and JS are loading (browser dev tools > Network tab)
- Ensure no JavaScript errors are blocking Prism.js execution

### Duplicate posts created

- Duplicate detection uses the `_claude_session_id` post meta field
- If you manually deleted posts, their session IDs won't be re-imported
- To re-import a deleted session, delete the corresponding post meta from the database

## Development

### Running Tests

```bash
# Run all unit tests
for test in tests/test-*.php; do php "$test"; done

# Run specific test file
php tests/test-jsonl-parser.php
```

### PHP Lint Check

```bash
find . -name "*.php" -exec php -l {} \;
```

## Changelog

### 1.0.0

- Initial release
- JSONL parsing with thinking block extraction
- HTML formatting with Prism.js syntax highlighting
- Duplicate detection via session ID
- Admin UI with import and test parse actions

## License

GPL v2 or later

## Credits

- [Prism.js](https://prismjs.com/) for syntax highlighting
- [Claude Code CLI](https://claude.com/claude-code) by Anthropic
