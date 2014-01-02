jQuery(document).ready(function($){
    var _orig_send_attachment = wp.media.editor.send.attachment;
    $('#menu-to-edit').on('click', 'img.media-upload', function(e) {
        var button = $(this);
        var label = button.parent();
        var type = label.data('type');
        var id = label.data('id');
        wp.media.editor.send.attachment = function(props, attachment){
            $("#selected-menu-item-"+type+"-id-"+id).val(attachment.id);
            console.dir(attachment);
            url=(attachment.sizes.thumbnail !== undefined) ? attachment.sizes.thumbnail.url : attachment.url;
            button.attr('src',url);
        }

        wp.media.editor.open(button);

        return false;
    });
});
