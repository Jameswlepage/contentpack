# Bulk AI Post Generator

**Version:** 1.0  
**Author:** James LePage

## Description

The OpenAI Post Generator plugin allows you to generate WordPress posts using the OpenAI GPT-4o model, leveraging structured JSON outputs defined by a provided JSON schema. You can generate a single post or multiple posts in clusters. Each cluster generates a fixed number of posts (default: 5 posts per cluster) and you can specify topics and a general theme for bulk generation.

This plugin also supports WP-CLI commands to generate posts programmatically.

This is intended to quickly generate content to make it easy to test search, clustering, and other AI features for WordPress that require a lot of unique content.

### Key Features

- **OpenAI API Integration:** Uses OpenAI GPT-4o(-mini) model for content generation.
- **JSON Schema Enforcement:** Ensures returned content matches specified JSON structures in `main.schema.json`.
- **Single and Bulk Post Generation:**
  - Generate a single post with optional parameters (title, topic, theme).
  - Generate multiple clusters of posts at once, with each cluster producing multiple posts.
- **Internal Linking:** For bulk-generated posts, internal links to other posts in the same cluster are naturally integrated into the post content.
- **Dynamic UI:** Calculates total number of posts based on the number of clusters (clusters \* 5 posts per cluster) and warns when generating a large number of posts.
- **WP-CLI Support:** Run `wp openai-post-gen single` or `wp openai-post-gen bulk` from the command line.
- **Admin Interface Styling:** Improved UI with custom CSS.

## Installation

1. Download or clone this repository into your `wp-content/plugins/` directory so that the file structure looks like:

   ```
   wp-content/
     plugins/
       openai-post-gen/
         openai-post-gen.php
         admin/
           class-openai-post-gen-admin.php
         assets/
           admin.css
           admin.js
           schema/
             main.schema.json
         cli/
           class-openai-post-gen-cli.php
         includes/
           class-openai-post-gen.php
           class-openai-post-gen-api.php
           class-openai-post-gen-generator.php
   ```

2. Activate the plugin from the **Plugins** page in the WordPress admin.

## Configuration

1. Go to **Settings > OpenAI Post Gen**.
2. Enter your OpenAI API Key and click **Save API Key**.

## Usage

### Single Post Generation

- Navigate to **Settings > OpenAI Post Gen**.
- In the "Single Post Generation" section, optionally enter a title, topic, and theme.
- Click **Generate Single Post**.
- A new post will be created and published.

### Bulk Post Generation

- Under "Bulk Generation," specify:
  - Number of clusters (e.g. 3).
  - Cluster topics, comma-separated (e.g. "Artificial Intelligence Ethics, Machine Learning Applications, Data Privacy Regulations").
  - A general theme.
- The plugin calculates total posts as `number_of_clusters * 5`. If the number is large (e.g., >50), a warning will appear.
- Click **Generate Bulk Posts** to create a plan and then generate all posts accordingly.

**Note:** Each cluster produces exactly 5 posts. The generated posts will include internal links to other posts in the same cluster, integrated naturally in the content.

### WP-CLI Commands

From your WordPress root directory (where `wp-cli` is installed):

- **Single post:**

  ```
  wp openai-post-gen single --title="My AI Post" --topic="AI Innovation" --theme="Emerging tech"
  ```

- **Bulk posts:**
  ```
  wp openai-post-gen bulk --clusters='["Cluster A","Cluster B"]' --theme="General Theme" --count=3
  ```
  This command will generate 3 clusters, each producing 5 posts, for a total of 15 posts.

## Schema and Structured Outputs

The plugin uses `main.schema.json` located in `assets/schema/` to enforce JSON schemas on the responses returned by the OpenAI API. Both the plan (bulk) and single post outputs adhere to their respective schemas, ensuring that generated data is structured correctly.

## Modifications and Notes

- The admin UI calculates the total post count dynamically and displays a warning if the total is large.
- Internal linking is done by passing other posts in the same cluster to the model at generation time, prompting it to include inline links. The previous "Related posts" section is no longer used.
- The schema and strict JSON mode ensure reliable, structured content returned from the model.

## Troubleshooting

- **No API Key:** Make sure to set your OpenAI API key under **Settings > OpenAI Post Gen**.
- **Large Bulk Requests:** If you attempt to generate a very large number of posts, it may cause unexpected behavior. Consider generating fewer clusters at a time.
- **Missing or Invalid JSON:** If the model fails to return valid JSON due to network issues or internal errors, try again or reduce complexity.

## License

GPL2

## TODO

- Add a way to set the number of posts per cluster
- Add a way to set the total number of posts to generate
