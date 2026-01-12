<?php



class DFS_ContactPlugin {
    public function __construct() {
        add_action('admin_footer', [$this, 'contact_overlay']);
        //add_action('wp_footer', [$this, 'contact_overlay']);
        add_action('wp_ajax_send_mail', [$this, 'handle_send_mail']);
    }

    public function contact_overlay() {
        ?>
        <style>
            #client-help-btn {
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: #0073aa;
                color: white;
                border: none;
                border-radius: 50%;
                width: 60px;
                height: 60px;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
                z-index: 9999;
                transition: background 0.3s ease, transform 0.2s ease;
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            }

            #client-help-btn:hover {
                background: #005f8d;
                transform: scale(1.05);
            }

            #client-help-form {
                display: none;
                position: fixed;
                bottom: 100px;
                right: 20px;
                width: 260px;
                background: #fff;
                border-radius: 12px;
                border: 1px solid #ddd;
                padding: 15px;
                box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
                z-index: 9999;
                animation: fadeIn 0.3s ease;
            }

            #client-help-form input {
                width: 100%;
                padding: 8px;
                border: 1px solid #ccc;
                border-radius: 8px;
                font-family: inherit;
                font-size: 14px;
                box-sizing: border-box;
            }

            #client-help-form textarea {
                margin-top: 5px;
                width: 100%;
                height: 100px;
                padding: 8px;
                resize: none;
                border: 1px solid #ccc;
                border-radius: 8px;
                font-family: inherit;
                font-size: 14px;
            }

            #client-help-form button {
                margin-top: 10px;
                background-color: #0073aa;
                color: white;
                border: none;
                padding: 8px 12px;
                border-radius: 6px;
                cursor: pointer;
                transition: background 0.3s ease;
                width: 100%;
                font-size: 15px;
            }

            #client-help-form button:hover {
                background-color: #005f8d;
            }

            #client-help-status {
                animation: fadeIn 0.3s ease;
            }

            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        </style>

        <button id="client-help-btn">?</button>
        <div id="client-help-form">
            <p>Demande d'assistance</p>
            <div id="client-help-status" style="display: none; color: green; font-weight: bold; margin-bottom: 8px;">
                Email envoyé!
            </div>
            <input id="objet-textarea" placeholder="Objet..." type="text">
            <textarea id="message-textarea" placeholder="Message..."></textarea>
            <button onclick="send_mail()">Send</button>
        </div>

        <script>
            const helpBtn = document.getElementById('client-help-btn');
            const helpForm = document.getElementById('client-help-form');

            helpBtn.addEventListener('click', () => {
                if (helpForm.style.display === 'block') {
                    helpForm.style.display = 'none';
                } else {
                    helpForm.style.display = 'block';
                }
            });

            function send_mail()
            {
                const object_textarea = document.getElementById("objet-textarea");
                const message_textarea = document.getElementById("message-textarea");

                ajax_call(
                    new URLSearchParams({ action: "send_mail", subject: object_textarea.value, message: message_textarea.value }),
                    json_data => {
                        if (!json_data.success) {
                            alert(json_data.data.message);
                        } else {
                            const status = document.getElementById("client-help-status");
                            status.style.display = "block";
                            status.textContent = "Email envoyé! ✅";

                            setTimeout(() => {
                                status.style.display = "none";
                            }, 3000);
                        }
                    }
                );
            }

            function ajax_call(body, callback)
            {
                fetch("<?=admin_url('admin-ajax.php')?>", {
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
        </script>
        <?php
    }

    public function handle_send_mail()
    {
        if (isset($_POST['subject']) && isset($_POST['message']))
        {
            $to = 'assistance@studiodefacto.com';
            $current_user = wp_get_current_user();
            $username = $current_user->user_login;
            $email = $current_user->user_email;
            $subject = $_POST['subject'];
            $message = $_POST['message'];
            $message = "Message de " . $username . "\n" . $email . "\n\n" . $message;
            $sent = wp_mail($to, $subject, $message);
            if ($sent) {     
                wp_send_json_success(['message' => "Le mail à bien été envoyé!"]);
                wp_die();
            } else {
                wp_send_json_error(['message' => "Une erreur s'est produite en envoyant le mail, veuillez réessayer ultérieurement"]);
                wp_die();
            }
        } else if (!isset($_POST['subject'])) {
            wp_send_json_error(['message' => "Veuillez bien indiquer un object valide"]);
            wp_die();
        } else if (!isset($_POST['message'])) {
            wp_send_json_error(['message' => "Veuillez bien indiquer un message valide"]);
            wp_die();
        } else {
            wp_send_json_error(['message' => "Une erreur s'est produite, veuillez réessayer ultérieurement"]);
            wp_die();
        }
    }
}