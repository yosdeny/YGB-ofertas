/**
 * YGB Ofertas - Admin JavaScript
 * 
 * @package YGB_Ofertas
 * @version 1.7.7
 */

(function($) {
    'use strict';

    var searchTimeout;
    
    // CORRECCIÓN DE SEGURIDAD: Función para escapar HTML y prevenir XSS
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    $(document).ready(function() {
        
        /**
         * Guardar pestaña actual vía AJAX
         */
        $('#ygb-save-tab').on('click', function() {
            var $button = $(this);
            var $spinner = $('#ygb-save-spinner');
            var $notice = $('#ygb-save-notice');
            var tab = $button.data('tab');
            
            // Recopilar datos del formulario de la pestaña actual
            var formData = {};
            
            $('#tab-' + tab + ' input, #tab-' + tab + ' select, #tab-' + tab + ' textarea').each(function() {
                var $input = $(this);
                var name = $input.attr('name');
                var type = $input.attr('type');
                
                // Solo procesar si tiene nombre
                if (!name) return;
                
                // Para selects múltiples (pages[])
                if ($input.is('select[multiple]')) {
                    var values = [];
                    $input.find('option:selected').each(function() {
                        values.push($(this).val());
                    });
                    // Eliminar los corchetes del nombre
                    var cleanName = name.replace('[]', '');
                    formData[cleanName] = values;
                }
                // Para checkboxes
                else if ($input.is(':checkbox')) {
                    formData[name] = $input.is(':checked') ? '1' : '0';
                } 
                // Para radios
                else if ($input.is(':radio')) {
                    if ($input.is(':checked')) {
                        formData[name] = $input.val();
                    }
                }
                // Para el resto de inputs
                else {
                    formData[name] = $input.val();
                }
            });
            
            // Deshabilitar botón
            $button.prop('disabled', true);
            $spinner.show();
            $notice.hide().removeClass('notice-success notice-error');
            
            // Enviar AJAX
            $.ajax({
                url: ygb_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ygb_save_tab',
                    tab: tab,
                    data: formData,
                    nonce: ygb_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $notice
                            .addClass('notice-success')
                            .html('<p>' + escapeHtml(response.data.message) + '</p>')
                            .show();
                        
                        setTimeout(function() {
                            $notice.fadeOut();
                        }, 3000);
                    } else {
                        $notice
                            .addClass('notice-error')
                            .html('<p>Error: ' + escapeHtml(response.data || 'Error desconocido') + '</p>')
                            .show();
                    }
                },
                error: function(xhr, status, error) {
                    $notice
                        .addClass('notice-error')
                        .html('<p>' + escapeHtml(ygb_admin.error_text) + '</p>')
                        .show();
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.hide();
                }
            });
        });
        
        /**
         * Cambiar de pestaña
         */
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            
            var tab = $(this).data('tab');
            
            // Actualizar clases activas
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            // Mostrar pestaña correspondiente
            $('.tab-pane').hide();
            $('#tab-' + tab).show();
            
            // Actualizar botón
            $('#ygb-save-tab').data('tab', tab);
            
            // Limpiar notificaciones al cambiar de pestaña
            $('#ygb-save-notice').hide().removeClass('notice-success notice-error');
        });
        
        /**
         * Búsqueda de productos
         */
        $('#product-search').on('keyup', function() {
            var searchTerm = $(this).val();
            
            clearTimeout(searchTimeout);

            if (searchTerm.length < 2) {
                $('#product-search-results').hide();
                return;
            }

            searchTimeout = setTimeout(function() {
                $.ajax({
                    url: ygb_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'ygb_search_products',
                        search: searchTerm,
                        nonce: ygb_admin.nonce
                    },
                    beforeSend: function() {
                        $('#product-search').addClass('loading');
                        $('#product-search-results').html('<div style="padding:10px;text-align:center;">Buscando...</div>').show();
                    },
                    success: function(response) {
                        $('#product-search').removeClass('loading');
                        
                        if (response.success && response.data.length > 0) {
                            var html = '';
                            
                            // CORRECCIÓN DE SEGURIDAD: Escapar datos para prevenir XSS
                            response.data.forEach(function(product) {
                                var escapedName = escapeHtml(product.text);
                                var escapedPrice = escapeHtml(product.price);
                                var escapedImage = escapeHtml(product.image || '');
                                
                                html += '<div class="product-search-result" ' +
                                       'data-id="' + escapeHtml(String(product.id)) + '" ' +
                                       'data-name="' + escapedName + '" ' +
                                       'data-price="' + escapedPrice + '" ' +
                                       'data-image="' + escapedImage + '">';
                                
                                if (product.image) {
                                    html += '<img src="' + escapedImage + '" alt="' + escapedName + '">';
                                } else {
                                    html += '<div style="width:40px;height:40px;background:#f0f0f0;border-radius:4px;"></div>';
                                }
                                
                                html += '<div class="product-info">';
                                html += '<span class="product-name">' + escapedName + '</span>';
                                html += '<span class="product-price">' + escapedPrice + '</span>';
                                html += '</div>';
                                html += '</div>';
                            });
                            
                            $('#product-search-results').html(html).show();
                        } else {
                            $('#product-search-results').html('<div style="padding:10px;text-align:center;">No se encontraron productos</div>');
                        }
                    },
                    error: function() {
                        $('#product-search').removeClass('loading');
                        $('#product-search-results').html('<div style="padding:10px;text-align:center;color:#dc3232;">Error en la búsqueda</div>');
                    }
                });
            }, 300);
        });

        /**
         * Seleccionar producto
         */
        $(document).on('click', '.product-search-result', function() {
            var id = $(this).data('id');
            var name = $(this).data('name');
            var price = $(this).data('price');
            var image = $(this).data('image');
            
            $('#selected_product').val(id);
            
            var infoHtml = '';
            
            if (image) {
                infoHtml += '<img src="' + escapeHtml(image) + '" alt="' + escapeHtml(name) + '">';
            } else {
                infoHtml += '<div style="width:80px;height:80px;background:#f0f0f0;border-radius:4px;"></div>';
            }
            
            infoHtml += '<div class="selected-product-details">';
            infoHtml += '<h3>' + escapeHtml(name) + '</h3>';
            infoHtml += '<p><strong>ID:</strong> ' + escapeHtml(String(id)) + '</p>';
            infoHtml += '<p><strong>Precio:</strong> ' + escapeHtml(price) + '</p>';
            infoHtml += '</div>';
            infoHtml += '<a href="#" id="remove-product" class="remove-product">Quitar producto</a>';
            
            $('#selected-product-info').html(infoHtml).show();
            $('#product-search').val('');
            $('#product-search-results').hide();
        });

        /**
         * Quitar producto
         */
        $(document).on('click', '#remove-product', function(e) {
            e.preventDefault();
            
            if (confirm('¿Estás seguro de que quieres quitar este producto?')) {
                $('#selected_product').val('');
                $('#selected-product-info').hide();
            }
        });

        /**
         * Cerrar resultados de búsqueda
         */
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#product-search').length && 
                !$(e.target).closest('.product-search-result').length) {
                $('#product-search-results').hide();
            }
        });

        /**
         * Prevenir submit con Enter
         */
        $('#product-search').on('keydown', function(e) {
            if (e.keyCode === 13) {
                e.preventDefault();
                return false;
            }
        });

        /**
         * Mostrar/ocultar selector de páginas
         */
        $('input[name="specific_pages_only"]').on('change', function() {
            if ($(this).is(':checked')) {
                $('#selector-paginas').slideDown();
            } else {
                $('#selector-paginas').slideUp();
            }
        });

        /**
         * Mostrar/ocultar fechas programadas
         */
        $('input[name="popup_status"]').on('change', function() {
            if ($(this).val() === 'scheduled') {
                $('#fechas-programadas').slideDown();
            } else {
                $('#fechas-programadas').slideUp();
            }
        });

        /**
         * Inicializar estados
         */
        if ($('input[name="specific_pages_only"]').is(':checked')) {
            $('#selector-paginas').show();
        }
        
        if ($('input[name="popup_status"]:checked').val() === 'scheduled') {
            $('#fechas-programadas').show();
        }
    });

})(jQuery);