<?php
/**
 * Plugin Name: Generador de Catálogos PDF
 * Description: Plugin para generar catálogos PDF desde WooCommerce con filtros avanzados
 * Version: 2.2
 * Author: Elian Leguizamon (TeemsAgency)
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CATALOG_GENERATOR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CATALOG_GENERATOR_PLUGIN_PATH', plugin_dir_path(__FILE__));

class CatalogGeneratorPlugin {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_menu', array($this, 'add_settings_menu'), 20);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_generate_catalog_pdf', array($this, 'generate_catalog_pdf'));
        add_action('wp_ajax_get_subcategories', array($this, 'get_subcategories_ajax'));
        //add_action('wp_ajax_debug_product_stock', array($this, 'debug_product_stock')); // Nueva acción // Comment by @eeelian8
        add_action('wp_ajax_debug_system_check', array($this, 'debug_system_check'));
        register_activation_hook(__FILE__, array($this, 'activate'));
    }

    public function init() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
    }

    public function activate() {
        $upload_dir = wp_upload_dir();
        $catalog_dir = $upload_dir['basedir'] . '/catalog-pdfs';
        if (!file_exists($catalog_dir)) {
            wp_mkdir_p($catalog_dir);
        }
    }

    public function woocommerce_missing_notice() {
        echo '<div class="error"><p>El plugin Generador de Catálogos PDF requiere WooCommerce para funcionar.</p></div>';
    }

    public function add_admin_menu() {
        add_menu_page(
            'Generador de Catálogos',
            'Catálogos PDF',
            'manage_options',
            'catalog-generator',
            array($this, 'admin_page'),
            'dashicons-media-document',
            30
        );
    }

    public function add_settings_menu() {
        add_submenu_page(
            'catalog-generator',
            'Configuración del Catálogo',
            'Configuración',
            'manage_options',
            'catalog-generator-settings',
            array($this, 'settings_page')
        );
    }

    public function settings_page() {
        wp_enqueue_media();
        ?>
        <div class="wrap">
            <h1>Configuración del Generador de Catálogos</h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('catalog_generator_settings');
                do_settings_sections('catalog_generator_settings');
                submit_button();
                ?>
            </form>
            
            <div class="catalog-images-section">
                <h2>Gestión de Imágenes</h2>
                <p>Aquí puedes ver las imágenes que se utilizarán en los catálogos PDF.</p>
                
                <div class="image-upload-section">
                    <h3>Logo de la Empresa</h3>
                    <p>Logo actual: <strong>swaga-logo.webp</strong></p>
                    <div id="logo-preview">
                        <?php if (file_exists(CATALOG_GENERATOR_PLUGIN_PATH . 'assets/img/swaga-logo.webp')): ?>
                            <img src="<?php echo CATALOG_GENERATOR_PLUGIN_URL; ?>assets/img/swaga-logo.webp" alt="Logo" style="max-width: 200px; max-height: 150px;">
                        <?php else: ?>
                            <p style="color: red;">⚠ Logo no encontrado</p>
                        <?php endif; ?>
                    </div>
                    <p><em>Para cambiar el logo, reemplaza el archivo "swaga-logo.webp" en la carpeta assets/img/</em></p>
                </div>
                
                <div class="image-upload-section">
                    <h3>Imagen de Portada</h3>
                    <p>Portada actual: <strong>PORTADA-CATALOGO-INVIERNO-2025-v2.jpg</strong></p>
                    <div id="cover-preview">
                        <?php if (file_exists(CATALOG_GENERATOR_PLUGIN_PATH . 'assets/img/PORTADA-CATALOGO-INVIERNO-2025-v2.jpg')): ?>
                            <img src="<?php echo CATALOG_GENERATOR_PLUGIN_URL; ?>assets/img/PORTADA-CATALOGO-INVIERNO-2025-v2.jpg" alt="Portada" style="max-width: 200px; max-height: 150px;">
                        <?php else: ?>
                            <p style="color: red;">⚠ Portada no encontrada</p>
                        <?php endif; ?>
                    </div>
                    <p><em>Para cambiar la portada, reemplaza el archivo "PORTADA-CATALOGO-INVIERNO-2025-v2.jpg" en la carpeta assets/img/</em></p>
                </div>
                
                <div class="image-upload-section">
                    <h3>Fuente Roboto</h3>
                    <p>Fuente actual: <strong>Roboto-Black.ttf</strong></p>
                    <?php 
                    $roboto_path = CATALOG_GENERATOR_PLUGIN_PATH . 'assets/fonts/Roboto-Black.ttf';
                    if (file_exists($roboto_path)) {
                        echo '<div class="notice notice-success inline"><p>✓ Fuente Roboto disponible</p></div>';
                    } else {
                        echo '<div class="notice notice-warning inline"><p>⚠ Fuente Roboto no encontrada. Se usará DejaVu Sans como alternativa.</p></div>';
                    }
                    ?>
                    <p><em>Para cambiar la fuente, reemplaza el archivo "Roboto-Black.ttf" en la carpeta assets/fonts/</em></p>
                </div>
                
                <div class="image-upload-section">
                    <h3>Librería TCPDF</h3>
                    <?php 
                    $tcpdf_path = CATALOG_GENERATOR_PLUGIN_PATH . 'lib/tcpdf/tcpdf.php';
                    if (file_exists($tcpdf_path)) {
                        echo '<div class="notice notice-success inline"><p>✓ TCPDF instalado correctamente</p></div>';
                    } else {
                        echo '<div class="notice notice-error inline"><p>⚠ TCPDF no encontrado. Descarga desde <a href="https://tcpdf.org/" target="_blank">https://tcpdf.org/</a> y extrae en /lib/tcpdf/</p></div>';
                    }
                    ?>
                </div>
            </div>
            
            <div class="catalog-help-section">
                <h2>Ayuda</h2>
                <div class="help-content">
                    <h3>Estructura de archivos:</h3>
                    <pre>
catalog-generator/
├── catalog-generator.php
├── includes/
│   └── pdf-generator.php
├── assets/
│   ├── img/
│   │   ├── swaga-logo.webp
│   │   └── PORTADA-CATALOGO-INVIERNO-2025-v2.jpg
│   ├── fonts/
│   │   └── Roboto-Black.ttf
│   ├── catalog-generator.js
│   └── catalog-generator.css
└── lib/
    └── tcpdf/ (descargar desde tcpdf.org)
                    </pre>
                </div>
            </div>
        </div>
        
        <style>
            .catalog-images-section {
                margin-top: 30px;
                padding: 20px;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 5px;
            }
            
            .image-upload-section {
                margin: 20px 0;
                padding: 15px;
                background: #f9f9f9;
                border-radius: 3px;
            }
            
            .image-upload-section h3 {
                margin-top: 0;
                color: #333;
            }
            
            .catalog-help-section {
                margin-top: 30px;
                padding: 20px;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 5px;
            }
            
            .help-content pre {
                background: #f4f4f4;
                padding: 15px;
                border-radius: 3px;
                overflow-x: auto;
            }
            
            .notice.inline {
                display: inline-block;
                margin: 10px 0;
            }
        </style>
        <?php
    }

    public function enqueue_admin_scripts($hook) {
        if ($hook != 'toplevel_page_catalog-generator') {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('catalog-generator-js', CATALOG_GENERATOR_PLUGIN_URL . 'assets/catalog-generator.js', array('jquery'), '1.0', true);
        wp_enqueue_style('catalog-generator-css', CATALOG_GENERATOR_PLUGIN_URL . 'assets/catalog-generator.css', array(), '1.0');
        
        wp_localize_script('catalog-generator-js', 'catalog_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('catalog_nonce')
        ));
    }

    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Generador de Catálogos PDF</h1>
            
            <!-- Botón de debug de stock -->
            <!--<div class="stock-debug-section" style="background: #f0f8ff; border: 1px solid #0073aa; padding: 15px; margin: 20px 0; border-radius: 5px;">-->
            <!--    <h3>🔧 Herramientas de Debug</h3>-->
            <!--    <p>Usa estas herramientas para diagnosticar problemas con el filtro de stock:</p>-->
            <!--    <button type="button" id="debug-stock" class="button">Analizar Stock de Productos</button>-->
            <!--    <div id="stock-debug-results" style="margin-top: 15px;"></div>-->
            <!--</div>-->
            
            <div class="catalog-generator-container">
                <form id="catalog-form" method="post">
                    <?php wp_nonce_field('catalog_nonce', 'catalog_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Categorías</th>
                            <td>
                                <select name="categories[]" id="categories" multiple style="width: 100%; height: 150px;">
                                    <?php $this->render_categories_options(); ?>
                                </select>
                                <p class="description">Mantén presionado Ctrl para seleccionar múltiples categorías</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Subcategorías</th>
                            <td>
                                <select name="subcategories[]" id="subcategories" multiple style="width: 100%; height: 150px;">
                                    <option value="">Selecciona primero una categoría</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Etiquetas</th>
                            <td>
                                <select name="tags[]" id="tags" multiple style="width: 100%; height: 150px;">
                                    <?php $this->render_tags_options(); ?>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">SKU</th>
                            <td>
                                <input type="text" name="sku" id="sku" class="regular-text" placeholder="Ingresa SKU específico">
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Rango de Precios</th>
                            <td>
                                <input type="number" name="min_price" id="min_price" placeholder="Precio mínimo" step="0.01" min="0">
                                <span> - </span>
                                <input type="number" name="max_price" id="max_price" placeholder="Precio máximo" step="0.01" min="0">
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Stock Mínimo</th>
                            <td>
                                <input type="number" name="min_stock" id="min_stock" placeholder="Cantidad mínima de stock" min="0">
                                <p class="description">Solo productos con stock igual o superior a este valor</p>
                                <p class="description" style="color: #d63638;"><strong>Nota:</strong> Para productos sin gestión de stock que están "En stock", se asigna un valor de 999 unidades.</p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="generate_catalog" class="button-primary" value="Generar Catálogo PDF">
                        <span id="loading" style="display: none;">Generando PDF...</span>
                    </p>
                </form>
                
                <div id="catalog-result"></div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Funcionalidad del botón de debug // Comment by @eeelian8
            $('#debug-stock').on('click', function() {
                var button = $(this);
                var resultsDiv = $('#stock-debug-results');
                
                button.prop('disabled', true).text('Analizando...');
                resultsDiv.html('<p>Obteniendo información de stock...</p>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'debug_product_stock',
                        nonce: '<?php echo wp_create_nonce('catalog_nonce'); ?>',
                        limit: 20
                    },
                    success: function(response) {
                        button.prop('disabled', false).text('Analizar Stock de Productos');
                        
                        if (response.success) {
                            var html = '<div style="background: white; border: 1px solid #ddd; padding: 15px; border-radius: 3px;">';
                            html += '<h4>📊 Análisis de Stock (últimos 20 productos):</h4>';
                            html += '<table style="width: 100%; border-collapse: collapse;">';
                            html += '<tr style="background: #f9f9f9;"><th style="border: 1px solid #ddd; padding: 8px;">Producto</th><th style="border: 1px solid #ddd; padding: 8px;">Tipo</th><th style="border: 1px solid #ddd; padding: 8px;">Gestiona Stock</th><th style="border: 1px solid #ddd; padding: 8px;">Stock Calculado</th><th style="border: 1px solid #ddd; padding: 8px;">Método</th></tr>';
                            
                            $.each(response.data, function(index, item) {
                                var stockColor = item.final_stock > 0 ? '#28a745' : '#dc3545';
                                html += '<tr>';
                                html += '<td style="border: 1px solid #ddd; padding: 8px;">' + item.product_name + ' (ID: ' + item.product_id + ')</td>';
                                html += '<td style="border: 1px solid #ddd; padding: 8px;">' + item.product_type + '</td>';
                                html += '<td style="border: 1px solid #ddd; padding: 8px;">' + (item.manage_stock ? 'Sí' : 'No') + '</td>';
                                html += '<td style="border: 1px solid #ddd; padding: 8px; color: ' + stockColor + '; font-weight: bold;">' + item.final_stock + '</td>';
                                html += '<td style="border: 1px solid #ddd; padding: 8px; font-size: 12px;">' + item.method_used + '</td>';
                                html += '</tr>';
                            });
                            
                            html += '</table></div>';
                            resultsDiv.html(html);
                        } else {
                            resultsDiv.html('<div style="color: #d63638;">Error: ' + response.data + '</div>');
                        }
                    },
                    error: function() {
                        button.prop('disabled', false).text('Analizar Stock de Productos');
                        resultsDiv.html('<div style="color: #d63638;">Error al realizar el análisis de stock.</div>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    private function render_categories_options() {
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'parent' => 0
        ));
        
        foreach ($categories as $category) {
            echo '<option value="' . $category->term_id . '">' . $category->name . '</option>';
        }
    }

    private function render_tags_options() {
        $tags = get_terms(array(
            'taxonomy' => 'product_tag',
            'hide_empty' => false
        ));
        
        foreach ($tags as $tag) {
            echo '<option value="' . $tag->term_id . '">' . $tag->name . '</option>';
        }
    }

    public function get_subcategories_ajax() {
        check_ajax_referer('catalog_nonce', 'nonce');
        
        $parent_ids = isset($_POST['parent_ids']) ? $_POST['parent_ids'] : array();
        $subcategories = array();
        
        if (!empty($parent_ids)) {
            $subcategories = get_terms(array(
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
                'parent__in' => $parent_ids
            ));
        }
        
        $options = '';
        foreach ($subcategories as $subcategory) {
            $options .= '<option value="' . $subcategory->term_id . '">' . $subcategory->name . '</option>';
        }
        
        wp_send_json_success($options);
    }

    /**
     * Método mejorado para obtener stock con debug detallado
     */
    private function get_detailed_stock_info($product) {
        $debug_info = array(
            'product_id' => $product->get_id(),
            'product_name' => $product->get_name(),
            'product_type' => $product->get_type(),
            'manage_stock' => $product->get_manage_stock(),
            'stock_status' => $product->get_stock_status(),
            'stock_quantity' => $product->get_stock_quantity(),
            'final_stock' => 0,
            'method_used' => ''
        );
        
        if ($product->is_type('variable')) {
            // Para productos variables, sumar stock de todas las variaciones // Comment by @eeelian8
            $variations = $product->get_children();
            $total_stock = 0;
            $variation_details = array();
            
            foreach ($variations as $variation_id) {
                $variation = wc_get_product($variation_id);
                if ($variation) {
                    $var_stock = 0;
                    if ($variation->get_manage_stock()) {
                        $var_stock = intval($variation->get_stock_quantity());
                    } elseif ($variation->get_stock_status() === 'instock') {
                        $var_stock = 999; // Valor por defecto para "en stock" sin gestión
                    }
                    
                    $total_stock += $var_stock;
                    $variation_details[] = array(
                        'variation_id' => $variation_id,
                        'manages_stock' => $variation->get_manage_stock(),
                        'stock_quantity' => $variation->get_stock_quantity(),
                        'stock_status' => $variation->get_stock_status(),
                        'calculated_stock' => $var_stock
                    );
                }
            }
            
            $debug_info['final_stock'] = $total_stock;
            $debug_info['method_used'] = 'variable_product_sum';
            $debug_info['variations'] = $variation_details;
            
        } elseif ($product->get_manage_stock()) {
            // Producto simple con gestión de stock // Comment by @eeelian8
            $stock_qty = $product->get_stock_quantity();
            $debug_info['final_stock'] = is_numeric($stock_qty) ? intval($stock_qty) : 0;
            $debug_info['method_used'] = 'managed_stock';
            
        } else {
            // Producto simple sin gestión de stock // Comment by @eeelian8
            $stock_status = $product->get_stock_status();
            if ($stock_status === 'instock') {
                $debug_info['final_stock'] = 999; // Valor alto para "en stock"
                $debug_info['method_used'] = 'stock_status_instock';
            } elseif ($stock_status === 'outofstock') {
                $debug_info['final_stock'] = 0;
                $debug_info['method_used'] = 'stock_status_outofstock';
            } else {
                $debug_info['final_stock'] = 0;
                $debug_info['method_used'] = 'stock_status_other';
            }
        }
        
        return $debug_info;
    }
    
    /**
     * Método mejorado para obtener solo el stock (sin debug)
     */
    private function get_product_stock_quantity($product) {
        if ($product->is_type('variable')) {
            $variations = $product->get_children();
            $total_stock = 0;
            
            foreach ($variations as $variation_id) {
                $variation = wc_get_product($variation_id);
                if ($variation) {
                    if ($variation->get_manage_stock()) {
                        $var_stock = intval($variation->get_stock_quantity());
                        $total_stock += $var_stock;
                    } elseif ($variation->get_stock_status() === 'instock') {
                        $total_stock += 999; // Valor alto para variaciones en stock
                    }
                }
            }
            return $total_stock;
            
        } elseif ($product->get_manage_stock()) {
            $stock_qty = $product->get_stock_quantity();
            return is_numeric($stock_qty) ? intval($stock_qty) : 0;
            
        } else {
            // Producto sin gestión de stock
            $stock_status = $product->get_stock_status();
            if ($stock_status === 'instock') {
                return 999; // Valor alto para productos en stock
            } else {
                return 0;
            }
        }
    }
    
    /**
     * Método AJAX para debug de stock
     */
    public function debug_product_stock() {
        check_ajax_referer('catalog_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos para realizar esta acción');
        }
        
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
        
        // Obtener productos para debug // Comment by @eeelian8
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        $products_query = new WP_Query($args);
        $debug_results = array();
        
        if ($products_query->have_posts()) {
            while ($products_query->have_posts()) {
                $products_query->the_post();
                $product = wc_get_product(get_the_ID());
                
                if ($product) {
                    $debug_results[] = $this->get_detailed_stock_info($product);
                }
            }
            wp_reset_postdata();
        }
        
        wp_send_json_success($debug_results);
    }
    
    /**
     * Método actualizado para obtener stock simple (mantener compatibilidad)
     */
    private function get_simple_stock($product) {
        // Usar el nuevo método mejorado para mantener consistencia // Comment by @eeelian8
        return $this->get_product_stock_quantity($product);
    }
    
    /**
     * Método adicional para verificar si un producto cumple con el stock mínimo
     */
    private function product_meets_stock_requirement($product, $min_stock) {
        if ($min_stock <= 0) {
            return true; // No hay requisito de stock mínimo // Comment by @eeelian8
        }
        
        $product_stock = $this->get_product_stock_quantity($product);
        return $product_stock >= $min_stock;
    }
    
    /**
     * Método para obtener información completa de stock para mostrar en el PDF
     */
    private function get_stock_display_info($product) {
        $stock_quantity = $this->get_product_stock_quantity($product);
        
        if ($product->is_type('variable')) {
            if ($stock_quantity > 0) {
                return array(
                    'quantity' => $stock_quantity,
                    'display' => $stock_quantity . ' UNIDADES DISPONIBLES (TOTAL VARIACIONES)',
                    'status' => 'in_stock'
                );
            } else {
                return array(
                    'quantity' => 0,
                    'display' => 'CONSULTAR DISPONIBILIDAD',
                    'status' => 'check_availability'
                );
            }
        } else {
            // Producto simple // Comment by @eeelian8
            if ($product->get_manage_stock()) {
                $actual_stock = $product->get_stock_quantity();
                if (is_numeric($actual_stock) && $actual_stock > 0) {
                    return array(
                        'quantity' => intval($actual_stock),
                        'display' => intval($actual_stock) . ' UNIDADES DISPONIBLES',
                        'status' => 'in_stock'
                    );
                } else {
                    return array(
                        'quantity' => 0,
                        'display' => 'SIN STOCK',
                        'status' => 'out_of_stock'
                    );
                }
            } else {
                // Sin gestión de stock // Comment by @eeelian8
                $stock_status = $product->get_stock_status();
                if ($stock_status === 'instock') {
                    return array(
                        'quantity' => 999,
                        'display' => 'DISPONIBLE',
                        'status' => 'in_stock'
                    );
                } elseif ($stock_status === 'outofstock') {
                    return array(
                        'quantity' => 0,
                        'display' => 'SIN STOCK',
                        'status' => 'out_of_stock'
                    );
                } else {
                    return array(
                        'quantity' => 0,
                        'display' => 'CONSULTAR DISPONIBILIDAD',
                        'status' => 'check_availability'
                    );
                }
            }
        }
    }

    /**
     * Método mejorado para generar el catálogo con mejor filtrado de stock
     */
    public function generate_catalog_pdf() {
    // Habilitar debug temporal // Comment by @eeelian8
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    error_reporting(E_ALL);
    
    check_ajax_referer('catalog_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('No tienes permisos para realizar esta acción');
    }
    
    try {
        // Log inicio del proceso // Comment by @eeelian8
        error_log('=== INICIO GENERACIÓN CATÁLOGO ===');
        
        $categories = isset($_POST['categories']) ? $_POST['categories'] : array();
        $subcategories = isset($_POST['subcategories']) ? $_POST['subcategories'] : array();
        $tags = isset($_POST['tags']) ? $_POST['tags'] : array();
        $sku = isset($_POST['sku']) ? sanitize_text_field($_POST['sku']) : '';
        $min_price = isset($_POST['min_price']) ? floatval($_POST['min_price']) : 0;
        $max_price = isset($_POST['max_price']) ? floatval($_POST['max_price']) : 0;
        $min_stock = isset($_POST['min_stock']) ? intval($_POST['min_stock']) : 0;
        
        error_log('Filtros recibidos: ' . json_encode(array(
            'categories' => $categories,
            'subcategories' => $subcategories,
            'tags' => $tags,
            'sku' => $sku,
            'min_price' => $min_price,
            'max_price' => $max_price,
            'min_stock' => $min_stock
        )));
        
        // Verificar que WooCommerce está activo // Comment by @eeelian8
        if (!class_exists('WooCommerce')) {
            error_log('ERROR: WooCommerce no está activo');
            wp_send_json_error('WooCommerce no está instalado o activo');
        }
        
        // Construir consulta WP_Query // Comment by @eeelian8
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => 20, // Limitar para debug
            'post_status' => 'publish',
        );
        
        error_log('Consulta inicial: ' . json_encode($args));
        
        // Filtros de taxonomía // Comment by @eeelian8
        if (!empty($categories) || !empty($subcategories)) {
            $category_terms = array_merge($categories, $subcategories);
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'id',
                    'terms' => $category_terms,
                    'operator' => 'IN',
                ),
            );
            error_log('Filtro de categorías aplicado: ' . json_encode($category_terms));
        }
        
        if (!empty($tags)) {
            if (!isset($args['tax_query'])) {
                $args['tax_query'] = array();
            }
            $args['tax_query'][] = array(
                'taxonomy' => 'product_tag',
                'field' => 'id',
                'terms' => $tags,
                'operator' => 'IN',
            );
            error_log('Filtro de tags aplicado: ' . json_encode($tags));
        }
        
        if (isset($args['tax_query']) && count($args['tax_query']) > 1) {
            $args['tax_query']['relation'] = 'AND';
        }
        
        // Filtros de meta (SKU y precios) // Comment by @eeelian8
        $meta_query = array();
        
        if (!empty($sku)) {
            $meta_query[] = array(
                'key' => '_sku',
                'value' => $sku,
                'compare' => 'LIKE',
            );
            error_log('Filtro SKU aplicado: ' . $sku);
        }
        
        if ($min_price > 0) {
            $meta_query[] = array(
                'key' => '_price',
                'value' => $min_price,
                'compare' => '>=',
                'type' => 'NUMERIC',
            );
            error_log('Filtro precio mínimo aplicado: ' . $min_price);
        }
        
        if ($max_price > 0) {
            $meta_query[] = array(
                'key' => '_price',
                'value' => $max_price,
                'compare' => '<=',
                'type' => 'NUMERIC',
            );
            error_log('Filtro precio máximo aplicado: ' . $max_price);
        }
        
        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }
        
        error_log('Consulta final antes de ejecutar: ' . json_encode($args));
        
        // Ejecutar consulta // Comment by @eeelian8
        $products_query = new WP_Query($args);
        $all_products = $products_query->posts;
        
        error_log('Productos encontrados en consulta: ' . count($all_products));
        
        if (empty($all_products)) {
            error_log('ERROR: No se encontraron productos con los filtros aplicados');
            wp_send_json_error('No se encontraron productos con los filtros seleccionados. Intenta con filtros menos restrictivos.');
        }
        
        // Debug detallado para stock mínimo // Comment by @eeelian8
        $stock_debug = array();
        $filtered_products = array();
        
        foreach ($all_products as $index => $product_post) {
            try {
                error_log('Procesando producto ' . ($index + 1) . ' ID: ' . $product_post->ID);
                
                $product = wc_get_product($product_post->ID);
                if (!$product) {
                    error_log('ERROR: No se pudo crear objeto producto para ID: ' . $product_post->ID);
                    continue;
                }
                
                $product_stock = $this->get_product_stock_quantity($product);
                error_log('Stock calculado para producto ID ' . $product_post->ID . ': ' . $product_stock);
                
                // Guardar información de debug // Comment by @eeelian8
                $stock_debug[] = array(
                    'product_id' => $product_post->ID,
                    'product_name' => $product->get_name(),
                    'calculated_stock' => $product_stock,
                    'meets_minimum' => ($product_stock >= $min_stock),
                    'min_stock_filter' => $min_stock
                );
                
                // Aplicar filtro de stock mínimo // Comment by @eeelian8
                if ($min_stock > 0) {
                    if ($product_stock >= $min_stock) {
                        $filtered_products[] = $product_post;
                        error_log('Producto ID ' . $product_post->ID . ' CUMPLE filtro de stock');
                    } else {
                        error_log('Producto ID ' . $product_post->ID . ' NO CUMPLE filtro de stock (' . $product_stock . ' < ' . $min_stock . ')');
                    }
                } else {
                    $filtered_products[] = $product_post;
                }
            } catch (Exception $e) {
                error_log('ERROR procesando producto ID ' . $product_post->ID . ': ' . $e->getMessage());
                continue;
            }
        }
        
        error_log('Productos después del filtro de stock: ' . count($filtered_products));
        
        if (empty($filtered_products)) {
            error_log('ERROR: Ningún producto cumple los filtros después de aplicar stock mínimo');
            
            $debug_summary = array();
            $debug_summary[] = 'PRODUCTOS ANALIZADOS: ' . count($stock_debug);
            foreach ($stock_debug as $debug_item) {
                $status = $debug_item['meets_minimum'] ? 'CUMPLE' : 'NO CUMPLE';
                $debug_summary[] = '- ' . $debug_item['product_name'] . ' | Stock: ' . $debug_item['calculated_stock'] . ' | ' . $status;
            }
            
            wp_send_json_error(implode("\n", $debug_summary));
        }
        
        // Intentar generar PDF // Comment by @eeelian8
        error_log('Iniciando generación de PDF con ' . count($filtered_products) . ' productos');
        
        $pdf_url = $this->create_pdf_debug($filtered_products);
        
        if ($pdf_url) {
            error_log('PDF generado exitosamente: ' . $pdf_url);
            wp_send_json_success(array(
                'pdf_url' => $pdf_url,
                'products_count' => count($filtered_products),
                'stock_debug_count' => count($stock_debug),
                'stock_filter_applied' => $min_stock > 0
            ));
        } else {
            error_log('ERROR: Falló la generación del PDF');
            wp_send_json_error('Error al generar el PDF. Revisa los logs de WordPress para más detalles.');
        }
        
    } catch (Exception $e) {
        error_log('EXCEPCIÓN CRÍTICA en generate_catalog_pdf: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        wp_send_json_error('Error crítico: ' . $e->getMessage());
    } catch (Error $e) {
        error_log('ERROR FATAL en generate_catalog_pdf: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        wp_send_json_error('Error fatal: ' . $e->getMessage());
    }
}

    private function create_pdf($products) {
        $tcpdf_path = CATALOG_GENERATOR_PLUGIN_PATH . 'lib/tcpdf/tcpdf.php';
        
        if (!file_exists($tcpdf_path)) {
            return $this->create_simple_pdf($products);
        }
        
        $pdf_generator_path = CATALOG_GENERATOR_PLUGIN_PATH . 'includes/pdf-generator.php';
        if (!file_exists($pdf_generator_path)) {
            return $this->create_simple_pdf($products);
        }
        
        try {
            require_once($tcpdf_path);
            require_once($pdf_generator_path);
            
            $pdf_generator = new CatalogPDFGenerator();
            return $pdf_generator->generate($products);
        } catch (Exception $e) {
            return $this->create_simple_pdf($products);
        }
    }
    
    private function create_simple_pdf($products) {
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/catalog-pdfs/';
        
        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);
        }
        
        $filename = 'catalog-' . date('Y-m-d-H-i-s') . '.html';
        $filepath = $pdf_dir . $filename;
        
        $html = $this->generate_html_catalog($products);
        
        file_put_contents($filepath, $html);
        
        return $upload_dir['baseurl'] . '/catalog-pdfs/' . $filename;
    }
    
    private function generate_html_catalog($products) {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Catálogo de Productos</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .product { page-break-after: always; margin-bottom: 50px; }
        .category { font-size: 18px; font-weight: bold; text-align: center; margin-bottom: 20px; }
        .product-name { font-size: 16px; font-weight: bold; margin: 10px 0; }
        .product-info { margin: 5px 0; }
        .product-price { font-size: 14px; font-weight: bold; color: #333; }
        .product-images { display: flex; gap: 10px; margin: 20px 0; }
        .product-images img { max-width: 150px; max-height: 150px; object-fit: cover; }
    </style>
</head>
<body>';
        
        $products_by_category = array();
        foreach ($products as $product_post) {
            $product = wc_get_product($product_post->ID);
            if (!$product) continue;
            
            $categories = wp_get_post_terms($product_post->ID, 'product_cat');
            $category_name = !empty($categories) ? $categories[0]->name : 'Sin Categoría';
            
            if (!isset($products_by_category[$category_name])) {
                $products_by_category[$category_name] = array();
            }
            $products_by_category[$category_name][] = $product;
        }
        
        foreach ($products_by_category as $category => $category_products) {
            foreach ($category_products as $product) {
                $html .= '<div class="product">';
                $html .= '<div class="category">' . strtoupper($category) . '</div>';
                
                $image_ids = array();
                if ($product->get_image_id()) {
                    $image_ids[] = $product->get_image_id();
                }
                $gallery_ids = $product->get_gallery_image_ids();
                if (!empty($gallery_ids)) {
                    $image_ids = array_merge($image_ids, array_slice($gallery_ids, 0, 2));
                }
                $image_ids = array_slice($image_ids, 0, 3);
                
                if (!empty($image_ids)) {
                    $html .= '<div class="product-images">';
                    foreach ($image_ids as $image_id) {
                        $image_url = wp_get_attachment_image_src($image_id, 'medium');
                        if ($image_url) {
                            $html .= '<img src="' . $image_url[0] . '" alt="Producto">';
                        }
                    }
                    $html .= '</div>';
                }
                
                $html .= '<div class="product-name">' . $product->get_name() . '</div>';
                $html .= '<div class="product-info">SKU: ' . $product->get_sku() . '</div>';
                
                // Usar el nuevo método de stock // Comment by @eeelian8
                $stock_display = $this->get_stock_display_info($product);
                $html .= '<div class="product-info">Stock: ' . $stock_display['display'] . '</div>';
                
                $colors = $this->get_product_colors_simple($product);
                if (!empty($colors)) {
                    $html .= '<div class="product-info">Colores: ' . implode(', ', $colors) . '</div>';
                }
                
                $html .= '<div class="product-price">Precio: '. number_format($product->get_price(), 2) . '</div>';
                $html .= '</div>';
            }
        }
        
        $html .= '</body></html>';
        
        return $html;
    }
    
    /**
 * Método de creación de PDF con debug extensivo
 */
private function create_pdf_debug($products) {
    try {
        error_log('=== INICIO CREACIÓN PDF ===');
        
        $tcpdf_path = CATALOG_GENERATOR_PLUGIN_PATH . 'lib/tcpdf/tcpdf.php';
        error_log('Ruta TCPDF: ' . $tcpdf_path);
        error_log('TCPDF existe: ' . (file_exists($tcpdf_path) ? 'SÍ' : 'NO'));
        
        if (!file_exists($tcpdf_path)) {
            error_log('TCPDF no encontrado, generando HTML simple');
            return $this->create_simple_pdf_debug($products);
        }
        
        $pdf_generator_path = CATALOG_GENERATOR_PLUGIN_PATH . 'includes/pdf-generator.php';
        error_log('Ruta PDF Generator: ' . $pdf_generator_path);
        error_log('PDF Generator existe: ' . (file_exists($pdf_generator_path) ? 'SÍ' : 'NO'));
        
        if (!file_exists($pdf_generator_path)) {
            error_log('pdf-generator.php no encontrado, generando HTML simple');
            return $this->create_simple_pdf_debug($products);
        }
        
        // Verificar que se puede incluir TCPDF // Comment by @eeelian8
        require_once($tcpdf_path);
        error_log('TCPDF incluido correctamente');
        
        // Verificar que se puede incluir el generador // Comment by @eeelian8
        require_once($pdf_generator_path);
        error_log('PDF Generator incluido correctamente');
        
        // Verificar que la clase existe // Comment by @eeelian8
        if (!class_exists('CatalogPDFGenerator')) {
            error_log('ERROR: Clase CatalogPDFGenerator no encontrada');
            return $this->create_simple_pdf_debug($products);
        }
        
        error_log('Creando instancia de CatalogPDFGenerator');
        $pdf_generator = new CatalogPDFGenerator();
        
        error_log('Llamando al método generate()');
        $result = $pdf_generator->generate($products);
        
        if ($result) {
            error_log('PDF generado exitosamente: ' . $result);
        } else {
            error_log('ERROR: El método generate() retornó false');
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log('EXCEPCIÓN en create_pdf_debug: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        return $this->create_simple_pdf_debug($products);
    } catch (Error $e) {
        error_log('ERROR FATAL en create_pdf_debug: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        return $this->create_simple_pdf_debug($products);
    }
}

/**
 * Método de creación de HTML simple con debug
 */
private function create_simple_pdf_debug($products) {
    try {
        error_log('=== INICIO CREACIÓN HTML SIMPLE ===');
        
        $upload_dir = wp_upload_dir();
        error_log('Upload dir: ' . json_encode($upload_dir));
        
        $pdf_dir = $upload_dir['basedir'] . '/catalog-pdfs/';
        error_log('PDF dir: ' . $pdf_dir);
        
        if (!file_exists($pdf_dir)) {
            error_log('Creando directorio: ' . $pdf_dir);
            $created = wp_mkdir_p($pdf_dir);
            error_log('Directorio creado: ' . ($created ? 'SÍ' : 'NO'));
            
            if (!$created) {
                error_log('ERROR: No se pudo crear el directorio');
                return false;
            }
        }
        
        // Verificar permisos de escritura // Comment by @eeelian8
        if (!is_writable($pdf_dir)) {
            error_log('ERROR: El directorio no tiene permisos de escritura');
            return false;
        }
        
        $filename = 'catalog-debug-' . date('Y-m-d-H-i-s') . '.html';
        $filepath = $pdf_dir . $filename;
        error_log('Archivo a crear: ' . $filepath);
        
        $html = $this->generate_html_catalog_debug($products);
        error_log('HTML generado, longitud: ' . strlen($html) . ' caracteres');
        
        $written = file_put_contents($filepath, $html);
        error_log('Bytes escritos: ' . ($written !== false ? $written : 'ERROR'));
        
        if ($written === false) {
            error_log('ERROR: No se pudo escribir el archivo');
            return false;
        }
        
        $url = $upload_dir['baseurl'] . '/catalog-pdfs/' . $filename;
        error_log('URL generada: ' . $url);
        
        return $url;
        
    } catch (Exception $e) {
        error_log('EXCEPCIÓN en create_simple_pdf_debug: ' . $e->getMessage());
        return false;
    }
}

/**
 * Método de generación de HTML con información de debug
 */
private function generate_html_catalog_debug($products) {
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Catálogo de Productos - DEBUG</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .debug-info { background: #e3f2fd; border: 1px solid #2196f3; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .product { background: white; margin-bottom: 30px; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .category { font-size: 18px; font-weight: bold; text-align: center; margin-bottom: 20px; color: #333; }
        .product-name { font-size: 16px; font-weight: bold; margin: 10px 0; color: #2196f3; }
        .product-info { margin: 5px 0; }
        .product-price { font-size: 14px; font-weight: bold; color: #4caf50; }
        .product-images { display: flex; gap: 10px; margin: 20px 0; flex-wrap: wrap; }
        .product-images img { max-width: 150px; max-height: 150px; object-fit: cover; border: 1px solid #ddd; border-radius: 3px; }
        .stock-info { padding: 5px 10px; border-radius: 3px; color: white; font-weight: bold; }
        .stock-ok { background: #4caf50; }
        .stock-warning { background: #ff9800; }
        .stock-error { background: #f44336; }
    </style>
</head>
<body>';
    
    // Información de debug // Comment by @eeelian8
    $html .= '<div class="debug-info">';
    $html .= '<h2>🔧 Información de Debug</h2>';
    $html .= '<p><strong>Fecha de generación:</strong> ' . date('d/m/Y H:i:s') . '</p>';
    $html .= '<p><strong>Total de productos procesados:</strong> ' . count($products) . '</p>';
    $html .= '<p><strong>Versión del plugin:</strong> 2.2 (Debug)</p>';
    $html .= '<p><strong>WordPress version:</strong> ' . get_bloginfo('version') . '</p>';
    $html .= '<p><strong>WooCommerce activo:</strong> ' . (class_exists('WooCommerce') ? 'SÍ' : 'NO') . '</p>';
    $html .= '</div>';
    
    if (empty($products)) {
        $html .= '<div style="background: #ffebee; border: 1px solid #f44336; padding: 20px; border-radius: 5px;">';
        $html .= '<h2>❌ No hay productos para mostrar</h2>';
        $html .= '<p>No se encontraron productos que cumplan con los filtros especificados.</p>';
        $html .= '</div>';
    } else {
        $products_by_category = array();
        foreach ($products as $product_post) {
            $product = wc_get_product($product_post->ID);
            if (!$product) continue;
            
            $categories = wp_get_post_terms($product_post->ID, 'product_cat');
            $category_name = !empty($categories) ? $categories[0]->name : 'Sin Categoría';
            
            if (!isset($products_by_category[$category_name])) {
                $products_by_category[$category_name] = array();
            }
            $products_by_category[$category_name][] = $product;
        }
        
        foreach ($products_by_category as $category => $category_products) {
            foreach ($category_products as $product) {
                $html .= '<div class="product">';
                $html .= '<div class="category">' . strtoupper(esc_html($category)) . '</div>';
                
                // Imágenes del producto // Comment by @eeelian8
                $image_ids = array();
                if ($product->get_image_id()) {
                    $image_ids[] = $product->get_image_id();
                }
                $gallery_ids = $product->get_gallery_image_ids();
                if (!empty($gallery_ids)) {
                    $image_ids = array_merge($image_ids, array_slice($gallery_ids, 0, 2));
                }
                $image_ids = array_slice($image_ids, 0, 3);
                
                if (!empty($image_ids)) {
                    $html .= '<div class="product-images">';
                    foreach ($image_ids as $image_id) {
                        $image_url = wp_get_attachment_image_src($image_id, 'medium');
                        if ($image_url) {
                            $html .= '<img src="' . esc_url($image_url[0]) . '" alt="Producto">';
                        }
                    }
                    $html .= '</div>';
                }
                
                $html .= '<div class="product-name">' . esc_html($product->get_name()) . '</div>';
                $html .= '<div class="product-info"><strong>ID:</strong> ' . $product->get_id() . '</div>';
                $html .= '<div class="product-info"><strong>SKU:</strong> ' . esc_html($product->get_sku()) . '</div>';
                $html .= '<div class="product-info"><strong>Tipo:</strong> ' . esc_html($product->get_type()) . '</div>';
                
                // Información de stock con debug // Comment by @eeelian8
                $stock_display = $this->get_stock_display_info($product);
                $stock_class = 'stock-ok';
                if ($stock_display['quantity'] == 0) {
                    $stock_class = 'stock-error';
                } elseif ($stock_display['quantity'] < 10 && $stock_display['quantity'] > 0) {
                    $stock_class = 'stock-warning';
                }
                
                $html .= '<div class="product-info">';
                $html .= '<strong>Stock:</strong> ';
                $html .= '<span class="stock-info ' . $stock_class . '">' . esc_html($stock_display['display']) . '</span>';
                $html .= '</div>';
                
                // Información adicional de debug para stock // Comment by @eeelian8
                $html .= '<div class="product-info" style="font-size: 12px; color: #666;">';
                $html .= '<strong>Debug Stock:</strong> ';
                $html .= 'Gestiona stock: ' . ($product->get_manage_stock() ? 'Sí' : 'No') . ' | ';
                $html .= 'Stock Status: ' . esc_html($product->get_stock_status()) . ' | ';
                $html .= 'Stock Quantity: ' . ($product->get_stock_quantity() ?: 'N/A');
                $html .= '</div>';
                
                $colors = $this->get_product_colors_simple($product);
                if (!empty($colors)) {
                    $html .= '<div class="product-info"><strong>Colores:</strong> ' . esc_html(implode(', ', $colors)) . '</div>';
                }
                
                $price = $product->get_price();
                if ($price) {
                    $html .= '<div class="product-price">Precio: $' . number_format($price, 2) . '</div>';
                }
                
                $html .= '</div>';
            }
        }
    }
    
    $html .= '</body></html>';
    
    return $html;
}

/**
 * Método para verificar la configuración del sistema
 */
public function debug_system_check() {
    check_ajax_referer('catalog_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('No tienes permisos para realizar esta acción');
    }
    
    $debug_info = array();
    
    // Información básica del sistema // Comment by @eeelian8
    $debug_info['system'] = array(
        'php_version' => PHP_VERSION,
        'wordpress_version' => get_bloginfo('version'),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size')
    );
    
    // Verificar WooCommerce // Comment by @eeelian8
    $debug_info['woocommerce'] = array(
        'active' => class_exists('WooCommerce'),
        'version' => class_exists('WooCommerce') ? WC()->version : 'N/A'
    );
    
    // Verificar archivos del plugin // Comment by @eeelian8
    $debug_info['plugin_files'] = array(
        'tcpdf_exists' => file_exists(CATALOG_GENERATOR_PLUGIN_PATH . 'lib/tcpdf/tcpdf.php'),
        'tcpdf_path' => CATALOG_GENERATOR_PLUGIN_PATH . 'lib/tcpdf/tcpdf.php',
        'pdf_generator_exists' => file_exists(CATALOG_GENERATOR_PLUGIN_PATH . 'includes/pdf-generator.php'),
        'pdf_generator_path' => CATALOG_GENERATOR_PLUGIN_PATH . 'includes/pdf-generator.php',
        'logo_exists' => file_exists(CATALOG_GENERATOR_PLUGIN_PATH . 'assets/img/swaga-logo.webp'),
        'cover_exists' => file_exists(CATALOG_GENERATOR_PLUGIN_PATH . 'assets/img/PORTADA-CATALOGO-INVIERNO-2025-v2.jpg'),
        'font_exists' => file_exists(CATALOG_GENERATOR_PLUGIN_PATH . 'assets/fonts/Roboto-Black.ttf')
    );
    
    // Verificar directorios y permisos // Comment by @eeelian8
    $upload_dir = wp_upload_dir();
    $catalog_dir = $upload_dir['basedir'] . '/catalog-pdfs/';
    
    $debug_info['directories'] = array(
        'upload_dir' => $upload_dir['basedir'],
        'upload_dir_writable' => is_writable($upload_dir['basedir']),
        'catalog_dir' => $catalog_dir,
        'catalog_dir_exists' => file_exists($catalog_dir),
        'catalog_dir_writable' => file_exists($catalog_dir) ? is_writable($catalog_dir) : false
    );
    
    // Crear directorio si no existe // Comment by @eeelian8
    if (!file_exists($catalog_dir)) {
        $created = wp_mkdir_p($catalog_dir);
        $debug_info['directories']['catalog_dir_created'] = $created;
        $debug_info['directories']['catalog_dir_exists'] = $created;
        $debug_info['directories']['catalog_dir_writable'] = $created ? is_writable($catalog_dir) : false;
    }
    
    // Verificar productos de WooCommerce // Comment by @eeelian8
    if (class_exists('WooCommerce')) {
        $products_query = new WP_Query(array(
            'post_type' => 'product',
            'posts_per_page' => 5,
            'post_status' => 'publish'
        ));
        
        $debug_info['products'] = array(
            'total_products' => $products_query->found_posts,
            'sample_products' => array()
        );
        
        if ($products_query->have_posts()) {
            while ($products_query->have_posts()) {
                $products_query->the_post();
                $product = wc_get_product(get_the_ID());
                
                if ($product) {
                    $debug_info['products']['sample_products'][] = array(
                        'id' => $product->get_id(),
                        'name' => $product->get_name(),
                        'type' => $product->get_type(),
                        'sku' => $product->get_sku(),
                        'price' => $product->get_price(),
                        'stock_status' => $product->get_stock_status(),
                        'manage_stock' => $product->get_manage_stock(),
                        'stock_quantity' => $product->get_stock_quantity()
                    );
                }
            }
            wp_reset_postdata();
        }
    }
    
    // Verificar errores recientes en logs // Comment by @eeelian8
    $debug_info['recent_errors'] = array();
    
    // Intentar leer los últimos errores del log // Comment by @eeelian8
    $error_log_path = ini_get('error_log');
    if ($error_log_path && file_exists($error_log_path)) {
        $debug_info['error_log_path'] = $error_log_path;
        $debug_info['error_log_readable'] = is_readable($error_log_path);
        
        if (is_readable($error_log_path)) {
            $log_content = file_get_contents($error_log_path);
            $lines = explode("\n", $log_content);
            $recent_lines = array_slice($lines, -20); // Últimas 20 líneas
            
            foreach ($recent_lines as $line) {
                if (stripos($line, 'catalog') !== false || stripos($line, 'pdf') !== false) {
                    $debug_info['recent_errors'][] = $line;
                }
            }
        }
    }
    
    wp_send_json_success($debug_info);
}

/**
 * Agregar este botón al admin_page() después del botón de debug de stock
 */
    
    private function get_product_colors_simple($product) {
        $colors = array();
        
        if ($product->is_type('variable')) {
            $variations = $product->get_available_variations();
            foreach ($variations as $variation) {
                if (isset($variation['attributes']['attribute_pa_color'])) {
                    $colors[] = $variation['attributes']['attribute_pa_color'];
                }
            }
        } else {
            $attributes = $product->get_attributes();
            foreach ($attributes as $attribute) {
                if ($attribute->get_name() == 'pa_color' || $attribute->get_name() == 'color') {
                    $terms = wc_get_product_terms($product->get_id(), $attribute->get_name());
                    foreach ($terms as $term) {
                        $colors[] = $term->name;
                    }
                }
            }
        }
        
        return array_unique($colors);
    }
}

add_action('admin_init', 'catalog_generator_settings_init');

function catalog_generator_settings_init() {
    register_setting('catalog_generator_settings', 'catalog_company_address');
    register_setting('catalog_generator_settings', 'catalog_company_instagram');
    register_setting('catalog_generator_settings', 'catalog_company_phone');
    register_setting('catalog_generator_settings', 'catalog_company_email');
    register_setting('catalog_generator_settings', 'catalog_company_website');
    
    add_settings_section(
        'catalog_generator_company_section',
        'Información de la Empresa',
        'catalog_generator_company_section_callback',
        'catalog_generator_settings'
    );
    
    add_settings_field(
        'catalog_company_address',
        'Dirección',
        'catalog_company_address_callback',
        'catalog_generator_settings',
        'catalog_generator_company_section'
    );
    
    add_settings_field(
        'catalog_company_instagram',
        'Instagram',
        'catalog_company_instagram_callback',
        'catalog_generator_settings',
        'catalog_generator_company_section'
    );
    
    add_settings_field(
        'catalog_company_phone',
        'Teléfono',
        'catalog_company_phone_callback',
        'catalog_generator_settings',
        'catalog_generator_company_section'
    );
    
    add_settings_field(
        'catalog_company_email',
        'Email',
        'catalog_company_email_callback',
        'catalog_generator_settings',
        'catalog_generator_company_section'
    );
    
    add_settings_field(
        'catalog_company_website',
        'Sitio Web',
        'catalog_company_website_callback',
        'catalog_generator_settings',
        'catalog_generator_company_section'
    );
}

function catalog_generator_company_section_callback() {
    echo '<p>Configura la información de tu empresa que aparecerá en el footer de los catálogos PDF.</p>';
}

function catalog_company_address_callback() {
    $value = get_option('catalog_company_address', '');
    echo '<input type="text" name="catalog_company_address" value="' . esc_attr($value) . '" class="regular-text" />';
}

function catalog_company_instagram_callback() {
    $value = get_option('catalog_company_instagram', '');
    echo '<input type="text" name="catalog_company_instagram" value="' . esc_attr($value) . '" class="regular-text" placeholder="@tu_instagram" />';
}

function catalog_company_phone_callback() {
    $value = get_option('catalog_company_phone', '');
    echo '<input type="text" name="catalog_company_phone" value="' . esc_attr($value) . '" class="regular-text" />';
}

function catalog_company_email_callback() {
    $value = get_option('catalog_company_email', '');
    echo '<input type="email" name="catalog_company_email" value="' . esc_attr($value) . '" class="regular-text" />';
}

function catalog_company_website_callback() {
    $value = get_option('catalog_company_website', '');
    echo '<input type="url" name="catalog_company_website" value="' . esc_attr($value) . '" class="regular-text" />';
}

new CatalogGeneratorPlugin();
?>