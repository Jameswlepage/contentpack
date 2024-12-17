<?php

/**
 * Plugin Name: OpenAI Post Generator
 * Plugin URI: https://a8c.com/
 * Description: Seed WordPress with unique, topically clustered posts using OpenAI.
 * Version: 1.0
 * Author: James LePage
 * Author URI: https://a8c.com/
 * License: GPL2
 */

if (! defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/class-openai-post-gen.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-openai-post-gen-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-openai-post-gen-generator.php';
require_once plugin_dir_path(__FILE__) . 'admin/class-openai-post-gen-admin.php';
require_once plugin_dir_path(__FILE__) . 'cli/class-openai-post-gen-cli.php';

function openai_post_gen_init_plugin()
{
    $plugin = new OpenAI_Post_Gen();
    $plugin->init();
}
add_action('plugins_loaded', 'openai_post_gen_init_plugin');
