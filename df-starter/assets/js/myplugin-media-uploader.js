jQuery(document).ready(function($){
    var file_frame;

    window.upload_image = function(event) {
        event.preventDefault();

        if (file_frame) {
            file_frame.open();
            return;
        }

        file_frame = wp.media({
            title: 'Select or Upload an Image',
            button: {
                text: 'Use this image'
            },
            multiple: false,
            library: { type: 'image' }
        });

        file_frame.on('select', function() {
            var attachment = file_frame.state().get('selection').first().toJSON();
            $('#upload_image_preview').html('<img src="' + attachment.url + '" style="max-width: 100%; height: auto; max-height: 200px; object-fit: contain; display: inline-block;" />');
            fetch(myplugin_ajax.ajax_url, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: new URLSearchParams({
                    action: "update_login_logo_image",
                    url: attachment.url
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success)
                {
                    const element = document.querySelector("#login h1 a");
                    if (element)
                    {
                        element.style.backgroundImage = "url('" + attachment.url + "')";
                    }
                }
                
            });
        });

        file_frame.open();
    }
});