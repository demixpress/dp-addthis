(function ($) {
    $(document)
        .on('ready', function () {
            postboxes.add_postbox_toggles(pagenow);
        })
        .on('click', '.add-item', function (e) {
            e.preventDefault();

            var $container = $(this).parents('.items-container');

            var $template = $('<div></div>').html($container.find('.tmpl-item').html());
            var uniqid = Math.random().toString(36).substr(2, 9);
            var itemHTML = $template.html().replace(/cloneindex/gi, uniqid);

            $container.find('tbody').append(itemHTML);
        })
        .on('click', '.delete-item', function (e) {
            e.preventDefault();

            $(this).parents('tr').remove();
        });
})(jQuery);