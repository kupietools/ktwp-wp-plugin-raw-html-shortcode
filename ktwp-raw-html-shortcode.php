<?php
/**
 * Plugin Name: KupieTools Raw HTML Shortcode
 * Description: Adds a [raw] shortcode that prevents WP's automatic <p> and <br> insertion.
 * Version: 1.0.0
 * Author: Michael E Kupietz
 */

if (!defined('ABSPATH')) {
    exit;
}

remove_filter('the_content', 'wpautop');
add_filter('the_content', 'rcb_wpautop_except_raw_blocks', 10);

function rcb_wpautop_except_raw_blocks($content) {
    if (stripos($content, '[raw]') === false) {
        return wpautop($content);
    }

    $raw_blocks = [];

    /*
     * Capture [raw]...[/raw].
     *
     * This is intentionally simple. It does not support nested [raw] blocks.
     */
    $content = preg_replace_callback(
        '~\[raw\](.*?)\[/raw\]~is',
        function ($matches) use (&$raw_blocks) {
            $token = "\n\n" . '%%RAW_BLOCK_' . count($raw_blocks) . '_' . md5($matches[1]) . '%%' . "\n\n";

            $raw_blocks[$token] = $matches[1];

            return $token;
        },
        $content
    );

    /*
     * Run normal wpautop on everything else.
     */
    $content = wpautop($content);

    /*
     * Restore raw blocks.
     *
     * wpautop may wrap the token with <p>...</p>, so handle both:
     *   <p>%%RAW_BLOCK_x%%</p>
     * and:
     *   %%RAW_BLOCK_x%%
     */
    foreach ($raw_blocks as $token => $raw) {
        $bare_token = trim($token);
        $quoted = preg_quote($bare_token, '~');

        $raw = trim($raw);

        // Replace paragraph-wrapped token.
        $content = preg_replace(
            '~<p>\s*' . $quoted . '\s*</p>~i',
            $raw,
            $content
        );

        // Replace token followed by possible <br>.
        $content = preg_replace(
            '~' . $quoted . '\s*<br\s*/?>~i',
            $raw,
            $content
        );

        // Replace bare token.
        $content = str_replace($bare_token, $raw, $content);
    }

    return $content;
}
