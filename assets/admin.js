jQuery(document).ready(function ($) {

    function recalcTotalPosts() {
        var clusterCount = parseInt($('#opg-cluster-count').val(), 10) || 0;

        // Right now this is forecasting things but I'd like to make it so we can actually set the number of posts per cluster in the future

        var totalPosts = clusterCount * 5;
        $('#opg-total-posts').text(totalPosts);

        if (totalPosts > 50) {
            $('#opg-warning').show();
        } else {
            $('#opg-warning').hide();
        }
    }


    $('#opg-cluster-count, #opg-cluster-topics').on('input change', function () {
        recalcTotalPosts();
    });


    recalcTotalPosts();

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
