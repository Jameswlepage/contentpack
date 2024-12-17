<?php

if (!defined('ABSPATH')) {
    exit;
}

class OpenAI_Post_Gen_Generator
{
    private $plugin;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
    }

    public function generate_single_post($title = '', $topic = '', $theme = '', $slug = '', $categories = array(), $tags = array(), $summary = '')
    {

        if (empty($title)) {
            $topic_msg = $topic ? "Topic: $topic\n" : "";
            $theme_msg = $theme ? "Theme: $theme\n" : "";
            $prompt = "You are a professional content writer. Generate a single WordPress post in strict JSON format.\n";
            $prompt .= "$topic_msg$theme_msg";
            $prompt .= "Return JSON with title, slug, summary, content, publish_date (ISO 8601), categories, tags.\n";
            $prompt .= "Do not create a separate 'Related posts' section. No related posts needed.\n";
            $prompt .= "Just produce a standalone well-structured article.\n";
            $prompt .= "Follow the schema strictly and return only JSON.\n";

            $messages = array(
                array('role' => 'system', 'content' => 'You are a helpful assistant.'),
                array('role' => 'user', 'content' => $prompt)
            );

            $response = $this->plugin->api->request($messages, 'single_post');
            if (is_wp_error($response)) {
                return $response;
            }
            $data = json_decode($response, true);
        } else {
            $response = $this->plugin->api->generate_single_post_content($title, $slug, $summary, $categories, $tags, $theme, $topic, array(), get_site_url());
            if (is_wp_error($response)) {
                return $response;
            }
            $data = json_decode($response, true);
        }

        if (!is_array($data) || empty($data['title']) || empty($data['content'])) {
            return new WP_Error('invalid_data', 'OpenAI did not return the expected structure.');
        }

        return $this->insert_post($data);
    }

    public function generate_bulk_posts($clusters, $theme, $count)
    {
        $plan_response = $this->plugin->api->generate_plan($clusters, $theme, $count);
        if (is_wp_error($plan_response)) {
            return $plan_response;
        }

        $plan = json_decode($plan_response, true);
        if (!is_array($plan) || empty($plan['clusters'])) {
            return new WP_Error('invalid_plan', 'OpenAI did not return a valid plan structure.');
        }

        $all_posts_info = array();
        $site_url = get_site_url();

        foreach ($plan['clusters'] as $cluster_data) {
            if (empty($cluster_data['cluster_topic']) || empty($cluster_data['posts'])) {
                continue;
            }
            $cluster_topic = $cluster_data['cluster_topic'];
            $cluster_posts = $cluster_data['posts']; // all posts in this cluster

            foreach ($cluster_posts as $current_post_info) {
                if (empty($current_post_info['title']) || empty($current_post_info['slug'])) {
                    continue;
                }

                $title = $current_post_info['title'];
                $slug = $current_post_info['slug'];
                $summary = isset($current_post_info['summary']) ? $current_post_info['summary'] : '';
                $categories = isset($current_post_info['categories']) ? $current_post_info['categories'] : array();
                $tags = isset($current_post_info['tags']) ? $current_post_info['tags'] : array();

                $other_posts = array();
                foreach ($cluster_posts as $p) {
                    if ($p['slug'] !== $slug) {
                        $other_posts[] = $p;
                    }
                }

                $response = $this->plugin->api->generate_single_post_content(
                    $title,
                    $slug,
                    $summary,
                    $categories,
                    $tags,
                    $theme,
                    $cluster_topic,
                    $other_posts,
                    $site_url
                );

                if (is_wp_error($response)) {
                    return $response;
                }

                $post_data = json_decode($response, true);
                if (!is_array($post_data) || empty($post_data['title']) || empty($post_data['content'])) {
                    continue;
                }

                $post_id = $this->insert_post($post_data);
                if (!is_wp_error($post_id)) {
                    $all_posts_info[] = array(
                        'id' => $post_id,
                        'title' => $post_data['title'],
                        'slug' => $post_data['slug'],
                        'cluster_topic' => $cluster_topic
                    );
                }
            }
        }

        return true;
    }

    private function insert_post($data)
    {
        $postarr = array(
            'post_title' => wp_strip_all_tags($data['title']),
            'post_content' => $data['content'],
            'post_excerpt' => isset($data['summary']) ? $data['summary'] : '',
            'post_status' => 'publish',
        );

        if (!empty($data['publish_date']) && $this->validate_date($data['publish_date'])) {
            $postarr['post_date'] = gmdate('Y-m-d H:i:s', strtotime($data['publish_date']));
        }

        $post_id = wp_insert_post($postarr);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        if (!empty($data['categories']) && is_array($data['categories'])) {
            $cat_ids = array();
            foreach ($data['categories'] as $cat_name) {
                $cat_id = $this->ensure_category($cat_name);
                if (!is_wp_error($cat_id)) {
                    $cat_ids[] = $cat_id;
                }
            }
            if (!empty($cat_ids)) {
                wp_set_post_categories($post_id, $cat_ids);
            }
        }

        if (!empty($data['tags']) && is_array($data['tags'])) {
            wp_set_post_tags($post_id, $data['tags']);
        }

        return $post_id;
    }

    private function ensure_category($cat_name)
    {
        $term = term_exists($cat_name, 'category');
        if ($term !== 0 && $term !== null) {
            return $term['term_id'];
        }
        $new_term = wp_insert_term($cat_name, 'category');
        if (is_wp_error($new_term)) {
            return $new_term;
        }
        return $new_term['term_id'];
    }

    private function validate_date($date, $format = 'Y-m-d\\TH:i:s')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}
