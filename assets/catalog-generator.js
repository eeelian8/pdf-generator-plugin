jQuery(document).ready(function($) {
    // Cargar subcategorías cuando se seleccionen categorías // Comment by @eeelian8
    $('#categories').on('change', function() {
        var selectedCategories = $(this).val();
        
        if (selectedCategories && selectedCategories.length > 0) {
            $('#subcategories').html('<option value="">Cargando subcategorías...</option>');
            
            $.ajax({
                url: catalog_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_subcategories',
                    parent_ids: selectedCategories,
                    nonce: catalog_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data && response.data.trim() !== '') {
                            $('#subcategories').html('<option value="">Seleccionar subcategorías</option>' + response.data);
                        } else {
                            $('#subcategories').html('<option value="">No hay subcategorías disponibles</option>');
                        }
                    } else {
                        $('#subcategories').html('<option value="">Error cargando subcategorías</option>');
                    }
                },
                error: function() {
                    $('#subcategories').html('<option value="">Error al cargar subcategorías</option>');
                    alert('Error al cargar subcategorías');
                }
            });
        } else {
            $('#subcategories').html('<option value="">Selecciona primero una categoría</option>');
        }
    });
    
    // Manejar envío del formulario // Comment by @eeelian8
    $('#catalog-form').on('submit', function(e) {
        e.preventDefault();
        
        // Validar que al menos hay productos en WooCommerce // Comment by @eeelian8
        var hasFilters = $('#categories').val() || $('#subcategories').val() || 
                        $('#tags').val() || $('#sku').val().trim() || 
                        $('#min_price').val() || $('#max_price').val() || 
                        $('#min_stock').val();
        
        if (!hasFilters) {
            if (!confirm('No has seleccionado ningún filtro. ¿Deseas generar un catálogo con todos los productos activos (máximo 50)?')) {
                return;
            }
        }
        
        // Validar rango de precios // Comment by @eeelian8
        var minPrice = parseFloat($('#min_price').val()) || 0;
        var maxPrice = parseFloat($('#max_price').val()) || 0;
        
        if (minPrice > 0 && maxPrice > 0 && minPrice > maxPrice) {
            alert('El precio mínimo no puede ser mayor al precio máximo.');
            $('#min_price').focus();
            return;
        }
        
        // Validar stock mínimo // Comment by @eeelian8
        var minStock = parseInt($('#min_stock').val()) || 0;
        if (minStock < 0) {
            alert('El stock mínimo no puede ser negativo.');
            $('#min_stock').focus();
            return;
        }
        
        var formData = {
            action: 'generate_catalog_pdf',
            nonce: catalog_ajax.nonce,
            categories: $('#categories').val() || [],
            subcategories: $('#subcategories').val() || [],
            tags: $('#tags').val() || [],
            sku: $('#sku').val().trim(),
            min_price: $('#min_price').val(),
            max_price: $('#max_price').val(),
            min_stock: $('#min_stock').val()
        };
        
        // Mostrar indicador de carga mejorado // Comment by @eeelian8
        $('#loading').show();
        $('.button-primary').prop('disabled', true);
        $('#catalog-result').empty();
        
        // Mensaje de progreso con timer // Comment by @eeelian8
        var loadingMessages = [
            'Generando catálogo...',
            'Procesando productos...',
            'Creando imágenes...',
            'Finalizando PDF...'
        ];
        var messageIndex = 0;
        
        $('#loading').html(loadingMessages[messageIndex]);
        
        var progressInterval = setInterval(function() {
            messageIndex = (messageIndex + 1) % loadingMessages.length;
            $('#loading').html(loadingMessages[messageIndex] + ' Esto puede tomar varios minutos.');
        }, 3000);
        
        $.ajax({
            url: catalog_ajax.ajax_url,
            type: 'POST',
            data: formData,
            timeout: 300000, // 5 minutos de timeout // Comment by @eeelian8
            success: function(response) {
                clearInterval(progressInterval);
                $('#loading').hide();
                $('.button-primary').prop('disabled', false);
                
                if (response.success) {
                    var fileExtension = response.data.pdf_url.split('.').pop().toLowerCase();
                    var fileType = (fileExtension === 'pdf') ? 'PDF' : 'HTML';
                    var downloadText = (fileExtension === 'pdf') ? 'Descargar PDF' : 'Ver Catálogo';
                    var fileSize = response.data.file_size || 'Desconocido';
                    
                    var successHtml = '<div class="notice notice-success">' +
                        '<p><strong>¡Catálogo generado exitosamente!</strong></p>' +
                        '<p>Se encontraron ' + response.data.products_count + ' productos.</p>' +
                        '<p>Tipo de archivo: ' + fileType + '</p>';
                    
                    if (fileSize !== 'Desconocido') {
                        successHtml += '<p>Tamaño del archivo: ' + fileSize + '</p>';
                    }
                    
                    successHtml += '<p><a href="' + response.data.pdf_url + '" target="_blank" class="button button-primary">' + downloadText + '</a></p>';
                    
                    if (fileExtension === 'html') {
                        successHtml += '<p><em>Nota: Se generó un archivo HTML porque TCPDF no está instalado correctamente. Para PDFs reales, instala la librería TCPDF en /lib/tcpdf/</em></p>';
                    }
                    
                    successHtml += '</div>';
                    
                    $('#catalog-result').html(successHtml);
                    
                    // Scroll hacia el resultado // Comment by @eeelian8
                    $('html, body').animate({
                        scrollTop: $('#catalog-result').offset().top
                    }, 500);
                    
                } else {
                    var errorHtml = '<div class="notice notice-error">' +
                        '<p><strong>Error:</strong> ' + response.data + '</p>' +
                        '<p><em>Sugerencias para solucionar el problema:</em></p>' +
                        '<ul>' +
                        '<li>Verifica que tienes productos activos en WooCommerce</li>' +
                        '<li>Asegúrate de que la librería TCPDF está instalada en /lib/tcpdf/</li>' +
                        '<li>Revisa que los productos tienen imágenes configuradas</li>' +
                        '<li>Verifica los permisos de escritura en wp-content/uploads/</li>' +
                        '<li>Intenta con menos productos usando filtros más específicos</li>' +
                        '</ul>' +
                        '</div>';
                    
                    $('#catalog-result').html(errorHtml);
                }
            },
            error: function(xhr, status, error) {
                clearInterval(progressInterval);
                $('#loading').hide();
                $('.button-primary').prop('disabled', false);
                
                var errorMessage = 'No se pudo generar el catálogo.';
                
                if (status === 'timeout') {
                    errorMessage = 'La generación del catálogo está tomando demasiado tiempo. Intenta con menos productos.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Error interno del servidor. Revisa los logs de WordPress.';
                } else if (xhr.status === 413) {
                    errorMessage = 'El catálogo es demasiado grande. Reduce el número de productos.';
                } else if (xhr.responseText) {
                    try {
                        var responseObj = JSON.parse(xhr.responseText);
                        if (responseObj.data) {
                            errorMessage += ' Detalle: ' + responseObj.data;
                        }
                    } catch (e) {
                        errorMessage += ' Error: ' + xhr.responseText.substring(0, 200);
                    }
                }
                
                var errorHtml = '<div class="notice notice-error">' +
                    '<p><strong>Error:</strong> ' + errorMessage + '</p>' +
                    '<p><em>Pasos para solucionar:</em></p>' +
                    '<ul>' +
                    '<li>1. Verifica que TCPDF está instalado en /lib/tcpdf/</li>' +
                    '<li>2. Comprueba los permisos de escritura en wp-content/uploads/</li>' +
                    '<li>3. Intenta con menos productos usando filtros</li>' +
                    '<li>4. Revisa los logs de error de WordPress</li>' +
                    '<li>5. Contacta al administrador si el problema persiste</li>' +
                    '</ul>' +
                    '</div>';
                
                $('#catalog-result').html(errorHtml);
            }
        });
    });
    
    // Validación en tiempo real de precios // Comment by @eeelian8
    $('#min_price, #max_price').on('input blur', function() {
        var minPrice = parseFloat($('#min_price').val()) || 0;
        var maxPrice = parseFloat($('#max_price').val()) || 0;
        
        // Limpiar errores previos // Comment by @eeelian8
        $('.price-error').remove();
        $('#min_price, #max_price').removeClass('error-field');
        
        if (minPrice > 0 && maxPrice > 0 && minPrice > maxPrice) {
            $(this).addClass('error-field');
            $(this).after('<span class="price-error" style="color: #dc3232; font-size: 12px; display: block; margin-top: 5px;">El precio mínimo no puede ser mayor al máximo</span>');
        }
        
        // Validar que los precios sean positivos // Comment by @eeelian8
        if ($(this).val() && parseFloat($(this).val()) < 0) {
            $(this).addClass('error-field');
            $(this).after('<span class="price-error" style="color: #dc3232; font-size: 12px; display: block; margin-top: 5px;">El precio no puede ser negativo</span>');
        }
    });
    
    // Validación de stock mínimo // Comment by @eeelian8
    $('#min_stock').on('input blur', function() {
        $('.stock-error').remove();
        $(this).removeClass('error-field');
        
        var stockValue = parseInt($(this).val());
        if ($(this).val() && (isNaN(stockValue) || stockValue < 0)) {
            $(this).addClass('error-field');
            $(this).after('<span class="stock-error" style="color: #dc3232; font-size: 12px; display: block; margin-top: 5px;">El stock debe ser un número positivo</span>');
        }
    });
    
    // Validación de SKU // Comment by @eeelian8
    $('#sku').on('input', function() {
        var sku = $(this).val().trim();
        $('.sku-error').remove();
        $(this).removeClass('error-field');
        
        // Validar caracteres especiales problemáticos // Comment by @eeelian8
        if (sku && /[<>\"'&]/.test(sku)) {
            $(this).addClass('error-field');
            $(this).after('<span class="sku-error" style="color: #dc3232; font-size: 12px; display: block; margin-top: 5px;">El SKU no puede contener caracteres especiales como < > " \' &</span>');
        }
    });
    
    // Mejorar la experiencia de usuario con los selects múltiples // Comment by @eeelian8
    $('#categories, #subcategories, #tags').each(function() {
        $(this).css({
            'font-family': 'Roboto, Arial, sans-serif',
            'font-size': '14px',
            'padding': '8px'
        });
        
        // Añadir contador de elementos seleccionados // Comment by @eeelian8
        $(this).on('change', function() {
            var count = $(this).val() ? $(this).val().length : 0;
            var label = $(this).closest('tr').find('th');
            var originalText = label.text().replace(/ \(\d+\)$/, '');
            
            if (count > 0) {
                label.text(originalText + ' (' + count + ')');
            } else {
                label.text(originalText);
            }
        });
    });
    
    // Añadir botón para limpiar filtros // Comment by @eeelian8
    if (!$('#clear-filters').length) {
        $('#catalog-form .submit').prepend(
            '<input type="button" id="clear-filters" class="button" value="Limpiar Filtros" style="margin-right: 10px;">'
        );
    }
    
    $('#clear-filters').on('click', function() {
        $('#categories, #subcategories, #tags').val(null);
        $('#sku, #min_price, #max_price, #min_stock').val('');
        $('.price-error, .stock-error, .sku-error').remove();
        $('#min_price, #max_price, #min_stock, #sku').removeClass('error-field');
        $('#catalog-result').empty();
        
        // Resetear contadores en labels // Comment by @eeelian8
        $('#categories, #subcategories, #tags').each(function() {
            var label = $(this).closest('tr').find('th');
            var originalText = label.text().replace(/ \(\d+\)$/, '');
            label.text(originalText);
        });
        
        // Resetear subcategorías // Comment by @eeelian8
        $('#subcategories').html('<option value="">Selecciona primero una categoría</option>');
    });
    
    // Añadir tooltips informativos // Comment by @eeelian8
    $('#categories').attr('title', 'Selecciona una o más categorías principales');
    $('#subcategories').attr('title', 'Aparecerán las subcategorías de las categorías seleccionadas');
    $('#tags').attr('title', 'Filtra por etiquetas de productos');
    $('#sku').attr('title', 'Busca productos por SKU específico o parte del SKU');
    $('#min_price').attr('title', 'Precio mínimo en la moneda de tu tienda');
    $('#max_price').attr('title', 'Precio máximo en la moneda de tu tienda');
    $('#min_stock').attr('title', 'Solo productos con stock igual o superior a este valor');
    
    // Prevenir envío accidental del formulario con Enter // Comment by @eeelian8
    $('#catalog-form input').on('keypress', function(e) {
        if (e.which === 13 && $(this).attr('type') !== 'submit') {
            e.preventDefault();
        }
    });
    
    // Mostrar información de ayuda contextual // Comment by @eeelian8
    function showContextualHelp() {
        if (!$('#contextual-help').length) {
            var helpHtml = '<div id="contextual-help" style="background: #f0f8ff; border: 1px solid #0073aa; padding: 15px; margin: 20px 0; border-radius: 5px;">' +
                '<h4 style="margin-top: 0;">💡 Consejos para usar el generador:</h4>' +
                '<ul style="margin-bottom: 0;">' +
                '<li><strong>Sin filtros:</strong> Genera un catálogo con hasta 50 productos aleatorios</li>' +
                '<li><strong>Con filtros:</strong> Usa cualquier combinación para obtener productos específicos</li>' +
                '<li><strong>Rendimiento:</strong> Menos productos = PDF más rápido de generar</li>' +
                '<li><strong>Calidad:</strong> Asegúrate de que los productos tengan imágenes de buena calidad</li>' +
                '</ul>' +
                '</div>';
            
            $('#catalog-form').before(helpHtml);
        }
    }
    
    // Mostrar ayuda después de 3 segundos si no se ha hecho nada // Comment by @eeelian8
    setTimeout(function() {
        if ($('#catalog-result').is(':empty')) {
            showContextualHelp();
        }
    }, 3000);
    
    // Ocultar ayuda cuando el usuario interactúe // Comment by @eeelian8
    $('#catalog-form input, #catalog-form select').one('focus change', function() {
        $('#contextual-help').fadeOut();
    });
});

// Funciones adicionales para mejorar la experiencia // Comment by @eeelian8
jQuery(window).on('beforeunload', function() {
    if (jQuery('#loading').is(':visible')) {
        return 'El catálogo se está generando. ¿Estás seguro de que quieres salir?';
    }
});

// Añadir estilos CSS inline para mejorar la apariencia // Comment by @eeelian8
jQuery(document).ready(function($) {
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .error-field {
                border-color: #dc3232 !important;
                box-shadow: 0 0 0 1px #dc3232 !important;
            }
            
            #loading {
                font-weight: bold;
                color: #0073aa;
                animation: pulse 1.5s ease-in-out infinite alternate;
            }
            
            @keyframes pulse {
                from { opacity: 1; }
                to { opacity: 0.5; }
            }
            
            .notice ul {
                padding-left: 20px;
            }
            
            .notice li {
                margin-bottom: 5px;
            }
            
            #catalog-form select:focus,
            #catalog-form input:focus {
                outline: 2px solid #0073aa;
                outline-offset: 1px;
            }
            
            .button-primary:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }
        `)
        .appendTo('head');
});