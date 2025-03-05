<?php
/**
 * Plugin Name: AI Content Generator for WordPress
 * Plugin URI: https://seu-site.com/ai-content-generator
 * Description: Plugin avançado para WordPress que utiliza Inteligência Artificial para gerar conteúdo de alta qualidade, com suporte a múltiplos modelos de IA como OpenRouter e Groq.
 * Version: 1.5.1
 * Author: Seu Nome
 * Author URI: https://seu-site.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-content-generator
 * Domain Path: /languages
 *
 * @package AIContentGenerator
 */

// Se este arquivo for chamado diretamente, aborta.
if (!defined('WPINC')) {
    die;
}

// Define constantes do plugin
define('AICG_VERSION', '1.5.1');
define('AICG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AICG_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Código executado durante a ativação do plugin.
 */
function aicg_activate() {
    // Adiciona opção para a chave da API do OpenRouter
    add_option('aicg_openrouter_api_key', '');
    
    // Adiciona opção para a chave da API do Groq
    add_option('aicg_groq_api_key', '');
    
    // Limpa o cache de permissões
    if (function_exists('wp_roles')) {
        wp_roles()->for_site();
    }
}
register_activation_hook(__FILE__, 'aicg_activate');

/**
 * Código executado durante a desativação do plugin.
 */
function aicg_deactivate() {
    // Limpa configurações temporárias se necessário
}
register_deactivation_hook(__FILE__, 'aicg_deactivate');

/**
 * Carrega os arquivos necessários
 */
function aicg_load_files() {
    // Carrega a classe de geração de conteúdo com IA
    require_once AICG_PLUGIN_DIR . 'includes/class-ai-content.php';
}
add_action('plugins_loaded', 'aicg_load_files');

/**
 * Desativa o Gutenberg
 */
function aicg_disable_gutenberg($is_enabled, $post_type) {
    if (!aicg_check_user_permissions()) {
        return true; // Mantém Gutenberg para usuários não autorizados
    }
    
    if (!aicg_check_post_type($post_type)) {
        return true; // Mantém Gutenberg para post types não selecionados
    }
    
    return false; // Desativa Gutenberg
}
add_filter('use_block_editor_for_post_type', 'aicg_disable_gutenberg', 100, 2);

/**
 * Inicializa o plugin
 */
function aicg_init() {
    // Carrega o texto domain para traduções
    load_plugin_textdomain('ai-content-generator', false, dirname(plugin_basename(__FILE__)) . '/languages');
    
    // Remove suporte ao editor de blocos
    remove_theme_support('core-block-patterns');
    
    // Força o editor clássico
    add_filter('use_block_editor_for_post', '__return_false', 100);
    
    // Adiciona suporte aos estilos do editor
    add_editor_style('assets/css/editor-styles.css');
}
add_action('init', 'aicg_init');

/**
 * Adiciona menu de configurações no admin
 */
function aicg_admin_menu() {
    add_options_page(
        'AI Content Generator Settings',
        'AI Content Generator',
        'manage_options',
        'ai-content-generator',
        'aicg_options_page'
    );
}
add_action('admin_menu', 'aicg_admin_menu');

/**
 * Renderiza a página de configurações
 */
function aicg_fetch_groq_models() {
    $api_key = get_option('aicg_groq_api_key');
    if (empty($api_key)) {
        return array();
    }

    $args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        )
    );

    $response = wp_remote_get('https://api.groq.com/openai/v1/models', $args);

    if (is_wp_error($response)) {
        return array();
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    return isset($data['data']) ? $data['data'] : array();
}

function aicg_options_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('aicg_options');
            ?>
            <h2><?php _e('API Settings', 'ai-content-generator'); ?></h2>
            <p><?php _e('Configure suas chaves de API para começar a gerar conteúdo com IA.', 'ai-content-generator'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="aicg_openrouter_api_key"><?php _e('OpenRouter API Key', 'ai-content-generator'); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               id="aicg_openrouter_api_key"
                               name="aicg_openrouter_api_key"
                               value="<?php echo esc_attr(get_option('aicg_openrouter_api_key')); ?>"
                               class="regular-text">
                        <p class="description">
                            <?php _e('Entre com sua chave da API do OpenRouter. Obtenha uma em openrouter.ai', 'ai-content-generator'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="aicg_groq_api_key"><?php _e('Groq API Key', 'ai-content-generator'); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               id="aicg_groq_api_key"
                               name="aicg_groq_api_key"
                               value="<?php echo esc_attr(get_option('aicg_groq_api_key')); ?>"
                               class="regular-text">
                        <p class="description">
                            <?php _e('Entre com sua chave da API do Groq. Obtenha uma em groq.com', 'ai-content-generator'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Guardar alterações', 'ai-content-generator')); ?>
        </form>
    </div>
    <?php
}

/**
 * Registra as configurações do plugin
 */
function aicg_register_settings() {
    register_setting('aicg_options', 'aicg_openrouter_api_key');
    register_setting('aicg_options', 'aicg_groq_api_key');
}
add_action('admin_init', 'aicg_register_settings');

/**
 * Verifica permissões do usuário
 */
function aicg_check_user_permissions() {
    $allowed_roles = get_option('mce_user_roles', array('administrator', 'editor'));
    $user = wp_get_current_user();
    $user_roles = (array) $user->roles;
    
    return array_intersect($allowed_roles, $user_roles);
}

/**
 * Verifica se deve usar o editor clássico para o post type atual
 */
function aicg_check_post_type($post_type) {
    $allowed_types = get_option('mce_post_types', array('post', 'page'));
    return in_array($post_type, $allowed_types);
}

/**
 * Adiciona meta box de opções do editor
 */
function aicg_add_meta_box() {
    $allowed_types = get_option('mce_post_types', array('post', 'page'));
    
    foreach ($allowed_types as $post_type) {
        add_meta_box(
            'aicg_meta_box',
            __('Classic Editor Options', 'ai-content-generator'),
            'aicg_meta_box_callback',
            $post_type,
            'side',
            'high'
        );
    }
}
add_action('add_meta_boxes', 'aicg_add_meta_box');

/**
 * Callback do meta box
 */
function aicg_meta_box_callback($post) {
    wp_nonce_field('aicg_meta_box', 'aicg_meta_box_nonce');
    
    $value = get_post_meta($post->ID, '_aicg_editor_type', true);
    ?>
    <p>
        <label>
            <input type="radio" name="aicg_editor_type" value="classic" <?php checked($value, 'classic'); ?>>
            <?php _e('Use Classic Editor', 'ai-content-generator'); ?>
        </label>
    </p>
    <p>
        <label>
            <input type="radio" name="aicg_editor_type" value="gutenberg" <?php checked($value, 'gutenberg'); ?>>
            <?php _e('Use Block Editor', 'ai-content-generator'); ?>
        </label>
    </p>
    <?php
}

/**
 * Salva dados do meta box
 */
function aicg_save_meta_box($post_id) {
    if (!isset($_POST['aicg_meta_box_nonce'])) {
        return;
    }
    
    if (!wp_verify_nonce($_POST['aicg_meta_box_nonce'], 'aicg_meta_box')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (isset($_POST['aicg_editor_type'])) {
        update_post_meta(
            $post_id,
            '_aicg_editor_type',
            sanitize_text_field($_POST['aicg_editor_type'])
        );
    }
}
add_action('save_post', 'aicg_save_meta_box');

/**
 * Adiciona links de ação no painel de plugins
 */
function aicg_add_action_links($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=ai-content-generator') . '">' . __('Settings', 'ai-content-generator') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'aicg_add_action_links');

/**
 * Carrega scripts e estilos
 */
function aicg_enqueue_scripts($hook) {
    if (!in_array($hook, array('post.php', 'post-new.php'))) {
        return;
    }

    wp_enqueue_style(
        'aicg-styles',
        AICG_PLUGIN_URL . 'assets/css/ai-content.css',
        array(),
        AICG_VERSION
    );

    wp_enqueue_script(
        'aicg-script',
        AICG_PLUGIN_URL . 'assets/js/ai-content.js',
        array('jquery'),
        AICG_VERSION,
        true
    );

    wp_localize_script('aicg-script', 'aicgSettings', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('aicg_nonce')
    ));
}
add_action('admin_enqueue_scripts', 'aicg_enqueue_scripts');

/**
 * Carrega scripts e estilos no admin
 */
function aicg_admin_enqueue_scripts($hook) {
    // Carrega estilos apenas na página de edição
    if (!in_array($hook, array('post.php', 'post-new.php'))) {
        return;
    }
    
    // Carrega estilos dos shortcodes no editor
    wp_enqueue_style(
        'aicg-shortcodes-admin',
        AICG_PLUGIN_URL . 'assets/css/shortcodes.css',
        array(),
        AICG_VERSION
    );

    // Carrega estilos do contador de estatísticas
    wp_enqueue_style(
        'aicg-editor-stats',
        AICG_PLUGIN_URL . 'assets/css/editor-stats.css',
        array('dashicons'),
        AICG_VERSION
    );

    // Carrega script do contador de estatísticas
    wp_enqueue_script(
        'aicg-editor-stats',
        AICG_PLUGIN_URL . 'assets/js/editor-stats.js',
        array('jquery'),
        AICG_VERSION,
        true
    );
}
add_action('admin_enqueue_scripts', 'aicg_admin_enqueue_scripts');

/**
 * Adiciona evento para inicializar o contador de estatísticas
 */
function aicg_add_editor_stats() {
    add_action('after_wp_tiny_mce', 'aicg_editor_stats_init');
}
add_action('admin_init', 'aicg_add_editor_stats');

/**
 * Inicializa o contador de estatísticas
 */
function aicg_editor_stats_init() {
    ?>
    <script>
        jQuery(document).ready(function($) {
            // Função para extrair o conteúdo do H1 e atualizar o título
            function updateTitleFromH1(content) {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = content;
                const h1 = tempDiv.querySelector('h1');
                if (h1) {
                    $('#title').val(h1.textContent.trim());
                }
            }

            // Função para atualizar o título quando o conteúdo mudar
            function setupTitleSync() {
                // Para o editor visual (TinyMCE)
                if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
                    tinymce.activeEditor.on('NodeChange KeyUp SetContent', function(e) {
                        const content = tinymce.activeEditor.getContent();
                        updateTitleFromH1(content);
                    });
                }

                // Para o editor de texto
                $('#content').on('input change', function() {
                    const content = $(this).val();
                    updateTitleFromH1(content);
                });
            }

            // Configurar a sincronização quando o editor estiver pronto
            if (typeof tinymce !== 'undefined') {
                tinymce.on('AddEditor', function(e) {
                    if (e.editor.id === 'content') {
                        setupTitleSync();
                    }
                });
            }

            // Configurar para o editor de texto também
            setupTitleSync();

            // Função para inserir H1 e atualizar título
            function insertContentAndUpdateTitle(content) {
                // Se não houver H1, adiciona um no início
                if (!content.includes('<h1>')) {
                    const title = $('#title').val();
                    if (title) {
                        content = '<h1>' + title + '</h1>\n' + content;
                    }
                }

                // Insere o conteúdo
                if (typeof tinymce !== 'undefined' && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
                    tinymce.activeEditor.setContent(content);
                } else {
                    $('#content').val(content);
                }

                // Atualiza o título
                updateTitleFromH1(content);
            }

            // Adiciona os botões na barra de ferramentas HTML
            var addCustomButtons = function() {
                var toolbar = $('#ed_toolbar');
                if (toolbar.length && !toolbar.hasClass('custom-buttons-added')) {
                    // Botões existentes...
                    toolbar.append('<button type="button" class="button-html-custom columns-button" title="Inserir colunas"><i class="dashicons dashicons-columns"></i></button>');
                    toolbar.append('<button type="button" class="button-html-custom warning-button" title="Inserir aviso"><i class="dashicons dashicons-warning"></i></button>');
                    toolbar.append('<button type="button" class="button-html-custom custom-button" title="Botão personalizado"><i class="dashicons dashicons-button"></i></button>');
                    toolbar.append('<button type="button" class="button-html-custom info-button" title="Inserir informação"><i class="dashicons dashicons-info"></i></button>');
                    toolbar.append('<button type="button" class="button-html-custom css-button" title="Inserir CSS"><i class="dashicons dashicons-editor-code"></i></button>');
                    
                    toolbar.addClass('custom-buttons-added');
                    
                    // Handlers dos botões com atualização do título
                    $('.columns-button').click(function() {
                        var content = '<div class="row"><div class="col">Coluna 1</div><div class="col">Coluna 2</div></div>';
                        insertContentAndUpdateTitle(content);
                    });
                    
                    $('.warning-button').click(function() {
                        var content = '<div class="warning-box">Texto do aviso aqui</div>';
                        insertContentAndUpdateTitle(content);
                    });
                    
                    $('.custom-button').click(function() {
                        var content = '<button class="custom-btn">Texto do botão</button>';
                        insertContentAndUpdateTitle(content);
                    });

                    $('.info-button').click(function() {
                        var content = '<div class="info-box">Texto da informação aqui</div>';
                        insertContentAndUpdateTitle(content);
                    });

                    $('.css-button').click(function() {
                        var cssTemplate = '<style type="text/css">\n' +
                            '/* Estilos personalizados */\n' +
                            '.row {\n' +
                            '    display: flex;\n' +
                            '    flex-wrap: wrap;\n' +
                            '    margin: -15px;\n' +
                            '}\n' +
                            '.col {\n' +
                            '    flex: 1;\n' +
                            '    padding: 15px;\n' +
                            '}\n' +
                            '.warning-box {\n' +
                            '    background-color: #fff3cd;\n' +
                            '    border: 1px solid #ffeeba;\n' +
                            '    color: #856404;\n' +
                            '    padding: 15px;\n' +
                            '    border-radius: 4px;\n' +
                            '    margin: 10px 0;\n' +
                            '}\n' +
                            '.info-box {\n' +
                            '    background-color: #cce5ff;\n' +
                            '    border: 1px solid #b8daff;\n' +
                            '    color: #004085;\n' +
                            '    padding: 15px;\n' +
                            '    border-radius: 4px;\n' +
                            '    margin: 10px 0;\n' +
                            '}\n' +
                            '.custom-btn {\n' +
                            '    display: inline-block;\n' +
                            '    padding: 10px 20px;\n' +
                            '    background-color: #007bff;\n' +
                            '    color: white;\n' +
                            '    border: none;\n' +
                            '    border-radius: 4px;\n' +
                            '    cursor: pointer;\n' +
                            '    text-decoration: none;\n' +
                            '}\n' +
                            '.custom-btn:hover {\n' +
                            '    background-color: #0056b3;\n' +
                            '}\n' +
                            '</style>';
                        insertContentAndUpdateTitle(cssTemplate);
                    });
                }
            };
            
            // Adiciona os botões quando trocar para a aba de texto
            $(document).on('click', '#content-html', function() {
                setTimeout(addCustomButtons, 100);
            });
            
            // Adiciona os botões na inicialização se começar na aba de texto
            if ($('#content-html').hasClass('active')) {
                setTimeout(addCustomButtons, 100);
            }

            // Adiciona handler para o botão "Generate with AI"
            $(document).on('click', '[data-action="generate-content"]', function() {
                // Quando o conteúdo for gerado, garantir que tem H1
                setTimeout(function() {
                    const content = tinymce.activeEditor 
                        ? tinymce.activeEditor.getContent()
                        : $('#content').val();
                    
                    if (!content.includes('<h1>')) {
                        const title = $('#title').val();
                        if (title) {
                            const newContent = '<h1>' + title + '</h1>\n' + content;
                            if (tinymce.activeEditor) {
                                tinymce.activeEditor.setContent(newContent);
                            } else {
                                $('#content').val(newContent);
                            }
                        }
                    }
                }, 100);
            });
        });
    </script>
    <?php
}

/**
 * Inclui a classe de shortcodes
 */
require_once AICG_PLUGIN_DIR . 'includes/class-shortcodes.php';

/**
 * Inclui a classe de templates
 */
require_once AICG_PLUGIN_DIR . 'includes/class-templates.php';

/**
 * Carrega as fontes do Google no admin
 */
function aicg_add_google_fonts() {
    $google_fonts = array(
        'Roboto',
        'Open+Sans',
        'Lato',
        'Montserrat',
        'Raleway',
        'Poppins',
        'Ubuntu',
        'Playfair+Display',
        'Merriweather',
        'Source+Sans+Pro',
        'PT+Sans',
        'Noto+Sans',
        'Nunito',
        'Fira+Sans',
        'Josefin+Sans'
    );
    
    $fonts_url = 'https://fonts.googleapis.com/css?family=' . implode('|', $google_fonts) . '&display=swap';
    wp_enqueue_style('aicg-google-fonts', $fonts_url);
}
add_action('admin_enqueue_scripts', 'aicg_add_google_fonts');

function aicg_add_editor_styles() {
    add_action('admin_head', function() {
        ?>
        <style>
            .aicg-font-control, .aicg-size-control, .aicg-color-control {
                display: inline-block;
                margin: 0 5px;
                vertical-align: middle;
            }
            .aicg-size-control input {
                width: 60px;
            }
            /* Remove os botões específicos */
            #mceu_15, #mceu_16, #mceu_17, #mceu_18,
            #mceu_18-button,
            .mce-ico.mce-i-icon.dashicons-info,
            [aria-label*="info"] {
                display: none !important;
            }
            /* Estilo para os botões na aba de texto */
            .quicktags-toolbar .button-html-custom {
                display: inline-block;
                padding: 2px 8px;
                margin: 0 2px;
                cursor: pointer;
                border: 1px solid #ccc;
                background: #f7f7f7;
            }
            .quicktags-toolbar .button-html-custom i {
                font: normal 20px/1 dashicons;
                vertical-align: middle;
            }
            /* Estilo para o botão CSS */
            .quicktags-toolbar .css-button i {
                color: #0073aa;
            }
        </style>
        <script>
            jQuery(document).ready(function($) {
                // Função para extrair o conteúdo do H1 e atualizar o título
                function updateTitleFromH1(content) {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = content;
                    const h1 = tempDiv.querySelector('h1');
                    if (h1) {
                        $('#title').val(h1.textContent.trim());
                    }
                }

                // Função para atualizar o título quando o conteúdo mudar
                function setupTitleSync() {
                    // Para o editor visual (TinyMCE)
                    if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
                        tinymce.activeEditor.on('NodeChange KeyUp SetContent', function(e) {
                            const content = tinymce.activeEditor.getContent();
                            updateTitleFromH1(content);
                        });
                    }

                    // Para o editor de texto
                    $('#content').on('input change', function() {
                        const content = $(this).val();
                        updateTitleFromH1(content);
                    });
                }

                // Configurar a sincronização quando o editor estiver pronto
                if (typeof tinymce !== 'undefined') {
                    tinymce.on('AddEditor', function(e) {
                        if (e.editor.id === 'content') {
                            setupTitleSync();
                        }
                    });
                }

                // Configurar para o editor de texto também
                setupTitleSync();

                // Função para inserir H1 e atualizar título
                function insertContentAndUpdateTitle(content) {
                    // Se não houver H1, adiciona um no início
                    if (!content.includes('<h1>')) {
                        const title = $('#title').val();
                        if (title) {
                            content = '<h1>' + title + '</h1>\n' + content;
                        }
                    }

                    // Insere o conteúdo
                    if (typeof tinymce !== 'undefined' && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
                        tinymce.activeEditor.setContent(content);
                    } else {
                        $('#content').val(content);
                    }

                    // Atualiza o título
                    updateTitleFromH1(content);
                }

                // Adiciona os botões na barra de ferramentas HTML
                var addCustomButtons = function() {
                    var toolbar = $('#ed_toolbar');
                    if (toolbar.length && !toolbar.hasClass('custom-buttons-added')) {
                        // Botões existentes...
                        toolbar.append('<button type="button" class="button-html-custom columns-button" title="Inserir colunas"><i class="dashicons dashicons-columns"></i></button>');
                        toolbar.append('<button type="button" class="button-html-custom warning-button" title="Inserir aviso"><i class="dashicons dashicons-warning"></i></button>');
                        toolbar.append('<button type="button" class="button-html-custom custom-button" title="Botão personalizado"><i class="dashicons dashicons-button"></i></button>');
                        toolbar.append('<button type="button" class="button-html-custom info-button" title="Inserir informação"><i class="dashicons dashicons-info"></i></button>');
                        toolbar.append('<button type="button" class="button-html-custom css-button" title="Inserir CSS"><i class="dashicons dashicons-editor-code"></i></button>');
                        
                        toolbar.addClass('custom-buttons-added');
                        
                        // Handlers dos botões com atualização do título
                        $('.columns-button').click(function() {
                            var content = '<div class="row"><div class="col">Coluna 1</div><div class="col">Coluna 2</div></div>';
                            insertContentAndUpdateTitle(content);
                        });
                        
                        $('.warning-button').click(function() {
                            var content = '<div class="warning-box">Texto do aviso aqui</div>';
                            insertContentAndUpdateTitle(content);
                        });
                        
                        $('.custom-button').click(function() {
                            var content = '<button class="custom-btn">Texto do botão</button>';
                            insertContentAndUpdateTitle(content);
                        });

                        $('.info-button').click(function() {
                            var content = '<div class="info-box">Texto da informação aqui</div>';
                            insertContentAndUpdateTitle(content);
                        });

                        $('.css-button').click(function() {
                            var cssTemplate = '<style type="text/css">\n' +
                                '/* Estilos personalizados */\n' +
                                '.row {\n' +
                                '    display: flex;\n' +
                                '    flex-wrap: wrap;\n' +
                                '    margin: -15px;\n' +
                                '}\n' +
                                '.col {\n' +
                                '    flex: 1;\n' +
                                '    padding: 15px;\n' +
                                '}\n' +
                                '.warning-box {\n' +
                                '    background-color: #fff3cd;\n' +
                                '    border: 1px solid #ffeeba;\n' +
                                '    color: #856404;\n' +
                                '    padding: 15px;\n' +
                                '    border-radius: 4px;\n' +
                                '    margin: 10px 0;\n' +
                                '}\n' +
                                '.info-box {\n' +
                                '    background-color: #cce5ff;\n' +
                                '    border: 1px solid #b8daff;\n' +
                                '    color: #004085;\n' +
                                '    padding: 15px;\n' +
                                '    border-radius: 4px;\n' +
                                '    margin: 10px 0;\n' +
                                '}\n' +
                                '.custom-btn {\n' +
                                '    display: inline-block;\n' +
                                '    padding: 10px 20px;\n' +
                                '    background-color: #007bff;\n' +
                                '    color: white;\n' +
                                '    border: none;\n' +
                                '    border-radius: 4px;\n' +
                                '    cursor: pointer;\n' +
                                '    text-decoration: none;\n' +
                                '}\n' +
                                '.custom-btn:hover {\n' +
                                '    background-color: #0056b3;\n' +
                                '}\n' +
                                '</style>';
                            insertContentAndUpdateTitle(cssTemplate);
                        });
                    }
                };
                
                // Adiciona os botões quando trocar para a aba de texto
                $(document).on('click', '#content-html', function() {
                    setTimeout(addCustomButtons, 100);
                });
                
                // Adiciona os botões na inicialização se começar na aba de texto
                if ($('#content-html').hasClass('active')) {
                    setTimeout(addCustomButtons, 100);
                }

                // Adiciona handler para o botão "Generate with AI"
                $(document).on('click', '[data-action="generate-content"]', function() {
                    // Quando o conteúdo for gerado, garantir que tem H1
                    setTimeout(function() {
                        const content = tinymce.activeEditor 
                            ? tinymce.activeEditor.getContent()
                            : $('#content').val();
                        
                        if (!content.includes('<h1>')) {
                            const title = $('#title').val();
                            if (title) {
                                const newContent = '<h1>' + title + '</h1>\n' + content;
                                if (tinymce.activeEditor) {
                                    tinymce.activeEditor.setContent(newContent);
                                } else {
                                    $('#content').val(newContent);
                                }
                            }
                        }
                    }, 100);
                });
            });
        </script>
        <?php
    });

    add_filter('mce_buttons', function($buttons) {
        array_unshift($buttons, 'fontselect', 'fontsizeselect', 'forecolor');
        return $buttons;
    });

    add_filter('tiny_mce_before_init', function($settings) {
        // Remove o botão específico da lista de botões
        if (isset($settings['toolbar1'])) {
            $settings['toolbar1'] = str_replace('button', '', $settings['toolbar1']);
        }
        if (isset($settings['toolbar2'])) {
            $settings['toolbar2'] = str_replace('button', '', $settings['toolbar2']);
        }
        
        // Adicionar tamanhos de fonte personalizados
        $font_sizes = array();
        for($i = 1; $i <= 100; $i++) {
            $font_sizes[] = $i . 'px=' . $i . 'px';
        }
        $settings['fontsize_formats'] = implode(' ', $font_sizes);

        // Adicionar fontes comuns
        $settings['font_formats'] = 
            'Arial=arial,helvetica,sans-serif;' .
            'Arial Black=arial black,avant garde;' .
            'Book Antiqua=book antiqua,palatino;' .
            'Comic Sans MS=comic sans ms,sans-serif;' .
            'Courier New=courier new,courier;' .
            'Georgia=georgia,palatino;' .
            'Helvetica=helvetica;' .
            'Impact=impact,chicago;' .
            'Symbol=symbol;' .
            'Tahoma=tahoma,arial,helvetica,sans-serif;' .
            'Terminal=terminal,monaco;' .
            'Times New Roman=times new roman,times;' .
            'Trebuchet MS=trebuchet ms,geneva;' .
            'Verdana=verdana,geneva;' .
            'Webdings=webdings;' .
            'Wingdings=wingdings,zapf dingbats';

        return $settings;
    });
}
add_action('init', 'aicg_add_editor_styles');

// Adicionar suporte para editor personalizado
function aicg_add_editor_support() {
    add_theme_support('editor-styles');
    add_editor_style('editor-style.css');
}
add_action('after_setup_theme', 'aicg_add_editor_support');

function aicg_create_editor_script() {
    // Definir o caminho do diretório e arquivo
    $js_dir = plugin_dir_path(__FILE__) . 'js';
    $js_file = $js_dir . '/editor-styles.js';

    // Criar diretório se não existir
    if (!file_exists($js_dir)) {
        wp_mkdir_p($js_dir);
    }

    // Só criar o arquivo se ele não existir
    if (!file_exists($js_file)) {
        $js_content = <<<'EOT'
(function() {
    tinymce.create('tinymce.plugins.AICGEditorStyles', {
        init : function(ed, url) {
            // Font Family Dropdown
            ed.addButton('aicg_font_family', {
                type: 'listbox',
                text: 'Fonte',
                tooltip: 'Escolha a fonte',
                className: 'aicg-style-dropdown',
                values: [
                    {text: 'Arial', value: 'Arial'},
                    {text: 'Times New Roman', value: 'Times New Roman'},
                    {text: 'Helvetica', value: 'Helvetica'},
                    {text: 'Georgia', value: 'Georgia'},
                    {text: 'Verdana', value: 'Verdana'},
                    {text: 'Roboto', value: 'Roboto'}
                ],
                onselect: function(e) {
                    var value = e.control.settings.value;
                    ed.execCommand('FontName', false, value);
                }
            });

            // Font Size Input
            ed.addButton('aicg_font_size', {
                type: 'listbox',
                text: 'Tamanho',
                tooltip: 'Tamanho da fonte',
                className: 'aicg-style-dropdown',
                values: Array.from({length: 100}, (_, i) => ({
                    text: (i + 1) + 'px',
                    value: (i + 1) + 'px'
                })),
                onselect: function(e) {
                    var value = e.control.settings.value;
                    ed.execCommand('FontSize', false, value);
                }
            });

            // Text Color Picker
            ed.addButton('aicg_text_color', {
                type: 'colorbutton',
                text: 'Cor',
                tooltip: 'Cor do texto',
                className: 'aicg-color-picker',
                onselect: function(e) {
                    ed.execCommand('ForeColor', false, e.value);
                }
            });
        },
        
        createControl : function(n, cm) {
            return null;
        }
    });

    tinymce.PluginManager.add('aicg_editor_styles', tinymce.plugins.AICGEditorStyles);
})();
EOT;

        // Criar o arquivo
        file_put_contents($js_file, $js_content);
    }
}

// Registrar a criação do arquivo na ativação do plugin
register_activation_hook(__FILE__, 'aicg_create_editor_script');

// Garantir que o arquivo existe quando o plugin é carregado
add_action('plugins_loaded', 'aicg_create_editor_script'); 