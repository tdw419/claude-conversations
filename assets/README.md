# Plugin Assets for WordPress.org

These assets are displayed on the WordPress.org plugin directory page.

## Files

| File | Size | Description |
|------|------|-------------|
| `banner-772x250.png` | 772×250 | Standard banner |
| `banner-1544x500.png` | 1544×500 | HiDPI/Retina banner |
| `icon-128x128.png` | 128×128 | Standard icon |
| `icon-256x256.png` | 256×256 | HiDPI/Retina icon |

## How to Upload

After your plugin is approved on WordPress.org:

1. Check out your plugin's SVN repository:
   ```bash
   svn co https://plugins.svn.wordpress.org/claude-conversations/
   cd claude-conversations
   ```

2. Create an `assets` folder (at root level, alongside `trunk/`):
   ```bash
   mkdir assets
   cp /path/to/assets/*.png assets/
   ```

3. Commit to SVN:
   ```bash
   svn add assets
   svn ci -m "Add plugin banner and icons"
   ```

## Design Notes

- **Banner**: Dark theme with chat bubbles showing user/AI conversation and code snippet
- **Icon**: Blue background with "CC" (Claude Conversations) abbreviation
- **Colors**: Matches Claude/Anthropic branding (blues and greens)

## Regenerating Assets

If you need to regenerate these assets:

```bash
cd assets/
# Run the commands from the plugin preparation script
# or use ImageMagick to create custom designs
```
