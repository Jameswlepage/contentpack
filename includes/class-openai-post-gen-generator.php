<?php

if (! defined('ABSPATH')) {
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
        // If not provided, we can ask the model to generate on the fly by calling a simplified plan or direct post generation prompt.
        if (empty($title)) {
            // We'll just generate a simple post with minimal instructions
            $topic_msg = $topic ? "Topic: $topic\n" : "";
            $theme_msg = $theme ? "Theme: $theme\n" : "";
            $prompt = "You are a professional content writer. Generate a single WordPress post.\n$topic_msg$theme_msgReturn JSON with title, slug, summary, content, publish_date (ISO 8601), categories, tags.\n";
            $response = $this->plugin->api->request(array(
                array('role' => 'system', 'content' => 'You are a helpful assistant.'),
                array('role' => 'user', 'content' => $prompt)
            ));
            if (is_wp_error($response)) {
                return $response;
            }
            $data = json_decode($response, true);
        } else {
            // If we have details, generate using the more structured method
            $response = $this->plugin->api->generate_single_post_content($title, $slug, $summary, $categories, $tags, $theme, $topic);
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

        // We will store all posts info to handle internal linking after insertion
        $all_posts_info = array();

        // Generate each post's full content
        foreach ($plan['clusters'] as $cluster_data) {
            if (empty($cluster_data['cluster_topic']) || empty($cluster_data['posts'])) {
                continue;
            }
            $cluster_topic = $cluster_data['cluster_topic'];
            foreach ($cluster_data['posts'] as $post_info) {
                if (empty($post_info['title']) || empty($post_info['slug'])) {
                    continue;
                }

                $title = $post_info['title'];
                $slug = $post_info['slug'];
                $summary = isset($post_info['summary']) ? $post_info['summary'] : '';
                $categories = isset($post_info['categories']) ? $post_info['categories'] : array();
                $tags = isset($post_info['tags']) ? $post_info['tags'] : array();
                $response = $this->plugin->api->generate_single_post_content(
                    $title,
                    $slug,
                    $summary,
                    $categories,
                    $tags,
                    $theme,
                    $cluster_topic
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

        $this->add_internal_links($all_posts_info);

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

        // Categories
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

        // Tags
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

    private function validate_date($date, $format = 'Y-m-d\TH:i:s')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    private function add_internal_links($all_posts_info)
    {
        // Group by cluster_topic
        $posts_by_cluster = array();
        foreach ($all_posts_info as $info) {
            $cluster = $info['cluster_topic'];
            if (!isset($posts_by_cluster[$cluster])) {
                $posts_by_cluster[$cluster] = array();
            }
            $posts_by_cluster[$cluster][] = $info;
        }

        foreach ($posts_by_cluster as $cluster => $posts) {
            if (count($posts) < 2) continue;

            foreach ($posts as $current_post) {
                $post_id = $current_post['id'];
                $content = get_post_field('post_content', $post_id);
                $related_links = "<h3>Related posts in this cluster:</h3><ul>";
                foreach ($posts as $other_post) {
                    if ($other_post['id'] == $post_id) continue;
                    $related_links .= '<li><a href="' . get_permalink($other_post['id']) . '">' . esc_html($other_post['title']) . '</a></li>';
                }
                $related_links .= "</ul>";
                $content .= "\n\n" . $related_links;
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_content' => $content
                ));
            }
        }
    }
}
