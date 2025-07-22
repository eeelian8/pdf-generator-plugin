<?php
/**
 * Clase para generar PDFs de catálogos con diseño exacto como referencia
 * Versión corregida con errores de sintaxis solucionados
 */

class CatalogPDFGenerator extends TCPDF { // ✅ CORREGIDO: era 'tcpdf' (minúsculas)
    
    private $logo_path;
    private $cover_path;
    private $company_info;
    private $font_available;
    
    public function __construct() {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false); // Comment by @eeelian8
        
        // Configurar información de la empresa // Comment by @eeelian8
        $this->company_info = array(
            'address' => get_option('catalog_company_address', 'HELGUERA 433'),
            'instagram' => get_option('catalog_company_instagram', '@swagaoficial'),
            'phone' => get_option('catalog_company_phone', '11 4033-1384'),
            'email' => get_option('catalog_company_email', ''),
            'website' => get_option('catalog_company_website', 'https://swaga.com.ar/mayorista/')
        );
        
        // Rutas de imágenes desde assets/img // Comment by @eeelian8
        $this->logo_path = CATALOG_GENERATOR_PLUGIN_PATH . 'assets/img/swaga-logo.webp';
        $this->cover_path = CATALOG_GENERATOR_PLUGIN_PATH . 'assets/img/PORTADA-CATALOGO-INVIERNO-2025-v2.jpg';
        
        // Configuración del PDF // Comment by @eeelian8
        $this->SetCreator('WordPress Catalog Generator');
        $this->SetAuthor('SWAGA');
        $this->SetTitle('Catálogo de Productos SWAGA');
        $this->SetSubject('Catálogo generado automáticamente');
        $this->SetKeywords('catálogo, productos, PDF, SWAGA');
        
        // Configurar fuente Roboto // Comment by @eeelian8
        $this->font_available = $this->setup_roboto_font();
        
        // Configurar márgenes para el diseño exacto // Comment by @eeelian8
        $this->SetMargins(15, 10, 15);
        $this->SetHeaderMargin(0);
        $this->SetFooterMargin(0);
        
        // Desactivar salto automático para control total // Comment by @eeelian8
        $this->SetAutoPageBreak(FALSE);
        
        // Configurar alta calidad de imagen // Comment by @eeelian8
        $this->setImageScale(PDF_IMAGE_SCALE_RATIO);
    }
    
    private function setup_roboto_font() {
        $roboto_path = CATALOG_GENERATOR_PLUGIN_PATH . 'assets/fonts/Roboto-Black.ttf';
        
        if (!file_exists($roboto_path)) {
            $this->SetFont('helvetica', '', 10);
            return false;
        }
        
        try {
            $fontname = TCPDF_FONTS::addTTFfont($roboto_path, 'TrueTypeUnicode', '', 96);
            if ($fontname !== false) {
                $this->SetFont($fontname, '', 10);
                return $fontname;
            }
        } catch (Exception $e) {
            error_log('Error cargando fuente Roboto: ' . $e->getMessage()); // Comment by @eeelian8
        }
        
        $this->SetFont('helvetica', '', 10);
        return false;
    }
    
    private function setRobotoFont($style = '', $size = 10) {
        if ($this->font_available) {
            $this->SetFont($this->font_available, $style, $size);
        } else {
            $this->SetFont('helvetica', $style, $size);
        }
    }
    
    public function Header() {
        // Sin header para usar toda la página // Comment by @eeelian8
    }
    
    public function Footer() {
        // Sin footer automático // Comment by @eeelian8
    }
    
    public function generate($products) {
        try {
            // Log inicio del proceso // Comment by @eeelian8
            error_log('=== INICIO GENERACIÓN PDF ===');
            error_log('Productos recibidos: ' . count($products));
            
            // Crear portada mejorada // Comment by @eeelian8
            $this->add_cover_page();
            error_log('Portada creada correctamente');
            
            // Agrupar productos por categoría // Comment by @eeelian8
            $products_by_category = $this->group_products_by_category($products);
            error_log('Productos agrupados en ' . count($products_by_category) . ' categorías');
            
            // Generar páginas de productos // Comment by @eeelian8
            $page_count = 0;
            foreach ($products_by_category as $category => $category_products) {
                error_log('Procesando categoría: ' . $category . ' con ' . count($category_products) . ' productos');
                
                foreach ($category_products as $product_data) {
                    try {
                        $this->add_product_page($product_data['product'], $category);
                        $page_count++;
                        error_log('Página ' . $page_count . ' creada para producto ID: ' . $product_data['product']->get_id());
                    } catch (Exception $e) {
                        error_log('Error creando página para producto ID ' . $product_data['product']->get_id() . ': ' . $e->getMessage());
                        continue; // Continuar con el siguiente producto
                    }
                }
            }
            
            error_log('Total de páginas de productos creadas: ' . $page_count);
            
            // Guardar PDF // Comment by @eeelian8
            $upload_dir = wp_upload_dir();
            $pdf_dir = $upload_dir['basedir'] . '/catalog-pdfs/';
            
            if (!file_exists($pdf_dir)) {
                $created = wp_mkdir_p($pdf_dir);
                error_log('Directorio PDF creado: ' . ($created ? 'SÍ' : 'NO'));
                
                if (!$created) {
                    throw new Exception('No se pudo crear el directorio de PDFs');
                }
            }
            
            $filename = 'catalog-' . date('Y-m-d-H-i-s') . '.pdf';
            $filepath = $pdf_dir . $filename;
            
            error_log('Guardando PDF en: ' . $filepath);
            
            // Intentar guardar el PDF // Comment by @eeelian8
            $this->Output($filepath, 'F');
            
            // Verificar que el archivo se creó correctamente // Comment by @eeelian8
            if (file_exists($filepath)) {
                $file_size = filesize($filepath);
                error_log('PDF generado exitosamente. Tamaño: ' . $file_size . ' bytes');
                
                if ($file_size > 0) {
                    return $upload_dir['baseurl'] . '/catalog-pdfs/' . $filename;
                } else {
                    error_log('ERROR: PDF generado pero está vacío');
                    return false;
                }
            } else {
                error_log('ERROR: PDF no se guardó correctamente');
                return false;
            }
            
        } catch (Exception $e) {
            error_log('EXCEPCIÓN en generate(): ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }
    
    private function add_cover_page() {
        $this->AddPage();
        
        if (file_exists($this->cover_path)) {
            try {
                // Obtener información de la imagen // Comment by @eeelian8
                $image_info = getimagesize($this->cover_path);
                $img_width = $image_info[0];
                $img_height = $image_info[1];
                $img_ratio = $img_width / $img_height;
                
                // Dimensiones de página A4 en mm // Comment by @eeelian8
                $page_width = 210;
                $page_height = 297;
                $page_ratio = $page_width / $page_height;
                
                // Calcular dimensiones para cubrir toda la página manteniendo proporción // Comment by @eeelian8
                if ($img_ratio > $page_ratio) {
                    // Imagen más ancha que la página // Comment by @eeelian8
                    $new_height = $page_height;
                    $new_width = $new_height * $img_ratio;
                    $x = -($new_width - $page_width) / 2; // Centrar horizontalmente // Comment by @eeelian8
                    $y = 0;
                } else {
                    // Imagen más alta que la página // Comment by @eeelian8
                    $new_width = $page_width;
                    $new_height = $new_width / $img_ratio;
                    $x = 0;
                    $y = -($new_height - $page_height) / 2; // Centrar verticalmente // Comment by @eeelian8
                }
                
                // Colocar imagen cubriendo toda la página // Comment by @eeelian8
                $this->Image(
                    $this->cover_path,
                    $x, $y,
                    $new_width, $new_height,
                    '',
                    '',
                    '',
                    false,
                    300,
                    '',
                    false,
                    false,
                    0,
                    false,
                    false,
                    false
                );
                
            } catch (Exception $e) {
                error_log('Error cargando imagen de portada: ' . $e->getMessage()); // Comment by @eeelian8
                $this->create_default_cover();
            }
        } else {
            error_log('Imagen de portada no encontrada: ' . $this->cover_path);
            $this->create_default_cover();
        }
    }
    
    private function create_default_cover() {
        // Fondo de color para portada por defecto // Comment by @eeelian8
        $this->SetFillColor(41, 128, 185); // Azul corporativo // Comment by @eeelian8
        $this->Rect(0, 0, 210, 297, 'F');
        
        $this->SetTextColor(255, 255, 255);
        $this->setRobotoFont('B', 36);
        $this->SetY(120);
        $this->Cell(0, 20, 'CATÁLOGO', 0, 1, 'C');
        
        $this->setRobotoFont('', 24);
        $this->Cell(0, 15, 'INVIERNO \'25', 0, 1, 'C');
        
        if (file_exists($this->logo_path)) {
            try {
                $this->Image($this->logo_path, 80, 200, 50, 0, '', '', '', false, 300, '', false, false, 0);
            } catch (Exception $e) {
                error_log('Error cargando logo en portada: ' . $e->getMessage()); // Comment by @eeelian8
            }
        }
    }
    
    private function group_products_by_category($products) {
        $grouped = array();
        
        foreach ($products as $product_post) {
            $product = wc_get_product($product_post->ID);
            if (!$product) continue;
            
            $categories = wp_get_post_terms($product_post->ID, 'product_cat');
            $category_name = 'Sin Categoría';
            
            if (!empty($categories)) {
                $category_name = $categories[0]->name;
            }
            
            if (!isset($grouped[$category_name])) {
                $grouped[$category_name] = array();
            }
            
            $grouped[$category_name][] = array(
                'product' => $product,
                'post' => $product_post
            );
        }
        
        return $grouped;
    }
    
    private function add_product_page($product, $category) {
        $this->AddPage();
        
        // Fondo blanco limpio // Comment by @eeelian8
        $this->SetFillColor(255, 255, 255);
        $this->Rect(0, 0, 210, 297, 'F');
        
        $this->SetTextColor(0, 0, 0);
        
        // Título de categoría centrado // Comment by @eeelian8
        $this->setRobotoFont('B', 14);
        $this->SetY(20);
        $this->Cell(0, 8, strtoupper($category), 0, 1, 'C');
        
        // Layout de imágenes mejorado // Comment by @eeelian8
        $images = $this->get_product_images($product);
        $this->layout_product_images_improved($images);
        
        // Información del producto // Comment by @eeelian8
        $this->add_product_info_improved($product);
        
        // Footer corporativo // Comment by @eeelian8
        $this->add_company_footer_improved();
    }
    
    private function get_product_images($product) {
        $images = array();
        
        // Imagen principal // Comment by @eeelian8
        if ($product->get_image_id()) {
            $main_image = wp_get_attachment_image_src($product->get_image_id(), 'full');
            if ($main_image) {
                $images['main'] = $main_image[0];
            }
        }
        
        // Galería (máximo 2 imágenes adicionales) // Comment by @eeelian8
        $gallery_ids = $product->get_gallery_image_ids();
        if (!empty($gallery_ids)) {
            $gallery_images = array();
            foreach (array_slice($gallery_ids, 0, 2) as $gallery_id) {
                $gallery_image = wp_get_attachment_image_src($gallery_id, 'full');
                if ($gallery_image) {
                    $gallery_images[] = $gallery_image[0];
                }
            }
            $images['gallery'] = $gallery_images;
        }
        
        return $images;
    }
    
    private function layout_product_images_improved($images) {
        $margin_left = 20; // Margen izquierdo // Comment by @eeelian8
        $start_y = 40; // Posición Y inicial // Comment by @eeelian8
        
        // Imagen principal (grande a la izquierda) // Comment by @eeelian8
        if (!empty($images['main'])) {
            $main_temp = $this->download_temp_image($images['main']);
            if ($main_temp) {
                try {
                    // Imagen principal más grande como en la referencia // Comment by @eeelian8
                    $this->Image(
                        $main_temp,
                        $margin_left,        // x = 20mm
                        $start_y,           // y = 40mm
                        85,                 // ancho = 85mm (más grande)
                        115,                // alto = 115mm (más grande)
                        '',
                        '',
                        '',
                        false,
                        300,
                        '',
                        false,
                        false,
                        1,                  // borde sutil
                        false,
                        false,
                        true
                    );
                } catch (Exception $e) {
                    error_log('Error añadiendo imagen principal: ' . $e->getMessage()); // Comment by @eeelian8
                }
                @unlink($main_temp);
            }
        }
        
        // Imágenes de galería (a la derecha, apiladas) // Comment by @eeelian8
        if (!empty($images['gallery'])) {
            $gallery_x = 115; // Posición X para galería (después de la imagen principal) // Comment by @eeelian8
            $gallery_width = 70; // Ancho de cada imagen de galería // Comment by @eeelian8
            $gallery_height = 55; // Alto de cada imagen de galería // Comment by @eeelian8
            $gallery_spacing = 60; // Espaciado vertical entre imágenes // Comment by @eeelian8
            
            foreach ($images['gallery'] as $index => $gallery_url) {
                $gallery_temp = $this->download_temp_image($gallery_url);
                if ($gallery_temp) {
                    try {
                        $gallery_y = $start_y + ($index * $gallery_spacing);
                        $this->Image(
                            $gallery_temp,
                            $gallery_x,
                            $gallery_y,
                            $gallery_width,
                            $gallery_height,
                            '',
                            '',
                            '',
                            false,
                            300,
                            '',
                            false,
                            false,
                            1,              // borde sutil
                            false,
                            false,
                            true
                        );
                    } catch (Exception $e) {
                        error_log('Error añadiendo imagen de galería: ' . $e->getMessage()); // Comment by @eeelian8
                    }
                    @unlink($gallery_temp);
                }
            }
        }
    }
    
    /**
     * Método mejorado para obtener información de stock en el PDF
     */
    private function get_stock_info($product) {
        // Obtener información de stock del producto // Comment by @eeelian8
        $stock_quantity = $this->get_product_stock_quantity_for_pdf($product);
        $stock_status = $product->get_stock_status();
        $manage_stock = $product->get_manage_stock();
        
        // Determinar el display del stock según el tipo de producto // Comment by @eeelian8
        if ($product->is_type('variable')) {
            // Para productos variables, sumar stock de todas las variaciones // Comment by @eeelian8
            $total_stock = 0;
            $variations = $product->get_children();
            $has_stock_variations = false;
            
            foreach ($variations as $variation_id) {
                $variation = wc_get_product($variation_id);
                if ($variation) {
                    if ($variation->get_manage_stock() && $variation->get_stock_quantity()) {
                        $total_stock += intval($variation->get_stock_quantity());
                        $has_stock_variations = true;
                    } elseif ($variation->get_stock_status() === 'instock') {
                        $total_stock += 100; // Valor representativo para variaciones en stock
                        $has_stock_variations = true;
                    }
                }
            }
            
            if ($total_stock > 0 || $has_stock_variations) {
                return array(
                    'quantity' => $total_stock,
                    'display' => $total_stock > 0 ? $total_stock . ' UNIDADES DISPONIBLES' : 'DISPONIBLE',
                    'color_indicator' => 'green'
                );
            } else {
                return array(
                    'quantity' => 0,
                    'display' => 'CONSULTAR DISPONIBILIDAD',
                    'color_indicator' => 'orange'
                );
            }
            
        } else {
            // Para productos simples // Comment by @eeelian8
            if ($manage_stock && is_numeric($stock_quantity)) {
                if ($stock_quantity > 0) {
                    return array(
                        'quantity' => $stock_quantity,
                        'display' => $stock_quantity . ' UNIDADES DISPONIBLES',
                        'color_indicator' => $stock_quantity > 10 ? 'green' : 'orange'
                    );
                } else {
                    return array(
                        'quantity' => 0,
                        'display' => 'SIN STOCK',
                        'color_indicator' => 'red'
                    );
                }
            } else {
                // Si no gestiona stock, verificar status // Comment by @eeelian8
                if ($stock_status === 'instock') {
                    return array(
                        'quantity' => 999, // Número alto para indicador verde
                        'display' => 'DISPONIBLE',
                        'color_indicator' => 'green'
                    );
                } elseif ($stock_status === 'outofstock') {
                    return array(
                        'quantity' => 0,
                        'display' => 'SIN STOCK',
                        'color_indicator' => 'red'
                    );
                } else {
                    return array(
                        'quantity' => 5, // Número medio para indicador naranja
                        'display' => 'CONSULTAR DISPONIBILIDAD',
                        'color_indicator' => 'orange'
                    );
                }
            }
        }
    }
    
    /**
     * Método auxiliar para obtener stock en el contexto del PDF
     */
    private function get_product_stock_quantity_for_pdf($product) {
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
                        $total_stock += 100; // Valor representativo
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
     * Método actualizado para mostrar información del producto con mejor stock
     */
    private function add_product_info_improved($product) {
        $info_y = 170; // Posición Y para la información // Comment by @eeelian8
        $margin_left = 20; // Alinear con las imágenes // Comment by @eeelian8
        
        $this->SetY($info_y);
        $this->SetX($margin_left);
        
        // Nombre del producto en mayúsculas y negrita // Comment by @eeelian8
        $this->setRobotoFont('B', 12);
        $this->SetTextColor(0, 0, 0);
        $product_name = strtoupper($product->get_name());
        $this->Cell(0, 8, $product_name, 0, 1, 'L');
        $this->Ln(2);
        
        // SKU en formato correcto // Comment by @eeelian8
        $this->SetX($margin_left);
        $this->setRobotoFont('', 9);
        $this->SetTextColor(100, 100, 100);
        $sku = $product->get_sku();
        if (!empty($sku)) {
            $this->Cell(0, 6, strtoupper($sku), 0, 1, 'L');
            $this->Ln(2);
        }
        
        // Precio destacado // Comment by @eeelian8
        $this->SetX($margin_left);
        $this->setRobotoFont('B', 14);
        $this->SetTextColor(0, 0, 0);
        $price = $product->get_price();
        if ($price) {
            $this->Cell(0, 8, '$' . number_format($price, 2), 0, 1, 'L');
            $this->Ln(3);
        }
        
        // Stock disponible con indicador visual mejorado // Comment by @eeelian8
        $this->SetX($margin_left);
        $stock_info = $this->get_stock_info($product);
        if ($stock_info) {
            $this->setRobotoFont('B', 9);
            
            // Configurar color según el indicador // Comment by @eeelian8
            switch ($stock_info['color_indicator']) {
                case 'green':
                    $this->SetTextColor(0, 150, 0); // Verde para stock disponible
                    break;
                case 'orange':
                    $this->SetTextColor(255, 140, 0); // Naranja para poco stock o consultar
                    break;
                case 'red':
                    $this->SetTextColor(200, 0, 0); // Rojo para sin stock
                    break;
                default:
                    $this->SetTextColor(100, 100, 100); // Gris por defecto
                    break;
            }
            
            $this->Cell(0, 6, 'STOCK: ' . $stock_info['display'], 0, 1, 'L');
            $this->Ln(2);
        }
        
        // Tallas si existen // Comment by @eeelian8
        $sizes = $this->get_product_sizes($product);
        if (!empty($sizes)) {
            $this->SetX($margin_left);
            $this->setRobotoFont('', 8);
            $this->SetTextColor(0, 0, 0);
            $this->Cell(0, 5, 'TALLES: ' . strtoupper(implode(', ', $sizes)), 0, 1, 'L');
            $this->Ln(1);
        }
        
        // Colores si existen // Comment by @eeelian8
        $colors = $this->get_product_colors($product);
        if (!empty($colors)) {
            $this->SetX($margin_left);
            $this->setRobotoFont('', 8);
            $this->SetTextColor(0, 0, 0);
            $this->Cell(0, 5, 'COLORES: ' . strtoupper(implode(', ', $colors)), 0, 1, 'L');
        }
        
        // Información adicional para productos variables // Comment by @eeelian8
        if ($product->is_type('variable')) {
            $variations = $product->get_children();
            $variations_count = count($variations);
            if ($variations_count > 0) {
                $this->SetX($margin_left);
                $this->setRobotoFont('', 8);
                $this->SetTextColor(100, 100, 100);
                $this->Cell(0, 5, 'VARIACIONES: ' . $variations_count . ' disponibles', 0, 1, 'L');
            }
        }
        
        // Mostrar información de stock específica para debug (solo en desarrollo) // Comment by @eeelian8
        // if (defined('WP_DEBUG') && WP_DEBUG) {
        //     $this->SetX($margin_left);
        //     $this->setRobotoFont('', 7);
        //     $this->SetTextColor(150, 150, 150);
        //     $debug_stock = $this->get_product_stock_quantity_for_pdf($product);
        //     $this->Cell(0, 4, 'DEBUG: Stock calculado = ' . $debug_stock, 0, 1, 'L');
        // }
    }
    
    private function add_company_footer_improved() {
        // Footer en la parte inferior con logo y datos // Comment by @eeelian8
        $footer_y = 250; // Posición Y del footer // Comment by @eeelian8
        
        // Logo SWAGA centrado // Comment by @eeelian8
        if (file_exists($this->logo_path)) {
            try {
                $this->Image(
                    $this->logo_path,
                    75,              // x centrado
                    $footer_y,       // y en footer
                    60,              // ancho del logo
                    0,               // alto automático
                    '',
                    '',
                    '',
                    false,
                    300,
                    '',
                    false,
                    false,
                    0
                );
                $footer_y += 20; // Espacio después del logo // Comment by @eeelian8
            } catch (Exception $e) {
                error_log('Error cargando logo en footer: ' . $e->getMessage()); // Comment by @eeelian8
            }
        }
        
        // Información de contacto centrada // Comment by @eeelian8
        $this->SetY($footer_y);
        $this->setRobotoFont('', 8);
        $this->SetTextColor(0, 0, 0);
        
        // Dirección // Comment by @eeelian8
        if (!empty($this->company_info['address'])) {
            $this->Cell(0, 4, $this->company_info['address'], 0, 1, 'C');
        }
        
        // Instagram // Comment by @eeelian8
        if (!empty($this->company_info['instagram'])) {
            $this->Cell(0, 4, $this->company_info['instagram'], 0, 1, 'C');
        }
        
        // Website // Comment by @eeelian8
        if (!empty($this->company_info['website'])) {
            $this->Cell(0, 4, $this->company_info['website'], 0, 1, 'C');
        }
        
        // Teléfono // Comment by @eeelian8
        if (!empty($this->company_info['phone'])) {
            $this->Cell(0, 4, $this->company_info['phone'], 0, 1, 'C');
        }
    }
    
    private function download_temp_image($url) {
        try {
            // Verificar URL válida // Comment by @eeelian8
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                error_log('URL de imagen inválida: ' . $url);
                return false;
            }
            
            $temp_file = tempnam(sys_get_temp_dir(), 'catalog_img_');
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'WordPress Catalog Generator',
                    'follow_location' => true,
                    'max_redirects' => 3
                ]
            ]);
            
            $image_data = file_get_contents($url, false, $context);
            if ($image_data !== false && strlen($image_data) > 0) {
                file_put_contents($temp_file, $image_data);
                
                // Verificar que es una imagen válida // Comment by @eeelian8
                $image_info = @getimagesize($temp_file);
                if ($image_info !== false) {
                    return $temp_file;
                } else {
                    error_log('Archivo descargado no es una imagen válida: ' . $url);
                }
            } else {
                error_log('No se pudo descargar imagen: ' . $url);
            }
            
            // Si llegamos aquí, eliminar el archivo temporal // Comment by @eeelian8
            @unlink($temp_file);
            
        } catch (Exception $e) {
            error_log('Error descargando imagen: ' . $e->getMessage() . ' URL: ' . $url);
        }
        
        return false;
    }
    
    private function get_product_colors($product) {
        $colors = array();
        
        if ($product->is_type('variable')) {
            $variations = $product->get_available_variations();
            foreach ($variations as $variation) {
                if (isset($variation['attributes']['attribute_pa_color'])) {
                    $colors[] = $variation['attributes']['attribute_pa_color'];
                } elseif (isset($variation['attributes']['attribute_color'])) {
                    $colors[] = $variation['attributes']['attribute_color'];
                }
            }
        } else {
            $attributes = $product->get_attributes();
            foreach ($attributes as $attribute) {
                if (in_array($attribute->get_name(), ['pa_color', 'color', 'pa_colour', 'colour'])) {
                    $terms = wc_get_product_terms($product->get_id(), $attribute->get_name());
                    foreach ($terms as $term) {
                        $colors[] = $term->name;
                    }
                }
            }
        }
        
        return array_unique($colors);
    }
    
    private function get_product_sizes($product) {
        $sizes = array();
        
        if ($product->is_type('variable')) {
            $variations = $product->get_available_variations();
            foreach ($variations as $variation) {
                if (isset($variation['attributes']['attribute_pa_size'])) {
                    $sizes[] = $variation['attributes']['attribute_pa_size'];
                } elseif (isset($variation['attributes']['attribute_size'])) {
                    $sizes[] = $variation['attributes']['attribute_size'];
                } elseif (isset($variation['attributes']['attribute_pa_talla'])) {
                    $sizes[] = $variation['attributes']['attribute_pa_talla'];
                }
            }
        } else {
            $attributes = $product->get_attributes();
            foreach ($attributes as $attribute) {
                if (in_array($attribute->get_name(), ['pa_size', 'size', 'pa_talla', 'talla'])) {
                    $terms = wc_get_product_terms($product->get_id(), $attribute->get_name());
                    foreach ($terms as $term) {
                        $sizes[] = $term->name;
                    }
                }
            }
        }
        
        return array_unique($sizes);
    }
}
?>