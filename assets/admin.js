jQuery(document).ready(function ($) {
    var galleryFrame;
    var galleryContainer = $('#homestay-gallery-container');
    var galleryInput = $('#homestay-gallery-ids');

    // Add Images
    $('#add-gallery-images').on('click', function (e) {
        e.preventDefault();

        if (galleryFrame) {
            galleryFrame.open();
            return;
        }

        galleryFrame = wp.media({
            title: 'Select Homestay Images',
            button: {
                text: 'Add to Gallery'
            },
            multiple: true
        });

        galleryFrame.on('select', function () {
            var selection = galleryFrame.state().get('selection');
            var ids = galleryInput.val() ? galleryInput.val().split(',') : [];

            selection.map(function (attachment) {
                attachment = attachment.toJSON();
                if (ids.indexOf(attachment.id.toString()) === -1) {
                    ids.push(attachment.id);
                    galleryContainer.append(
                        '<div class="gallery-image-item" data-id="' + attachment.id + '">' +
                        '<img src="' + attachment.sizes.thumbnail.url + '" alt="">' +
                        '<button type="button" class="remove-gallery-image">&times;</button>' +
                        '</div>'
                    );
                }
            });

            galleryInput.val(ids.join(','));
        });

        galleryFrame.open();
    });

    // Remove Image
    galleryContainer.on('click', '.remove-gallery-image', function (e) {
        e.preventDefault();
        var item = $(this).closest('.gallery-image-item');
        var imageId = item.data('id').toString();
        var ids = galleryInput.val().split(',');

        ids = ids.filter(function (id) {
            return id !== imageId;
        });

        galleryInput.val(ids.join(','));
        item.remove();
    });

    // Make gallery sortable
    if (typeof $.fn.sortable !== 'undefined') {
        galleryContainer.sortable({
            update: function () {
                var ids = [];
                galleryContainer.find('.gallery-image-item').each(function () {
                    ids.push($(this).data('id'));
                });
                galleryInput.val(ids.join(','));
            }
        });
    }
    // Nearby Attractions Repeater
    var attractionsWrapper = $('#homestay-attractions-wrapper');

    // Add Row
    $('#add-attraction-row').on('click', function (e) {
        e.preventDefault();
        var row = '<div class="attraction-row" style="margin-bottom: 10px; display: flex; gap: 10px; align-items: center;">' +
            '<input type="text" name="homestay_attractions[]" class="widefat" placeholder="Attraction Name - Distance">' +
            '<button type="button" class="button remove-attraction-row" aria-label="Remove">&times;</button>' +
            '</div>';
        attractionsWrapper.append(row);
    });

    // Remove Row
    attractionsWrapper.on('click', '.remove-attraction-row', function (e) {
        e.preventDefault();
        if (confirm('Are you sure you want to remove this attraction?')) {
            $(this).closest('.attraction-row').remove();
        }
    });

    // House Rules Dos Repeater
    var dosWrapper = $('#homestay-rules-dos-wrapper');
    $('#add-rules-dos-row').on('click', function (e) {
        e.preventDefault();
        var row = '<div class="rules-dos-row" style="margin-bottom: 10px; display: flex; gap: 10px; align-items: center;">' +
            '<input type="text" name="homestay_rules_dos[]" class="widefat" placeholder="Allowed activity">' +
            '<button type="button" class="button remove-rules-dos-row" aria-label="Remove">&times;</button>' +
            '</div>';
        dosWrapper.append(row);
    });
    dosWrapper.on('click', '.remove-rules-dos-row', function (e) {
        e.preventDefault();
        $(this).closest('.rules-dos-row').remove();
    });

    // House Rules Donts Repeater
    var dontsWrapper = $('#homestay-rules-donts-wrapper');
    $('#add-rules-donts-row').on('click', function (e) {
        e.preventDefault();
        var row = '<div class="rules-donts-row" style="margin-bottom: 10px; display: flex; gap: 10px; align-items: center;">' +
            '<input type="text" name="homestay_rules_donts[]" class="widefat" placeholder="Restricted activity">' +
            '<button type="button" class="button remove-rules-donts-row" aria-label="Remove">&times;</button>' +
            '</div>';
        dontsWrapper.append(row);
    });
    dontsWrapper.on('click', '.remove-rules-donts-row', function (e) {
        e.preventDefault();
        $(this).closest('.rules-donts-row').remove();
    });

});
