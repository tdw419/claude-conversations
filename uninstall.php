<?php
/**
 * Uninstall script for Claude Conversations
 *
 * This file is called when the plugin is uninstalled (deleted) from WordPress.
 * It cleans up all plugin data including options and post meta.
 *
 * @package Claude_Conversations
 * @since 1.0.0
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Delete plugin options
 */
delete_option('claude_conversations_claude_dir');
delete_option('claude_conversations_version');

/**
 * Delete all post meta associated with Claude conversations
 */
global $wpdb;

// Delete all _claude_* post meta
$wpdb->query(
    "DELETE FROM {$wpdb->postmeta}
    WHERE meta_key LIKE '_claude_%'"
);

/**
 * Note: We do NOT delete the posts themselves or the category.
 * This allows users to keep their imported conversations even after
 * uninstalling the plugin. If they want to remove everything, they
 * should delete the posts manually before uninstalling.
 */

// Clear any cached data
wp_cache_flush();
