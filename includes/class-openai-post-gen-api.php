<?php

if (! defined('ABSPATH')) {
    exit;
}

class OpenAI_Post_Gen_API
{

    private $api_key;
    private $model = 'gpt-4';

    public function __construct($api_key = '')
    {
        $this->api_key = $api_key;
    }

    public function set_api_key($key)
    {
        $this->api_key = $key;
    }

    public function request($messages, $temperature = 0.7)
    {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'No API key provided.');
        }

        $body = array(
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $temperature,
        );
        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key
            ),
            'body' => json_encode($body),
            'timeout' => 60
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

        if (empty($data['choices'][0]['message']['content'])) {
            return new WP_Error('no_content', 'No content returned from OpenAI.');
        }

        return $data['choices'][0]['message']['content'];
    }

    public function generate_plan($clusters, $theme, $count)
    {
        // Generate a JSON plan of posts including cluster topics, titles, slugs, categories, tags, summaries, and publish dates
        // The prompt requests a structured JSON with all necessary info.
        $prompt = "You are a professional content strategist. I have {$count} clusters of topics:\n";
        foreach ($clusters as $index => $cluster_topic) {
            $prompt .= ($index + 1) . ". $cluster_topic\n";
        }
        $prompt .= "General theme: $theme\n";
        $prompt .= "Produce a JSON structure with format:\n";
        $prompt .= "clusters: [ { \"cluster_topic\": string, \"posts\": [ {\"title\": string, \"slug\": string, \"summary\": string, \"categories\": [string], \"tags\": [string], \"planned_publish_date\": string (ISO 8601) } ] } ]\n";
        $prompt .= "Ensure each post is unique and distinct, no overlapping content, and prepare them for a cohesive blog series.\n";
        $prompt .= "Return only JSON, no extra commentary.";

        $messages = array(
            array('role' => 'system', 'content' => 'You are a helpful assistant.'),
            array('role' => 'user', 'content' => $prompt)
        );

        return $this->request($messages);
    }

    public function generate_single_post_content($title, $slug, $summary, $categories, $tags, $theme, $cluster_topic)
    {
        // Generate full content for a single post
        $cat_list = implode(", ", $categories);
        $tag_list = implode(", ", $tags);

        $prompt = "You are a professional blog writer.\n";
        $prompt .= "We have a cluster topic: \"$cluster_topic\".\n";
        $prompt .= "General theme: $theme\n";
        $prompt .= "Now produce a full WordPress blog post with the following details:\n";
        $prompt .= "Title: \"{$title}\"\n";
        $prompt .= "Slug: \"{$slug}\"\n";
        $prompt .= "Summary: \"{$summary}\"\n";
        $prompt .= "Categories: [{$cat_list}]\n";
        $prompt .= "Tags: [{$tag_list}]\n";
        $prompt .= "Return JSON with keys: {\"title\": string, \"slug\": string, \"summary\": string, \"content\": string, \"publish_date\": string (ISO 8601), \"categories\": [string], \"tags\": [string]}.\n";
        $prompt .= "Include headings, structured content, and a conclusion. No extraneous commentary outside the JSON.";

        $messages = array(
            array('role' => 'system', 'content' => 'You are a helpful assistant.'),
            array('role' => 'user', 'content' => $prompt)
        );

        return $this->request($messages);
    }
}
