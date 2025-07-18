<?php
/**
 * Plugin Name: Generador de Catálogos PDF
 * Description: Plugin para generar catálogos PDF desde WooCommerce con filtros avanzados
 * Version: 2.1
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
                                <p class="description">Solo productos con stock igual o superior a este valor</p> <!-- Comment by @eeelian8 -->
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

    public function generate_catalog_pdf() {
        check_ajax_referer('catalog_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos para realizar esta acción');
        }
        
        // Obtener parámetros del formulario // Comment by @eeelian8
        $categories = isset($_POST['categories']) ? $_POST['categories'] : array();
        $subcategories = isset($_POST['subcategories']) ? $_POST['subcategories'] : array();
        $tags = isset($_POST['tags']) ? $_POST['tags'] : array();
        $sku = isset($_POST['sku']) ? sanitize_text_field($_POST['sku']) : '';
        $min_price = isset($_POST['min_price']) ? floatval($_POST['min_price']) : 0;
        $max_price = isset($_POST['max_price']) ? floatval($_POST['max_price']) : 0;
        $min_stock = isset($_POST['min_stock']) ? intval($_POST['min_stock']) : 0;
        
        // Configurar argumentos base para la consulta WP_Query // Comment by @eeelian8
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => array(
                'relation' => 'AND', // Comment by @eeelian8
                array(
                    'key' => '_stock_status',
                    'value' => 'instock',
                    'compare' => '='
                )
            )
        );
        
        $has_filters = false;
        
        // Filtros por taxonomías (categorías, subcategorías, tags) // Comment by @eeelian8
        if (!empty($categories) || !empty($subcategories) || !empty($tags)) {
            $tax_query = array();
            $has_filters = true;
            
            if (!empty($categories)) {
                $tax_query[] = array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $categories,
                    'operator' => 'IN'
                );
            }
            
            if (!empty($subcategories)) {
                $tax_query[] = array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $subcategories,
                    'operator' => 'IN'
                );
            }
            
            if (!empty($tags)) {
                $tax_query[] = array(
                    'taxonomy' => 'product_tag',
                    'field' => 'term_id',
                    'terms' => $tags,
                    'operator' => 'IN'
                );
            }
            
            // Configurar relación OR para categorías y subcategorías, AND para tags // Comment by @eeelian8
            if (count($tax_query) > 1) {
                $tax_query['relation'] = 'OR';
            }
            
            $args['tax_query'] = $tax_query;
        }
        
        // Filtro por SKU // Comment by @eeelian8
        if (!empty($sku)) {
            $has_filters = true;
            $args['meta_query'][] = array(
                'key' => '_sku',
                'value' => $sku,
                'compare' => 'LIKE'
            );
        }
        
        // Filtros por precio // Comment by @eeelian8
        if ($min_price > 0 || $max_price > 0) {
            $has_filters = true;
            if ($min_price > 0) {
                $args['meta_query'][] = array(
                    'key' => '_price',
                    'value' => $min_price,
                    'compare' => '>=',
                    'type' => 'NUMERIC'
                );
            }
            
            if ($max_price > 0) {
                $args['meta_query'][] = array(
                    'key' => '_price',
                    'value' => $max_price,
                    'compare' => '<=',
                    'type' => 'NUMERIC'
                );
            }
        }
        
        // Filtro por stock mínimo CORREGIDO // Comment by @eeelian8
        if ($min_stock > 0) {
            $has_filters = true;
            $args['meta_query'][] = array(
                'key' => '_stock',
                'value' => $min_stock,
                'compare' => '>=',
                'type' => 'NUMERIC'
            );
        }
        
        // Si no hay filtros, limitar a 50 productos // Comment by @eeelian8
        if (!$has_filters) {
            $args['posts_per_page'] = 50;
        }
        
        // Ejecutar consulta // Comment by @eeelian8
        $query = new WP_Query($args);
        $products = $query->posts;
        
        if (empty($products)) {
            wp_send_json_error('No se encontraron productos con los criterios seleccionados');
        }
        
        // Generar PDF // Comment by @eeelian8
        $pdf_url = $this->create_pdf($products);
        
        if ($pdf_url) {
            wp_send_json_success(array(
                'pdf_url' => $pdf_url,
                'products_count' => count($products)
            ));
        } else {
            wp_send_json_error('Error al generar el PDF');
        }
    }

    private function create_pdf($products) {
        $tcpdf_path = CATALOG_GENERATOR_PLUGIN_PATH . 'lib/tcpdf/tcpdf.php';
        
        // Verificar si TCPDF está instalado // Comment by @eeelian8
        if (!file_exists($tcpdf_path)) {
            error_log('TCPDF no encontrado en: ' . $tcpdf_path); // Comment by @eeelian8
            return $this->create_simple_pdf($products);
        }
        
        // Verificar si existe el archivo pdf-generator.php // Comment by @eeelian8
        $pdf_generator_path = CATALOG_GENERATOR_PLUGIN_PATH . 'includes/pdf-generator.php';
        if (!file_exists($pdf_generator_path)) {
            error_log('PDF Generator no encontrado en: ' . $pdf_generator_path); // Comment by @eeelian8
            return $this->create_simple_pdf($products);
        }
        
        try {
            require_once($tcpdf_path);
            require_once($pdf_generator_path);
            
            $pdf_generator = new CatalogPDFGenerator();
            return $pdf_generator->generate($products);
        } catch (Exception $e) {
            error_log('Error generando PDF: ' . $e->getMessage()); // Comment by @eeelian8
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
            if (!$product) continue; // Comment by @eeelian8
            
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
                
                // Obtener imágenes del producto // Comment by @eeelian8
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
                $html .= '<div class="product-info">Stock: ' . $product->get_stock_quantity() . '</div>'; // Comment by @eeelian8
                
                $colors = $this->get_product_colors_simple($product);
                if (!empty($colors)) {
                    $html .= '<div class="product-info">Colores: ' . implode(', ', $colors) . '</div>';
                }
                
                $html .= '<div class="product-price">Precio: $' . number_format($product->get_price(), 2) . '</div>';
                $html .= '</div>';
            }
        }
        
        $html .= '</body></html>';
        
        return $html;
    }
    
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

// Inicializar configuraciones // Comment by @eeelian8
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