<?php

if (!defined('ABSPATH')) {
    exit;
}

class OpenAI_Post_Gen_Admin
{
    private $plugin;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
    }

    public function init()
    {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('wp_ajax_openai_post_gen_generate', array($this, 'handle_ajax_generate'));
    }

    public function add_settings_page()
    {
        add_management_page(
            'Post Generator',
            'Post Generator',
            'manage_options',
            'openai-post-gen',
            array($this, 'settings_page_html')
        );
    }

    public function register_settings()
    {
        register_setting($this->plugin->option_name, $this->plugin->option_name, array(
            'sanitize_callback' => array($this, 'sanitize_options')
        ));
        add_settings_section('openai_post_gen_section', 'OpenAI Settings', null, 'openai-post-gen');
        add_settings_field('api_key', 'OpenAI API Key', array($this, 'field_api_key'), 'openai-post-gen', 'openai_post_gen_section');
    }

    public function sanitize_options($options)
    {
        $clean = array();
        $clean['api_key'] = isset($options['api_key']) ? sanitize_text_field($options['api_key']) : '';
        return $clean;
    }

    public function field_api_key()
    {
        $options = get_option($this->plugin->option_name);
        $val = isset($options['api_key']) ? $options['api_key'] : '';
        echo '<input type="text" name="' . $this->plugin->option_name . '[api_key]" value="' . esc_attr($val) . '" style="width:400px;">';
    }

    public function settings_page_html()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
?>
        <div class="wrap opg-wrap">
            <h1>Post Generator</h1>
            <form method="post" action="options.php" class="opg-form">
                <?php
                settings_fields($this->plugin->option_name);
                do_settings_sections('openai-post-gen');
                submit_button('Save API Key');
                ?>
            </form>

            <hr>
            <h2>Generate Posts</h2>
            <p>Use the form below to generate either a single post or multiple clustered posts.</p>
            <div id="openai-post-gen-ui" class="opg-panel">
                <h3>Single Post Generation</h3>
                <p><small>Specify optional title, topic, theme:</small></p>
                <input type="text" id="opg-single-title" placeholder="Title (optional)" class="regular-text">
                <input type="text" id="opg-single-topic" placeholder="Topic (optional)" class="regular-text">
                <input type="text" id="opg-single-theme" placeholder="Theme (optional)" class="regular-text">
                <button id="opg-generate-single" class="button button-primary">Generate Single Post</button>

                <hr>
                <h3>Bulk Generation</h3>
                <p><small>Specify number of clusters, cluster topics, and general theme. By default, each cluster generates 5 posts. The total posts = number_of_clusters * 5. Adjust carefully!</small></p>
                <label>Number of clusters: <input type="number" id="opg-cluster-count" min="1" value="3" class="small-text"></label><br><br>
                <label>Cluster topics (comma-separated):<br>
                    <input type="text" id="opg-cluster-topics" style="width:400px;"
                        value="Artificial Intelligence Ethics, Machine Learning Applications, Data Privacy Regulations"
                        placeholder="e.g. Healthy Recipes, Fitness Tips" class="regular-text">
                </label><br><br>
                <label>General theme:<br>
                    <textarea id="opg-general-theme" style="width:400px;height:100px;">Create comprehensive content about the impact of AI technology on modern society. Focus on explaining complex concepts in an accessible way, addressing concerns and opportunities, and exploring real-world implications for businesses and individuals.</textarea>
                </label><br><br>

                <p><strong>Estimated Total Posts:</strong> <span id="opg-total-posts">0</span></p>
                <p id="opg-warning" style="color:red;display:none;">Warning: Generating a very large number of posts may cause unexpected behavior.</p>

                <button id="opg-generate-bulk" class="button button-primary">Generate Bulk Posts</button>

                <div id="opg-status" style="margin-top:20px;"></div>
            </div>
        </div>
<?php
    }

    public function admin_assets($hook)
    {
        if ($hook === 'tools_page_openai-post-gen') {
            wp_enqueue_script('opg-admin-js', plugin_dir_url(__FILE__) . '../assets/admin.js', array('jquery'), '1.0', true);
            wp_localize_script('opg-admin-js', 'opgAjax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('opg_ajax_nonce')
            ));
            wp_enqueue_style('opg-admin-css', plugin_dir_url(__FILE__) . '../assets/admin.css', array(), '1.0', 'all');
        }
    }

    public function handle_ajax_generate()
    {
        check_ajax_referer('opg_ajax_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized user');
        }

        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';

        if ($type === 'single') {
            $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
            $topic = isset($_POST['topic']) ? sanitize_text_field($_POST['topic']) : '';
            $theme = isset($_POST['theme']) ? sanitize_text_field($_POST['theme']) : '';
            $res = $this->plugin->generator->generate_single_post($title, $topic, $theme);
            if (is_wp_error($res)) {
                wp_send_json_error($res->get_error_message());
            } else {
                wp_send_json_success("Single post generated with ID: " . $res);
            }
        } elseif ($type === 'bulk') {
            $count = isset($_POST['count']) ? intval($_POST['count']) : 5;
            $topics_str = isset($_POST['topics']) ? sanitize_text_field($_POST['topics']) : '';
            $clusters = array_filter(array_map('trim', explode(',', $topics_str)));
            $theme = isset($_POST['theme']) ? stripslashes_deep($_POST['theme']) : '';

            if (empty($clusters)) {
                wp_send_json_error('No cluster topics provided.');
            }

            $res = $this->plugin->generator->generate_bulk_posts($clusters, $theme, $count);
            if (is_wp_error($res)) {
                wp_send_json_error($res->get_error_message());
            } else {
                wp_send_json_success("Bulk posts generated successfully.");
            }
        } else {
            wp_send_json_error('Invalid generation type.');
        }
    }
}
