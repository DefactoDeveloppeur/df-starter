let updateColorTimeout;

function background_pick(element) {
    clearTimeout(updateColorTimeout);
    updateColorTimeout = setTimeout(() => {
        update_color(element.value, 'update_login_color', (json_data) => {
            if (json_data.success) { 
                const element1 = document.getElementById("login-panel");
                element1.style.backgroundColor = element.value;
            } 
        });
    }, 500);
}

function background_text_pick(element) {
    clearTimeout(updateColorTimeout);
    updateColorTimeout = setTimeout(() => {
        update_color(element.value, 'update_login_bg_text_color', (json_data) => { 
            if (json_data.success) { 
                const element1 = document.querySelector("#nav a");
                const element2 = document.querySelector("#backtoblog a");

                element1.style.color = element.value;
                element2.style.color = element.value;
            } 
        });
    }, 500);
}

function update_color(color, action, callback)
{
    set_default(
        new URLSearchParams({ 
            action: action, 
            color: color
        }),
        function(json_data) {
            if (callback !== null)
                callback(json_data);
        }
    );   
}

function set_default_bg_color()
{
    update_color('#f0f0f1', 'update_login_color', function(json_data) { 
        if (json_data.success) { 
            document.getElementById('bgColorPicker').value = '#f0f0f1'; 
            const element = document.getElementById("login-panel");
            element.style.backgroundColor = "#f0f0f1";
        } 
    });    
}

function set_default_bg_text_color()
{
    update_color('#50575e', 'update_login_bg_text_color', function(json_data) { 
        if (json_data.success) { 
            document.getElementById('bgTextColorPicker').value = '#50575e'; 
            const element1 = document.querySelector("#nav a");
            const element2 = document.querySelector("#backtoblog a");
            
            element1.style.color = '#50575e';
            element2.style.color = '#50575e';
        } 
    });    
}

function set_default_logo()
{
    let default_url = myplugin_ajax.default_url;
    set_default(
        new URLSearchParams({ 
            action: "update_login_logo_image", 
            url: default_url 
        }),
        function(json_data) {
            if (json_data.success) {
                document.getElementById("upload_image_preview").innerHTML = '<img src="' + default_url + '" style="max-width: 100%; height: auto; max-height: 200px; object-fit: contain; display: inline-block;" />';
                const element = document.querySelector("#login h1 a");
                if (element)
                {
                    element.style.backgroundImage = "url('" + default_url + "')";
                }
            }
        }
    );      
}

function set_default(body, callback)
{
    fetch(myplugin_ajax.ajax_url, {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: body
    })
    .then(res => res.json())
    .then(data => {
        callback(data);
    });
}