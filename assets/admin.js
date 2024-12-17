jQuery(document).ready(function ($) {
    $('#opg-generate-single').on('click', function (e) {
        e.preventDefault();
        var title = $('#opg-single-title').val();
        var topic = $('#opg-single-topic').val();
        var theme = $('#opg-single-theme').val();

        $('#opg-status').html('Generating single post...');

        $.post(opgAjax.ajax_url, {
            action: 'openai_post_gen_generate',
            security: opgAjax.nonce,
            type: 'single',
            title: title,
            topic: topic,
            theme: theme
        }, function (response) {
            if (response.success) {
                $('#opg-status').html('<span style="color:green;">' + response.data + '</span>');
            } else {
                $('#opg-status').html('<span style="color:red;">Error: ' + response.data + '</span>');
            }
        });
    });

    $('#opg-generate-bulk').on('click', function (e) {
        e.preventDefault();
        var count = $('#opg-cluster-count').val();
        var topics = $('#opg-cluster-topics').val();
        var theme = $('#opg-general-theme').val();

        $('#opg-status').html('Generating bulk posts...');

        $.post(opgAjax.ajax_url, {
            action: 'openai_post_gen_generate',
            security: opgAjax.nonce,
            type: 'bulk',
            count: count,
            topics: topics,
            theme: theme
        }, function (response) {
            if (response.success) {
                $('#opg-status').html('<span style="color:green;">' + response.data + '</span>');
            } else {
                $('#opg-status').html('<span style="color:red;">Error: ' + response.data + '</span>');
            }
        });
    });
});
