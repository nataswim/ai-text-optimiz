<?php
/*
Plugin Name: AI Text Optimizer
Description: Ajoute un bouton IA à l'éditeur classique et WPBakery pour corriger, enrichir et optimiser le texte avec IA.
Version: 2.2
Author: Hassan  EL HAOUAT MyCreaNet
Text Domain: ai-text
*/

// Sécurité : Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// Constantes du plugin
define('AI_TEXT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AI_TEXT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('AI_TEXT_VERSION', '2.2');

// Activation du plugin
register_activation_hook(__FILE__, 'ai_text_activation');
function ai_text_activation() {
    add_option('ai_text_api_provider', 'gemini'); // Défaut : Google Gemini (gratuit)
    add_option('ai_text_api_key', '');
    add_option('ai_text_model', 'gemini-1.5-flash'); // Nouveau modèle par défaut
    add_option('ai_text_temperature', 0.7);
    add_option('ai_text_max_tokens', 1024);
}

// Désactivation du plugin
register_deactivation_hook(__FILE__, 'ai_text_deactivation');
function ai_text_deactivation() {
    // Nettoyage si nécessaire
}

// Initialisation du plugin
add_action('init', 'ai_text_init');
function ai_text_init() {
    load_plugin_textdomain('ai-text', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

// Enregistrement des scripts et styles - VERSION ÉTENDUE
add_action('admin_enqueue_scripts', 'ai_text_enqueue_scripts');
function ai_text_enqueue_scripts($hook) {
    // Pages où le plugin doit être chargé - ÉTENDU À TOUT LE SITE
    $allowed_hooks = array(
        'post.php', 
        'post-new.php', 
        'edit.php',
        'edit-tags.php',
        'term.php',
        'profile.php',
        'user-edit.php'
    );
    
    // Vérifier aussi les custom post types
    global $post_type;
    $is_editor_page = in_array($hook, $allowed_hooks) || 
                     (isset($_GET['action']) && $_GET['action'] === 'edit') ||
                     (isset($post_type) && !empty($post_type));
    
    if (!$is_editor_page && $hook !== 'settings_page_ai-text-settings') {
        return;
    }
    
    // Enregistrer le script principal
    wp_enqueue_script(
        'ai-text-js',
        AI_TEXT_PLUGIN_URL . 'ai-text-tinymce.js',
        array('jquery'),
        AI_TEXT_VERSION . '-' . time(),
        true
    );

    // Variables pour l'IA
    $ai_vars = array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ai_text_nonce'),
        'strings' => array(
            'select_text' => __('Sélectionnez le texte à optimiser.', 'ai-text'),
            'processing' => __('Traitement en cours...', 'ai-text'),
            'error' => __('Erreur lors du traitement.', 'ai-text'),
            'no_response' => __('Aucune réponse de l\'IA.', 'ai-text'),
            'connection_error' => __('Erreur de connexion.', 'ai-text')
        ),
        'isGlobalEnabled' => true // NOUVEAU : Activer globalement
    );
    
    // Détecter le type de contenu
    $ai_vars['isWooCommerce'] = class_exists('WooCommerce');
    $ai_vars['isProduct'] = false;
    $ai_vars['postType'] = '';
    $ai_vars['currentHook'] = $hook;
    
    // Vérifier le type de post
    if (isset($_GET['post']) && is_numeric($_GET['post'])) {
        $post = get_post($_GET['post']);
        if ($post) {
            $ai_vars['isProduct'] = $post->post_type === 'product';
            $ai_vars['postType'] = $post->post_type;
        }
    } elseif (isset($_GET['post_type'])) {
        $ai_vars['isProduct'] = $_GET['post_type'] === 'product';
        $ai_vars['postType'] = $_GET['post_type'];
    } elseif (isset($post_type)) {
        $ai_vars['postType'] = $post_type;
    }
    
    // Forcer la détection sur les pages produit
    global $post;
    if ($post && $post->post_type === 'product') {
        $ai_vars['isProduct'] = true;
        $ai_vars['postType'] = 'product';
    }
    
    // Détecter WPBakery
    $ai_vars['hasWPBakery'] = defined('WPB_VC_VERSION');
    
    wp_localize_script('ai-text-js', 'aiTextVars', $ai_vars);
    
    // CSS pour les boutons - Version globale
    wp_add_inline_style('wp-admin', '
        .ai-woo-button {
            background: #0073aa !important;
            color: white !important;
            border: none !important;
            padding: 8px 12px !important;
            margin: 8px 0 0 0 !important;
            border-radius: 4px !important;
            cursor: pointer !important;
            font-size: 12px !important;
            font-weight: 500 !important;
            text-decoration: none !important;
            display: inline-block !important;
            vertical-align: top !important;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2) !important;
            transition: all 0.2s ease !important;
            line-height: 1.3 !important;
        }
        .ai-woo-button:hover {
            background: #005a87 !important;
            color: white !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3) !important;
        }
        .ai-woo-button:active {
            transform: translateY(0) !important;
        }
        .ai-woo-button:focus {
            outline: none !important;
            box-shadow: 0 0 0 2px rgba(0,115,170,0.3) !important;
        }
        
        .ai-title-container {
            margin-top: 8px !important;
            display: block !important;
            clear: both !important;
        }
        .ai-slug-container {
            margin-top: 8px !important;
            display: block !important;
            clear: both !important;
        }
        
        /* Assurer la visibilité sur les pages WooCommerce */
        #titlewrap + .ai-title-container {
            display: block !important;
            visibility: visible !important;
        }
        
        #edit-slug-box + .ai-slug-container {
            display: block !important;
            visibility: visible !important;
        }
        
        /* Styles spécifiques pour les pages produit */
        .post-type-product .ai-title-container,
        .post-type-product .ai-slug-container {
            display: block !important;
            visibility: visible !important;
        }
        
        /* Style pour les notifications */
        .ai-text-notification {
            position: fixed !important;
            top: 32px !important;
            right: 20px !important;
            padding: 12px 20px !important;
            border-radius: 6px !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
            z-index: 999999 !important;
            font-size: 14px !important;
            font-weight: 500 !important;
            min-width: 280px !important;
            max-width: 400px !important;
            word-wrap: break-word !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
            transition: all 0.3s ease !important;
        }
        
        .ai-text-success {
            background: #00a32a !important;
            color: white !important;
        }
        
        .ai-text-error {
            background: #d63638 !important;
            color: white !important;
        }
        
        .ai-text-warning {
            background: #f56e28 !important;
            color: white !important;
        }
        
        .ai-text-info {
            background: #0073aa !important;
            color: white !important;
        }
        
        /* Boutons IA dans TinyMCE */
        .ai-text-btn {
            background: #0073aa !important;
            color: white !important;
            border: none !important;
            padding: 6px 12px !important;
            margin: 2px !important;
            border-radius: 4px !important;
            cursor: pointer !important;
            font-size: 12px !important;
            font-weight: bold !important;
            height: 30px !important;
            line-height: 1.2 !important;
        }
        
        .ai-text-btn:hover {
            background: #005a87 !important;
            color: white !important;
        }
        
        /* Support WPBakery */
        .wpb_element_wrapper .ai-text-btn,
        .vc_element .ai-text-btn {
            display: inline-block !important;
            visibility: visible !important;
            z-index: 99999 !important;
        }
        
        /* Support Gutenberg */
        .block-editor .ai-text-btn,
        .editor-styles-wrapper .ai-text-btn {
            display: inline-block !important;
        }
        
        /* Menu AI dans tous les contextes */
        #ai-text-menu {
            z-index: 999999 !important;
        }
    ');
    
    // Debug JavaScript amélioré
    wp_add_inline_script('ai-text-js', '
        console.log(" AI Text Plugin chargé");
        console.log("📄 Hook actuel:", "' . $hook . '");
        console.log("🔧 Variables AI:", aiTextVars);
        console.log("🛒 WooCommerce:", aiTextVars.isWooCommerce);
        console.log("📦 Page produit:", aiTextVars.isProduct);
        console.log("🌐 Global activé:", aiTextVars.isGlobalEnabled);
        
        // Fonction de debug globale
        window.aiTextDebugInfo = function() {
            console.log("=== DEBUG AI TEXT ===");
            console.log("Hook:", "' . $hook . '");
            console.log("Variables:", aiTextVars);
            console.log("Champ titre:", document.getElementById("title"));
            console.log("Champ slug:", document.getElementById("post_name"));
            console.log("TinyMCE:", typeof tinymce !== "undefined" ? tinymce.editors : "Non chargé");
            console.log("WPBakery:", typeof window.vc !== "undefined" ? "Détecté" : "Non détecté");
            console.log("===================");
        };
    ');
    
    // Styles pour l'interface admin
    if ($hook === 'settings_page_ai-text-settings') {
        wp_enqueue_style(
            'ai-text-admin-css',
            AI_TEXT_PLUGIN_URL . 'ai-text-admin.css',
            array(),
            AI_TEXT_VERSION
        );
    }
}

// Ajout du menu d'administration
add_action('admin_menu', 'ai_text_admin_menu');
function ai_text_admin_menu() {
    add_options_page(
        __('AI Text Optimizer', 'ai-text'),
        __('AI Text', 'ai-text'),
        'manage_options',
        'ai-text-settings',
        'ai_text_admin_page'
    );
}

// Page d'administration
function ai_text_admin_page() {
    if (isset($_POST['submit'])) {
        check_admin_referer('ai_text_admin_nonce');
        
        update_option('ai_text_api_provider', sanitize_text_field($_POST['api_provider']));
        update_option('ai_text_api_key', sanitize_text_field($_POST['api_key']));
        update_option('ai_text_model', sanitize_text_field($_POST['model']));
        update_option('ai_text_temperature', floatval($_POST['temperature']));
        update_option('ai_text_max_tokens', intval($_POST['max_tokens']));
        update_option('ai_text_search_api_key', sanitize_text_field($_POST['search_api_key']));
        update_option('ai_text_search_engine_id', sanitize_text_field($_POST['search_engine_id']));
        
        echo '<div class="notice notice-success"><p>' . __('Paramètres sauvegardés !', 'ai-text') . '</p></div>';
    }
    
    $api_provider = get_option('ai_text_api_provider', 'gemini');
    $api_key = get_option('ai_text_api_key', '');
    $model = get_option('ai_text_model', 'gemini-1.5-flash');
    $temperature = get_option('ai_text_temperature', 0.7);
    $max_tokens = get_option('ai_text_max_tokens', 1024);
    $search_api_key = get_option('ai_text_search_api_key', '');
    $search_engine_id = get_option('ai_text_search_engine_id', '');
    ?>
    
    <div class="wrap ai-text-admin">
        <h1><?php _e('AI Text Optimizer - Configuration', 'ai-text'); ?></h1>
        
        <div class="ai-text-admin-content">
            <form method="post" action="">
                <?php wp_nonce_field('ai_text_admin_nonce'); ?>
                
                <div class="ai-text-section">
                    <h2><?php _e('Configuration de l\'API IA', 'ai-text'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="api_provider"><?php _e('Fournisseur d\'API', 'ai-text'); ?></label>
                            </th>
                            <td>
                                <select id="api_provider" name="api_provider" onchange="updateModelOptions()">
                                    <option value="gemini" <?php selected($api_provider, 'gemini'); ?>>🆓 Google Gemini (Gratuit)</option>
                                    <option value="groq" <?php selected($api_provider, 'groq'); ?>>⚡ Groq (Gratuit + Rapide)</option>
                                    <option value="huggingface" <?php selected($api_provider, 'huggingface'); ?>>🤗 Hugging Face (Gratuit)</option>
                                    <option value="cohere" <?php selected($api_provider, 'cohere'); ?>>📝 Cohere (Gratuit)</option>
                                    <option value="openai" <?php selected($api_provider, 'openai'); ?>>💰 OpenAI (Payant)</option>
                                </select>
                                <p class="description"><?php _e('Choisissez votre fournisseur d\'IA préféré', 'ai-text'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="api_key"><?php _e('Clé API', 'ai-text'); ?></label>
                            </th>
                            <td>
                                <input type="password" id="api_key" name="api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" required />
                                <div id="api_instructions">
                                    <p class="description" id="gemini_instructions" style="<?php echo $api_provider === 'gemini' ? '' : 'display:none'; ?>">
                                        🆓 <strong>Google Gemini (Gratuit) :</strong> <a href="https://makersuite.google.com/app/apikey" target="_blank">Obtenir une clé API gratuite</a>
                                    </p>
                                    <p class="description" id="groq_instructions" style="<?php echo $api_provider === 'groq' ? '' : 'display:none'; ?>">
                                        ⚡ <strong>Groq (Gratuit + Rapide) :</strong> <a href="https://console.groq.com/keys" target="_blank">Obtenir une clé API gratuite</a>
                                    </p>
                                    <p class="description" id="huggingface_instructions" style="<?php echo $api_provider === 'huggingface' ? '' : 'display:none'; ?>">
                                        🤗 <strong>Hugging Face (Gratuit) :</strong> <a href="https://huggingface.co/settings/tokens" target="_blank">Obtenir un token gratuit</a>
                                    </p>
                                    <p class="description" id="cohere_instructions" style="<?php echo $api_provider === 'cohere' ? '' : 'display:none'; ?>">
                                        📝 <strong>Cohere (Gratuit) :</strong> <a href="https://dashboard.cohere.ai/api-keys" target="_blank">Obtenir une clé API gratuite</a>
                                    </p>
                                    <p class="description" id="openai_instructions" style="<?php echo $api_provider === 'openai' ? '' : 'display:none'; ?>">
                                        💰 <strong>OpenAI (Payant) :</strong> <a href="https://platform.openai.com/api-keys" target="_blank">Obtenir une clé API</a>
                                    </p>
                                </div>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="model"><?php _e('Modèle IA', 'ai-text'); ?></label>
                            </th>
                            <td>
                                <select id="model" name="model">
                                    <!-- Options seront remplies par JavaScript -->
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="temperature"><?php _e('Température', 'ai-text'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="temperature" name="temperature" value="<?php echo esc_attr($temperature); ?>" min="0" max="1" step="0.1" />
                                <p class="description"><?php _e('Contrôle la créativité (0 = conservateur, 1 = créatif)', 'ai-text'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="max_tokens"><?php _e('Tokens maximum', 'ai-text'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="max_tokens" name="max_tokens" value="<?php echo esc_attr($max_tokens); ?>" min="100" max="4096" />
                                <p class="description"><?php _e('Longueur maximale de la réponse', 'ai-text'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="ai-text-section">
                    <h2><?php _e('Configuration Recherche Web (Optionnel)', 'ai-text'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="search_api_key"><?php _e('Clé API Google Custom Search', 'ai-text'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="search_api_key" name="search_api_key" value="<?php echo esc_attr($search_api_key); ?>" class="regular-text" />
                                <p class="description"><?php _e('Pour la recherche web automatique', 'ai-text'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="search_engine_id"><?php _e('ID du moteur de recherche', 'ai-text'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="search_engine_id" name="search_engine_id" value="<?php echo esc_attr($search_engine_id); ?>" class="regular-text" />
                                <p class="description"><?php _e('ID du Custom Search Engine', 'ai-text'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button(); ?>
            </form>
            
            <div class="ai-text-section">
                <h2>✨ <?php _e('Fonctionnalités du plugin', 'ai-text'); ?></h2>
                <div style="background: #e7f3ff; padding: 15px; border-radius: 6px; border-left: 4px solid #0073aa;">
                    <h4 style="margin-top: 0; color: #0073aa;"> Plugin activé globalement !</h4>
                    <p>Le plugin AI Text est maintenant disponible sur <strong>tout votre site</strong> :</p>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin: 15px 0;">
                        <div style="background: white; padding: 15px; border-radius: 6px; border: 1px solid #ddd;">
                            <h5 style="margin-top: 0; color: #0073aa;">📝 Articles & Pages</h5>
                            <p style="font-size: 13px; margin-bottom: 0;">Bouton IA disponible dans l'éditeur classique et Gutenberg.</p>
                        </div>
                        
                        <div style="background: white; padding: 15px; border-radius: 6px; border: 1px solid #ddd;">
                            <h5 style="margin-top: 0; color: #0073aa;">🎨 WPBakery</h5>
                            <p style="font-size: 13px; margin-bottom: 0;">Support complet pour WPBakery Page Builder.</p>
                        </div>
                        
                        <?php if (class_exists('WooCommerce')): ?>
                        <div style="background: white; padding: 15px; border-radius: 6px; border: 1px solid #ddd;">
                            <h5 style="margin-top: 0; color: #0073aa;">🛒 WooCommerce</h5>
                            <p style="font-size: 13px; margin-bottom: 0;">Fonctions spéciales pour optimiser titres et slugs produits.</p>
                        </div>
                        <?php endif; ?>
                        
                        <div style="background: white; padding: 15px; border-radius: 6px; border: 1px solid #ddd;">
                            <h5 style="margin-top: 0; color: #0073aa;">🔧 Custom Post Types</h5>
                            <p style="font-size: 13px; margin-bottom: 0;">Compatible avec tous les types de contenu personnalisés.</p>
                        </div>
                    </div>
                    
                    <p style="margin-bottom: 0;"><strong>Comment utiliser :</strong> Sélectionnez du texte dans n'importe quel éditeur et cliquez sur le bouton 🤖 IA pour optimiser votre contenu !</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function updateModelOptions() {
        const provider = document.getElementById('api_provider').value;
        const modelSelect = document.getElementById('model');
        const currentModel = '<?php echo esc_js($model); ?>';
        
        // Cacher toutes les instructions
        document.querySelectorAll('#api_instructions p').forEach(p => p.style.display = 'none');
        
        // Afficher les instructions du provider sélectionné
        const instruction = document.getElementById(provider + '_instructions');
        if (instruction) instruction.style.display = 'block';
        
        // Modèles disponibles par provider
        const models = {
            gemini: [
                {value: 'gemini-1.5-flash', text: 'Gemini 1.5 Flash (Recommandé)'},
                {value: 'gemini-1.5-pro', text: 'Gemini 1.5 Pro (Plus intelligent)'},
                {value: 'gemini-1.0-pro', text: 'Gemini 1.0 Pro'}
            ],
            groq: [
                {value: 'llama3-8b-8192', text: 'Llama 3 8B (Rapide)'},
                {value: 'llama3-70b-8192', text: 'Llama 3 70B (Plus intelligent)'},
                {value: 'mixtral-8x7b-32768', text: 'Mixtral 8x7B'},
                {value: 'gemma-7b-it', text: 'Gemma 7B'}
            ],
            huggingface: [
                {value: 'microsoft/DialoGPT-medium', text: 'DialoGPT Medium'},
                {value: 'microsoft/DialoGPT-large', text: 'DialoGPT Large'},
                {value: 'facebook/blenderbot-400M-distill', text: 'BlenderBot 400M'}
            ],
            cohere: [
                {value: 'command', text: 'Command (Recommandé)'},
                {value: 'command-light', text: 'Command Light (Plus rapide)'}
            ],
            openai: [
                {value: 'gpt-3.5-turbo', text: 'GPT-3.5 Turbo (Recommandé)'},
                {value: 'gpt-4', text: 'GPT-4'},
                {value: 'gpt-4-turbo', text: 'GPT-4 Turbo'}
            ]
        };
        
        // Vider et remplir les options
        modelSelect.innerHTML = '';
        models[provider].forEach(model => {
            const option = document.createElement('option');
            option.value = model.value;
            option.textContent = model.text;
            option.selected = model.value === currentModel;
            modelSelect.appendChild(option);
        });
    }
    
    // Initialiser au chargement
    document.addEventListener('DOMContentLoaded', updateModelOptions);
    </script>
    <?php
}

// Traitement AJAX principal
add_action('wp_ajax_ai_text_process', 'ai_text_process_text');
function ai_text_process_text() {
    // Debug temporaire
    error_log('AI Text Debug: Fonction appelée');
    
    check_ajax_referer('ai_text_nonce', 'nonce');
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(__('Permissions insuffisantes.', 'ai-text'));
    }
    
    $api_key = get_option('ai_text_api_key');
    if (empty($api_key)) {
        wp_send_json_error(__('Clé API non configurée. Allez dans Réglages > AI Text pour la configurer.', 'ai-text'));
    }
    
    $title = sanitize_text_field($_POST['title'] ?? '');
    $content = wp_kses_post($_POST['content'] ?? '');
    $action_type = sanitize_text_field($_POST['action_type'] ?? 'optimize');
    
    // Debug
    error_log('AI Text Debug: title=' . $title . ', action=' . $action_type . ', content_length=' . strlen($content));
    
    $web_data = '';
    if ($action_type === 'create_content') {
        $web_data = ai_text_search_web($content);
    }
    
    $prompt = ai_text_build_prompt($title, $content, $action_type, $web_data);
    
    $response = ai_text_call_ai($prompt); // Utilisation de la nouvelle fonction multi-API
    
    if (is_wp_error($response)) {
        error_log('AI Text Debug Error: ' . $response->get_error_message());
        wp_send_json_error($response->get_error_message());
    }
    
    error_log('AI Text Debug: Succès');
    wp_send_json_success($response);
}

// Test AJAX simple
add_action('wp_ajax_ai_text_test', 'ai_text_test');
function ai_text_test() {
    wp_send_json_success('Test AJAX fonctionne !');
}

// Test API multi-provider
add_action('wp_ajax_ai_text_test_api', 'ai_text_test_api');
function ai_text_test_api() {
    $api_provider = get_option('ai_text_api_provider', 'gemini');
    $api_key = get_option('ai_text_api_key');
    
    if (empty($api_key)) {
        wp_send_json_error('Clé API non configurée');
    }
    
    // Test simple
    $test_prompt = 'Dis bonjour en français en une phrase.';
    $response = ai_text_call_ai($test_prompt);
    
    if (is_wp_error($response)) {
        wp_send_json_error('Erreur ' . $api_provider . ': ' . $response->get_error_message());
    }
    
    wp_send_json_success(array(
        'provider' => $api_provider,
        'response' => $response,
        'success' => true
    ));
}

// Mode démo (temporaire)
add_action('wp_ajax_ai_text_demo', 'ai_text_demo');
function ai_text_demo() {
    check_ajax_referer('ai_text_nonce', 'nonce');
    
    $title = sanitize_text_field($_POST['title'] ?? '');
    $content = wp_kses_post($_POST['content'] ?? '');
    $action_type = sanitize_text_field($_POST['action_type'] ?? 'optimize');
    
    // Simulation de réponse IA avec formatage HTML
    $demo_responses = array(
        'correct' => "<p>Voici le texte corrigé :</p>\n\n<p>" . $content . "</p>\n\n<p><em>(Corrections apportées : orthographe et grammaire optimisées)</em></p>",
        
        'optimize' => "<h2>" . $title . "</h2>\n\n<p><strong>Contenu SEO optimisé :</strong></p>\n\n<p>" . $content . "</p>\n\n<ul>\n<li>Mots-clés intégrés naturellement</li>\n<li>Structure améliorée pour le référencement</li>\n<li>Lisibilité optimisée</li>\n</ul>",
        
        'enrich' => "<p>" . $content . "</p>\n\n<h3>Informations complémentaires</h3>\n\n<p>Ce contenu a été enrichi avec :</p>\n\n<ul>\n<li>Des détails techniques supplémentaires</li>\n<li>Des exemples pertinents</li>\n<li>Des informations contextuelles</li>\n<li>Une meilleure structure narrative</li>\n</ul>\n\n<p><em>Le texte original a été développé pour offrir une valeur ajoutée au lecteur.</em></p>",
        
        'create_content' => "<h2>Article généré : " . $title . "</h2>\n\n<p><strong>Introduction captivante</strong> basée sur : <em>" . $content . "</em></p>\n\n<h3>Caractéristiques principales</h3>\n\n<ul>\n<li>Performance exceptionnelle</li>\n<li>Facilité d'utilisation</li>\n<li>Rapport qualité-prix optimal</li>\n</ul>\n\n<h3>Avantages détaillés</h3>\n\n<p>Ce produit se distingue par ses <strong>fonctionnalités avancées</strong> et sa <em>conception ergonomique</em>.</p>\n\n<h3>Conclusion</h3>\n\n<p>Un excellent choix pour tous ceux qui recherchent <strong>qualité</strong> et <strong>fiabilité</strong>.</p>"
    );
    
    $response = $demo_responses[$action_type] ?? $demo_responses['optimize'];
    
    wp_send_json_success($response . "\n\n<p><small><em>--- MODE DÉMO ACTIVÉ ---</em></small></p>");
}

// ACTION AJAX POUR OPTIMISER LE TITRE
add_action('wp_ajax_ai_text_optimize_title', 'ai_text_optimize_title');
function ai_text_optimize_title() {
    check_ajax_referer('ai_text_nonce', 'nonce');
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(__('Permissions insuffisantes.', 'ai-text'));
    }
    
    $api_key = get_option('ai_text_api_key');
    if (empty($api_key)) {
        wp_send_json_error(__('Clé API non configurée. Allez dans Réglages > AI Text pour la configurer.', 'ai-text'));
    }
    
    $current_title = sanitize_text_field($_POST['current_title'] ?? '');
    $content = wp_kses_post($_POST['content'] ?? '');
    $post_type = sanitize_text_field($_POST['post_type'] ?? 'post');
    
    if (empty($current_title)) {
        wp_send_json_error(__('Titre requis.', 'ai-text'));
    }
    
    // Prompt spécialisé pour optimiser le titre
    $prompt = "Titre actuel : \"$current_title\"\n\nContenu du produit : $content\n\nOptimise ce titre pour le SEO et l'e-commerce. Le titre doit être :\n- Accrocheur et vendeur\n- Optimisé pour le référencement\n- Contenir les mots-clés principaux\n- Faire moins de 60 caractères\n- Être en français\n- Attractif pour inciter au clic\n\nRenvoie uniquement le titre optimisé, sans guillemets ni formatage.";
    
    $response = ai_text_call_ai($prompt);
    
    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    }
    
    // Nettoyer la réponse
    $optimized_title = trim($response);
    $optimized_title = preg_replace('/^["\']|["\']$/', '', $optimized_title); // Supprimer les guillemets
    $optimized_title = substr($optimized_title, 0, 60); // Limiter à 60 caractères
    
    wp_send_json_success($optimized_title);
}

// ACTION AJAX POUR OPTIMISER LE SLUG
add_action('wp_ajax_ai_text_optimize_slug', 'ai_text_optimize_slug');
function ai_text_optimize_slug() {
    check_ajax_referer('ai_text_nonce', 'nonce');
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(__('Permissions insuffisantes.', 'ai-text'));
    }
    
    $api_key = get_option('ai_text_api_key');
    if (empty($api_key)) {
        wp_send_json_error(__('Clé API non configurée. Allez dans Réglages > AI Text pour la configurer.', 'ai-text'));
    }
    
    $title = sanitize_text_field($_POST['title'] ?? '');
    $current_slug = sanitize_text_field($_POST['current_slug'] ?? '');
    $content = wp_kses_post($_POST['content'] ?? '');
    $post_type = sanitize_text_field($_POST['post_type'] ?? 'post');
    
    if (empty($title)) {
        wp_send_json_error(__('Titre requis.', 'ai-text'));
    }
    
    // Prompt spécialisé pour optimiser le slug
    $prompt = "Titre : \"$title\"\n\nSlug actuel : \"$current_slug\"\n\nContenu : $content\n\nCrée un slug URL optimisé pour le SEO basé sur ce titre. Le slug doit être :\n- En minuscules\n- Sans accents ni caractères spéciaux\n- Séparé par des tirets\n- Court mais descriptif (3-5 mots maximum)\n- Contenir les mots-clés principaux\n- Être en français mais formaté pour une URL\n\nExemples : 'chaussures-running-homme' ou 'ordinateur-portable-gaming'\n\nRenvoie uniquement le slug optimisé, sans guillemets.";
    
    $response = ai_text_call_ai($prompt);
    
    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    }
    
    // Nettoyer et formater le slug
    $optimized_slug = trim($response);
    $optimized_slug = preg_replace('/^["\']|["\']$/', '', $optimized_slug); // Supprimer les guillemets
    $optimized_slug = strtolower($optimized_slug);
    $optimized_slug = remove_accents($optimized_slug);
    $optimized_slug = preg_replace('/[^a-z0-9-]/', '', $optimized_slug);
    $optimized_slug = preg_replace('/-+/', '-', $optimized_slug);
    $optimized_slug = trim($optimized_slug, '-');
    
    wp_send_json_success($optimized_slug);
}

// Test pour vérifier que tout fonctionne
add_action('wp_ajax_ai_text_test_woocommerce', 'ai_text_test_woocommerce');
function ai_text_test_woocommerce() {
    check_ajax_referer('ai_text_nonce', 'nonce');
    
    $response = array(
        'woocommerce_active' => class_exists('WooCommerce'),
        'api_key_configured' => !empty(get_option('ai_text_api_key')),
        'current_user_can_edit' => current_user_can('edit_posts'),
        'actions_registered' => array(
            'optimize_title' => has_action('wp_ajax_ai_text_optimize_title'),
            'optimize_slug' => has_action('wp_ajax_ai_text_optimize_slug')
        )
    );
    
    wp_send_json_success($response);
}

// Construction du prompt selon le type d'action
function ai_text_build_prompt($title, $content, $action_type, $web_data = '') {
    $prompts = array(
        'optimize' => "Titre : $title\n\nTexte : $content\n\nCorrige les fautes, améliore le style et optimise le SEO. IMPORTANT : Formate le texte avec des balises HTML appropriées (<p>, <strong>, <em>, etc.). Renvoie uniquement le texte optimisé en HTML.",
        
        'correct' => "Titre : $title\n\nTexte : $content\n\nCorrige uniquement les fautes d'orthographe, de grammaire et de syntaxe. IMPORTANT : Conserve la structure originale mais formate avec des balises HTML (<p>, <br>, etc.). Renvoie uniquement le texte corrigé en HTML.",
        
        'enrich' => "Titre : $title\n\nTexte : $content\n\nEnrichis ce texte en ajoutant des détails pertinents, des exemples et des informations complémentaires. IMPORTANT : Structure le contenu avec des balises HTML (<h3>, <p>, <strong>, <ul>, <li>, etc.) pour une meilleure lisibilité. Renvoie uniquement le texte enrichi en HTML.",
        
        'create_content' => "Titre : $title\n\nIdée/Mots-clés : $content\n\nInformations web récentes :\n$web_data\n\nCrée un article complet et optimisé SEO basé sur ces informations. IMPORTANT : Structure le contenu avec des balises HTML complètes :\n- Utilisez <h2> et <h3> pour les sous-titres\n- <p> pour les paragraphes\n- <strong> pour les mots importants\n- <ul> et <li> pour les listes\n- <em> pour l'emphase\nRenvoie uniquement l'article structuré en HTML."
    );
    
    return $prompts[$action_type] ?? $prompts['optimize'];
}

// Recherche web pour enrichir le contenu
function ai_text_search_web($query) {
    $api_key = get_option('ai_text_search_api_key');
    $engine_id = get_option('ai_text_search_engine_id');
    
    if (empty($api_key) || empty($engine_id)) {
        return '';
    }
    
    $url = "https://www.googleapis.com/customsearch/v1?key=$api_key&cx=$engine_id&q=" . urlencode($query) . "&num=5";
    
    $response = wp_remote_get($url, array('timeout' => 10));
    
    if (is_wp_error($response)) {
        return '';
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (!isset($data['items'])) {
        return '';
    }
    
    $web_info = '';
    foreach ($data['items'] as $item) {
        $web_info .= "Titre: " . $item['title'] . "\n";
        $web_info .= "Description: " . $item['snippet'] . "\n";
        $web_info .= "URL: " . $item['link'] . "\n\n";
    }
    
    return $web_info;
}

// Appel à l'API IA (multi-provider)
function ai_text_call_ai($prompt) {
    $api_provider = get_option('ai_text_api_provider', 'gemini');
    $api_key = get_option('ai_text_api_key');
    $model = get_option('ai_text_model', 'gemini-pro');
    $temperature = get_option('ai_text_temperature', 0.7);
    $max_tokens = get_option('ai_text_max_tokens', 1024);
    
    switch ($api_provider) {
        case 'gemini':
            return ai_text_call_gemini($prompt, $api_key, $model, $temperature, $max_tokens);
        case 'groq':
            return ai_text_call_groq($prompt, $api_key, $model, $temperature, $max_tokens);
        case 'huggingface':
            return ai_text_call_huggingface($prompt, $api_key, $model, $temperature, $max_tokens);
        case 'cohere':
            return ai_text_call_cohere($prompt, $api_key, $model, $temperature, $max_tokens);
        case 'openai':
            return ai_text_call_openai_old($prompt, $api_key, $model, $temperature, $max_tokens);
        default:
            return new WP_Error('api_error', __('Fournisseur d\'API non supporté.', 'ai-text'));
    }
}

// Google Gemini API (GRATUIT) - Corrigé
function ai_text_call_gemini($prompt, $api_key, $model, $temperature, $max_tokens) {
    // Utiliser la nouvelle API v1 au lieu de v1beta
    $url = "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key={$api_key}";
    
    $body = array(
        'contents' => array(
            array(
                'parts' => array(
                    array('text' => $prompt)
                )
            )
        ),
        'generationConfig' => array(
            'temperature' => floatval($temperature),
            'maxOutputTokens' => intval($max_tokens)
        )
    );
    
    $response = wp_remote_post($url, array(
        'headers' => array(
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($body),
        'timeout' => 60
    ));
    
    if (is_wp_error($response)) {
        return new WP_Error('api_error', __('Erreur Gemini: ' . $response->get_error_message(), 'ai-text'));
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    if ($response_code !== 200) {
        $error_data = json_decode($response_body, true);
        $error_message = 'Code ' . $response_code;
        if ($error_data && isset($error_data['error']['message'])) {
            $error_message .= ': ' . $error_data['error']['message'];
        }
        return new WP_Error('api_error', __('Erreur Gemini: ' . $error_message, 'ai-text'));
    }
    
    $body = json_decode($response_body, true);
    
    if (!isset($body['candidates'][0]['content']['parts'][0]['text'])) {
        return new WP_Error('api_error', __('Réponse Gemini invalide.', 'ai-text'));
    }
    
    return $body['candidates'][0]['content']['parts'][0]['text'];
}

// Groq API (GRATUIT + RAPIDE)
function ai_text_call_groq($prompt, $api_key, $model, $temperature, $max_tokens) {
    $body = array(
        'model' => $model,
        'messages' => array(
            array(
                'role' => 'system',
                'content' => 'Tu es un rédacteur SEO professionnel et expert en création de contenu web français.'
            ),
            array(
                'role' => 'user',
                'content' => $prompt
            )
        ),
        'temperature' => floatval($temperature),
        'max_tokens' => intval($max_tokens)
    );
    
    $response = wp_remote_post('https://api.groq.com/openai/v1/chat/completions', array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ),
        'body' => json_encode($body),
        'timeout' => 60
    ));
    
    if (is_wp_error($response)) {
        return new WP_Error('api_error', __('Erreur Groq: ' . $response->get_error_message(), 'ai-text'));
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    if ($response_code !== 200) {
        $error_data = json_decode($response_body, true);
        $error_message = 'Code ' . $response_code;
        if ($error_data && isset($error_data['error']['message'])) {
            $error_message .= ': ' . $error_data['error']['message'];
        }
        return new WP_Error('api_error', __('Erreur Groq: ' . $error_message, 'ai-text'));
    }
    
    $body = json_decode($response_body, true);
    
    if (!isset($body['choices'][0]['message']['content'])) {
        return new WP_Error('api_error', __('Réponse Groq invalide.', 'ai-text'));
    }
    
    return $body['choices'][0]['message']['content'];
}

// Formatage automatique du contenu
function ai_text_format_content($content, $action_type) {
    // Si le contenu contient déjà du HTML, le laisser tel quel
    if (preg_match('/<[^>]+>/', $content)) {
        return $content;
    }
    
    // Sinon, formater automatiquement le texte brut
    $formatted = '';
    
    // Diviser le contenu en paragraphes
    $paragraphs = preg_split('/\n\s*\n/', trim($content));
    
    foreach ($paragraphs as $paragraph) {
        $paragraph = trim($paragraph);
        if (empty($paragraph)) continue;
        
        // Détecter les titres (lignes courtes qui semblent être des titres)
        if (strlen($paragraph) < 80 && !preg_match('/[.!?]$/', $paragraph) && 
            (preg_match('/^[A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞŸ]/', $paragraph) || 
             preg_match('/:$/', $paragraph))) {
            
            // Déterminer le niveau de titre
            if (preg_match('/^(I{1,3}|[0-9]{1,2}\.|\w+:)/', $paragraph)) {
                $formatted .= '<h3>' . esc_html($paragraph) . '</h3>' . "\n";
            } else {
                $formatted .= '<h2>' . esc_html($paragraph) . '</h2>' . "\n";
            }
        } else {
            // Formater comme paragraphe
            $paragraph = ai_text_enhance_paragraph($paragraph);
            $formatted .= '<p>' . $paragraph . '</p>' . "\n";
        }
    }
    
    return $formatted;
}

// Améliorer un paragraphe avec du formatage inline
function ai_text_enhance_paragraph($text) {
    // Échapper le HTML existant
    $text = esc_html($text);
    
    // Mettre en gras les termes techniques et noms de produits
    $text = preg_replace('/\b([A-Z][a-z]+ [0-9]+[a-z]*( [A-Z]+)?)\b/', '<strong>$1</strong>', $text);
    
    // Mettre en gras les caractéristiques importantes
    $text = preg_replace('/\b([0-9]+\s*(mètres?|km|niveaux?|heures?|mm|m²|%|€|dollars?))\b/i', '<strong>$1</strong>', $text);
    
    // Mettre en emphase les mots-clés importants
    $keywords = array('étanche', 'ergonomique', 'précis', 'rechargeable', 'programmable', 'évolutif', 'pratique');
    foreach ($keywords as $keyword) {
        $text = preg_replace('/\b(' . preg_quote($keyword, '/') . ')\b/i', '<em>$1</em>', $text);
    }
    
    // Créer des listes à partir de phrases contenant des énumérations
    if (preg_match_all('/([^.!?]*(?:comprennent?|incluent?|sont)[^.!?]*(?:,\s*[^.!?]*){2,}[.!?])/', $text, $matches)) {
        foreach ($matches[0] as $list_sentence) {
            $parts = preg_split('/(?:comprennent?|incluent?|sont)\s*/', $list_sentence, 2);
            if (count($parts) == 2) {
                $intro = trim($parts[0]);
                $items = preg_split('/,\s*(?:et\s*)?/', trim($parts[1]));
                
                $list_html = $intro . ' comprennent :<ul>';
                foreach ($items as $item) {
                    $item = trim($item, ' .,!?');
                    if (!empty($item)) {
                        $list_html .= '<li>' . ucfirst($item) . '</li>';
                    }
                }
                $list_html .= '</ul>';
                
                $text = str_replace($list_sentence, $list_html, $text);
            }
        }
    }
    
    return $text;
}

// Hugging Face API (GRATUIT)
function ai_text_call_huggingface($prompt, $api_key, $model, $temperature, $max_tokens) {
    $url = "https://api-inference.huggingface.co/models/{$model}";
    
    $body = array(
        'inputs' => $prompt,
        'parameters' => array(
            'temperature' => floatval($temperature),
            'max_new_tokens' => intval($max_tokens),
            'return_full_text' => false
        )
    );
    
    $response = wp_remote_post($url, array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ),
        'body' => json_encode($body),
        'timeout' => 60
    ));
    
    if (is_wp_error($response)) {
        return new WP_Error('api_error', __('Erreur Hugging Face: ' . $response->get_error_message(), 'ai-text'));
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    if ($response_code !== 200) {
        $error_data = json_decode($response_body, true);
        $error_message = 'Code ' . $response_code;
        if ($error_data && isset($error_data['error'])) {
            $error_message .= ': ' . $error_data['error'];
        }
        return new WP_Error('api_error', __('Erreur Hugging Face: ' . $error_message, 'ai-text'));
    }
    
    $body = json_decode($response_body, true);
    
    if (!isset($body[0]['generated_text'])) {
        return new WP_Error('api_error', __('Réponse Hugging Face invalide.', 'ai-text'));
    }
    
    return $body[0]['generated_text'];
}

// Cohere API (GRATUIT)
function ai_text_call_cohere($prompt, $api_key, $model, $temperature, $max_tokens) {
    $body = array(
        'model' => $model,
        'prompt' => $prompt,
        'temperature' => floatval($temperature),
        'max_tokens' => intval($max_tokens)
    );
    
    $response = wp_remote_post('https://api.cohere.ai/v1/generate', array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ),
        'body' => json_encode($body),
        'timeout' => 60
    ));
    
    if (is_wp_error($response)) {
        return new WP_Error('api_error', __('Erreur Cohere: ' . $response->get_error_message(), 'ai-text'));
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    if ($response_code !== 200) {
        $error_data = json_decode($response_body, true);
        $error_message = 'Code ' . $response_code;
        if ($error_data && isset($error_data['message'])) {
            $error_message .= ': ' . $error_data['message'];
        }
        return new WP_Error('api_error', __('Erreur Cohere: ' . $error_message, 'ai-text'));
    }
    
    $body = json_decode($response_body, true);
    
    if (!isset($body['generations'][0]['text'])) {
        return new WP_Error('api_error', __('Réponse Cohere invalide.', 'ai-text'));
    }
    
    return $body['generations'][0]['text'];
}

// OpenAI API (renommée pour éviter les conflits)
function ai_text_call_openai_old($prompt, $api_key, $model, $temperature, $max_tokens) {
    $body = array(
        'model' => $model,
        'messages' => array(
            array(
                'role' => 'system',
                'content' => 'Tu es un rédacteur SEO professionnel et expert en création de contenu web français.'
            ),
            array(
                'role' => 'user',
                'content' => $prompt
            )
        ),
        'temperature' => floatval($temperature),
        'max_tokens' => intval($max_tokens)
    );
    
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ),
        'body' => json_encode($body),
        'timeout' => 60
    ));
    
    if (is_wp_error($response)) {
        return new WP_Error('api_error', __('Erreur OpenAI: ' . $response->get_error_message(), 'ai-text'));
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    if ($response_code !== 200) {
        $error_data = json_decode($response_body, true);
        $error_message = 'Code ' . $response_code;
        if ($error_data && isset($error_data['error']['message'])) {
            $error_message .= ': ' . $error_data['error']['message'];
        }
        return new WP_Error('api_error', __('Erreur OpenAI: ' . $error_message, 'ai-text'));
    }
    
    $body = json_decode($response_body, true);
    
    if (!isset($body['choices'][0]['message']['content'])) {
        return new WP_Error('api_error', __('Réponse OpenAI invalide.', 'ai-text'));
    }
    
    return $body['choices'][0]['message']['content'];
}

// Hook pour charger sur TOUTES les pages d'édition
add_action('admin_footer', 'ai_text_editor_page_script');
function ai_text_editor_page_script() {
    global $post;
    
    // Charger sur toutes les pages avec éditeur, pas seulement les produits
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->base, array('post', 'page', 'edit', 'edit-tags', 'term'))) {
        return;
    }
    
    ?>
    <script>
    (function() {
        console.log(' AI Text Script chargé sur:', '<?php echo $screen->post_type ?? $screen->base; ?>');
        
        // Forcer l'initialisation du plugin
        function forceInitPlugin() {
            if (typeof aiTextDebug !== 'undefined') {
                console.log('🔧 Initialisation forcée du plugin AI Text');
                if (aiTextDebug.init) {
                    aiTextDebug.init();
                }
                // Pour WooCommerce spécifiquement
                if (aiTextDebug.addWooCommerceButtons && <?php echo ($post && $post->post_type === 'product') ? 'true' : 'false'; ?>) {
                    aiTextDebug.addWooCommerceButtons();
                }
            }
        }
        
        // Essayer plusieurs fois
        setTimeout(forceInitPlugin, 1000);
        setTimeout(forceInitPlugin, 2000);
        setTimeout(forceInitPlugin, 3000);
        
        // Support WPBakery
        if (window.vc && window.vc.events) {
            window.vc.events.on('app:ready', forceInitPlugin);
        }
    })();
    </script>
    <?php
}

// Ajout du lien vers les paramètres
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ai_text_settings_link');
function ai_text_settings_link($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=ai-text-settings') . '">' . __('Paramètres', 'ai-text') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
?>