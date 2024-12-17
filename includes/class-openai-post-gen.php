<?php

if (! defined('ABSPATH')) {
    exit;
}

class OpenAI_Post_Gen
{

    public $option_name = 'openai_post_gen_options';
    public $api;
    public $generator;
    public $admin;
    public $cli;

    public function __construct()
    {
        $this->api = new OpenAI_Post_Gen_API($this->get_api_key());
        $this->generator = new OpenAI_Post_Gen_Generator($this);
        $this->admin = new OpenAI_Post_Gen_Admin($this);
        $this->cli = new OpenAI_Post_Gen_CLI($this);
    }

    public function init()
    {
        $this->admin->init();

        if (defined('WP_CLI') && WP_CLI) {
            $this->cli->init();
        }
    }

    public function get_api_key()
    {
        $options = get_option($this->option_name);
        return isset($options['api_key']) ? $options['api_key'] : '';
    }

    public function update_api_key($key)
    {
        $options = get_option($this->option_name, array());
        $options['api_key'] = $key;
        update_option($this->option_name, $options);
        // Update API instance key
        $this->api->set_api_key($key);
    }
}
