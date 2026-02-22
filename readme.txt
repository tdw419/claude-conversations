=== Claude Conversations ===
Contributors: geometryos
Tags: claude, ai, conversations, import, code, syntax-highlighting
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import Claude Code CLI conversations into WordPress as formatted posts with syntax highlighting.

== Description ==

This plugin imports your Claude Code CLI conversation history into WordPress as beautifully formatted posts, perfect for documentation, knowledge bases, or sharing AI-assisted development sessions.

**Features:**

* **Smart Import** - Automatically discovers and imports all `.jsonl` session files from your Claude directory
* **Beautiful Formatting** - User messages (blue) and assistant messages (green) with role-based styling
* **Thinking Blocks** - Claude's extended thinking displayed in highlighted blockquotes
* **Syntax Highlighting** - Prism.js integration for Python, Bash, JavaScript, and Rust code blocks
* **Metadata Preservation** - Project name, git branch, and timestamps saved as post meta
* **Duplicate Detection** - Prevents re-importing the same sessions
* **Configurable** - Set a custom Claude directory path in settings

**Use Cases:**

* Document AI-assisted coding sessions for your team
* Create a searchable knowledge base of conversations
* Share troubleshooting sessions as blog posts
* Archive development history with full context

== Installation ==

1. Upload the `claude-conversations` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Settings > Claude Conversations** to configure the Claude directory path (default: `~/.claude/projects/`)
4. Navigate to **Claude Chats** in the admin sidebar to import sessions

== Frequently Asked Questions ==

= Where are Claude Code CLI conversations stored? =

By default, Claude Code CLI stores conversations in `~/.claude/projects/`. Each project has its own subdirectory containing `.jsonl` session files.

= Can I change the import directory? =

Yes! Go to **Settings > Claude Conversations** and enter a custom path. This is useful if your Claude data is on a different drive or mounted volume.

= What happens if I run import multiple times? =

The plugin tracks imported sessions using a unique session ID. Running import again will skip any sessions that have already been imported.

= Does this work with Claude Pro / Claude Team? =

This plugin imports conversations from the Claude Code CLI tool, which is available with Claude Pro and Claude Team subscriptions. It does not directly access the Claude web interface.

= Can I customize the appearance? =

Yes! The plugin uses CSS classes that can be overridden in your theme:
* `.claude-msg-user` - User messages
* `.claude-msg-assistant` - Assistant messages
* `.claude-thinking` - Thinking blocks
* `.claude-conversation pre` - Code blocks

= What Prism.js languages are supported? =

By default: Python, Bash, JavaScript, and Rust. You can add more by filtering the languages in the settings or using the `claude_conversations_prism_languages` filter.

== Screenshots ==

1. **Admin Import Page** - Import all sessions with one click
2. **Session Preview** - Test parse to preview formatting
3. **Frontend Post** - Beautiful formatted conversation view
4. **Settings Page** - Configure Claude directory path

== Changelog ==

= 1.0.0 =
* Initial release
* JSONL parsing with thinking block extraction
* HTML formatting with Prism.js syntax highlighting
* Duplicate detection via session ID
* Admin UI with import and test parse actions
* Configurable Claude directory via settings page

== Upgrade Notice ==

= 1.0.0 =
Initial release. Welcome to Claude Conversations!

== Security ==

This plugin implements several security measures:

* **Directory Traversal Prevention** - All file paths are validated to prevent `../` attacks
* **Nonce Verification** - All form submissions use WordPress nonces
* **Capability Checks** - Only users with `manage_options` can import
* **Input Sanitization** - All inputs are sanitized using WordPress functions
* **Output Escaping** - All outputs are properly escaped

== Privacy ==

This plugin processes conversation data stored locally on your server. No data is sent to external services. All imported conversations are stored as WordPress posts in your database.

== Credits ==

* [Prism.js](https://prismjs.com/) - Syntax highlighting
* [Claude Code CLI](https://claude.com/claude-code) - By Anthropic

== Support ==

For bug reports and feature requests, please use the [WordPress.org support forum](https://wordpress.org/support/plugin/claude-conversations/).
