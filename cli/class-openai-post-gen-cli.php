<?php

if (!defined('ABSPATH')) {
    exit;
}

class OpenAI_Post_Gen_CLI
{
    private $plugin;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
    }

    public function init()
    {
        WP_CLI::add_command('openai-post-gen', array($this, 'handle_command'));
    }

    public function handle_command($args, $assoc_args)
    {
        $subcommand = isset($args[0]) ? $args[0] : '';

        if ($subcommand === 'single') {
            $title = isset($assoc_args['title']) ? $assoc_args['title'] : '';
            $topic = isset($assoc_args['topic']) ? $assoc_args['topic'] : '';
            $theme = isset($assoc_args['theme']) ? $assoc_args['theme'] : '';
            $res = $this->plugin->generator->generate_single_post($title, $topic, $theme);
            if (is_wp_error($res)) {
                WP_CLI::error($res->get_error_message());
            } else {
                WP_CLI::success("Post created with ID: $res");
            }
        } elseif ($subcommand === 'bulk') {
            $clusters = isset($assoc_args['clusters']) ? json_decode($assoc_args['clusters'], true) : array();
            $theme = isset($assoc_args['theme']) ? $assoc_args['theme'] : '';
            $count = isset($assoc_args['count']) ? intval($assoc_args['count']) : 5;

            if (!is_array($clusters) || empty($clusters)) {
                WP_CLI::error('Invalid clusters parameter. Must be a JSON array of topics.');
            }

            $res = $this->plugin->generator->generate_bulk_posts($clusters, $theme, $count);
            if (is_wp_error($res)) {
                WP_CLI::error($res->get_error_message());
            } else {
                WP_CLI::success("Bulk posts generated successfully.");
            }
        } else {
            WP_CLI::error("Unknown subcommand. Use 'single' or 'bulk'.");
        }
    }
}
