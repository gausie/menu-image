jQuery(document).ready(function($){
    var _orig_send_attachment = wp.media.editor.send.attachment;

    $('.menu-image-picker-button').click(function(e) {
        var button = $(this);
        var id = button.data('item-id');
        wp.media.editor.send.attachment = function(props, attachment){
            console.log(attachment);
            $("#selected-menu-item-image-id-"+id).val(attachment.id);
            url=(attachment.sizes.thumbnail !== undefined) ? attachment.sizes.thumbnail.url : attachment.url;
            $("#menu-image-preview-"+id).attr('src',url);
        }

        wp.media.editor.open(button);

        return false;
    });
});
