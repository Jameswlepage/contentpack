<?php

if (!defined('ABSPATH')) {
    exit;
}

class OpenAI_Post_Gen_API
{
    private $api_key;
    private $model = 'gpt-4o';
    private $schema; // loaded from schema.json

    public function __construct($api_key = '')
    {
        $this->api_key = $api_key;
        $this->load_schema();
    }

    private function load_schema()
    {
        $schema_path = plugin_dir_path(__FILE__) . '../assets/schema/main.schema.json';
        $schema_content = file_get_contents($schema_path);
        $this->schema = json_decode($schema_content, true);
    }

    public function set_api_key($key)
    {
        $this->api_key = $key;
    }

    private function request($messages, $schema_key, $temperature = 0.7)
    {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'No API key provided.');
        }

        // Use schema from $defs
        if (!isset($this->schema['$defs'][$schema_key])) {
            return new WP_Error('invalid_schema_key', 'Invalid schema key requested.');
        }

        $body = array(
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $temperature,
            // Enforce JSON schema
            'response_format' => array(
                'type' => 'json_schema',
                'json_schema' => array(
                    'name' => $schema_key,
                    'schema' => $this->schema['$defs'][$schema_key],
                    'strict' => true
                )
            )
        );

        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key
            ),
            'body' => json_encode($body),
            'timeout' => 120
        );
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data)) {
            return new WP_Error('invalid_response', 'Invalid JSON response from OpenAI.');
        }

        if (isset($data['error'])) {
            return new WP_Error('openai_error', 'OpenAI API Error: ' . $data['error']['message']);
        }

        // Check for refusal
        if (isset($data['choices'][0]['message']['refusal'])) {
            return new WP_Error('refusal', $data['choices'][0]['message']['refusal']);
        }

        if (!isset($data['choices'][0]['message']['content'])) {
            return new WP_Error('no_content', 'No content returned from OpenAI.');
        }

        return $data['choices'][0]['message']['content'];
    }

    public function generate_plan($clusters, $theme, $count)
    {
        $prompt = "You are a professional content strategist. I have {$count} clusters of topics:\n";
        foreach ($clusters as $index => $cluster_topic) {
            $prompt .= ($index + 1) . ". $cluster_topic\n";
        }
        $prompt .= "General theme: $theme\n";
        $prompt .= "Follow the schema strictly and return only JSON.\n";

        $messages = array(
            array('role' => 'system', 'content' => 'You are a helpful assistant.'),
            array('role' => 'user', 'content' => $prompt)
        );

        return $this->request($messages, 'plan');
    }

    public function generate_single_post_content($title, $slug, $summary, $categories, $tags, $theme, $cluster_topic)
    {
        $cat_list = implode(", ", $categories);
        $tag_list = implode(", ", $tags);

        $prompt = "You are a professional blog writer.\n";
        $prompt .= "Cluster topic: \"$cluster_topic\"\n";
        $prompt .= "General theme: $theme\n";
        $prompt .= "Title: \"{$title}\"\nSlug: \"{$slug}\"\nSummary: \"{$summary}\"\n";
        $prompt .= "Categories: [{$cat_list}]\nTags: [{$tag_list}]\n";
        $prompt .= "Follow the schema strictly and return only JSON.\n";

        $messages = array(
            array('role' => 'system', 'content' => 'You are a helpful assistant.'),
            array('role' => 'user', 'content' => $prompt)
        );

        return $this->request($messages, 'single_post');
    }
}
