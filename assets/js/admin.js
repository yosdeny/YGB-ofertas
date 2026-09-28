/**
 * YGB Ofertas - Admin JavaScript
 * 
 * @package YGB_Ofertas
 * @version 1.8.3
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

    /**
     * Envuelve la selección del textarea con una etiqueta HTML.
     *
     * - Si hay texto seleccionado, lo envuelve: <tag>selección</tag>.
     * - Si no hay selección, inserta <tag></tag> y deja el cursor dentro.
     * - Respeta el resto del contenido y mantiene el foco en el textarea.
     *
     * @param {HTMLTextAreaElement} textarea
     * @param {string} tag Nombre de la etiqueta (strong, em, p, h3...).
     */
    function wrapSelection(textarea, tag) {
        if (!textarea) return;

        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var value = textarea.value;
        var selected = value.substring(start, end);

        var openTag = '<' + tag + '>';
        var closeTag = '</' + tag + '>';

        var replacement;
        var newCaretStart;
        var newCaretEnd;

        if (selected.length > 0) {
            replacement = openTag + selected + closeTag;
            newCaretStart = start + openTag.length;
            newCaretEnd = newCaretStart + selected.length;
        } else {
            replacement = openTag + closeTag;
            newCaretStart = start + openTag.length;
            newCaretEnd = newCaretStart;
        }

        textarea.value = value.substring(0, start) + replacement + value.substring(end);

        textarea.focus();
        // setSelectionRange() es la API estándar; el try/catch protege de
        // navegadores antiguos que la lanzan si el textarea aún no está
        // pintado. No se usa el patrón obsoleto de document.selection.
        try {
            textarea.setSelectionRange(newCaretStart, newCaretEnd);
        } catch (e) {
            // Silencio: la selección no es crítica para el funcionamiento.
        }

        // Notifica a otros scripts (p. ej. validaciones) que el valor cambió.
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }

    /**
     * Envuelve la selección en un enlace <a href="...">...</a>.
     * Pide la URL con window.prompt (no requiere dependencia extra).
     *
     * @param {HTMLTextAreaElement} textarea
     */
    function insertLink(textarea) {
        if (!textarea) return;

        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var value = textarea.value;
        var selected = value.substring(start, end);

        var defaultLabel = (typeof ygb_admin !== 'undefined' && ygb_admin.link_prompt)
            ? ygb_admin.link_prompt
            : 'URL:';

        var url = window.prompt(defaultLabel, 'https://');

        if (url === null || url === '') {
            return;
        }

        // Normaliza: si el usuario no escribió protocolo y no empieza por
        // almohadilla (ancla), asumimos https://. Dejamos pasar #ancla.
        url = url.trim();
        if (url.charAt(0) !== '#' && !/^[a-z]+:\/\//i.test(url) && !/^mailto:/i.test(url) && !/^tel:/i.test(url)) {
            url = 'https://' + url;
        }

        var openTag = '<a href="' + url + '">';
        var closeTag = '</a>';
        var label = selected.length > 0 ? selected : url;
        var replacement = openTag + label + closeTag;

        textarea.value = value.substring(0, start) + replacement + value.substring(end);

        var newCaretStart = start + openTag.length;
        var newCaretEnd = newCaretStart + label.length;

        textarea.focus();
        try {
            textarea.setSelectionRange(newCaretStart, newCaretEnd);
        } catch (e) {
            // Silencio.
        }

        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }

    /**
     * Inserta texto literal en la posición del cursor (o reemplaza la
     * selección actual). Se usa para <br>, listas, etc.
     *
     * @param {HTMLTextAreaElement} textarea
     * @param {string} text
     */
    function insertText(textarea, text) {
        if (!textarea) return;

        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var value = textarea.value;

        textarea.value = value.substring(0, start) + text + value.substring(end);

        var newCaret = start + text.length;
        textarea.focus();
        try {
            textarea.setSelectionRange(newCaret, newCaret);
        } catch (e) {
            // Silencio.
        }

        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }

    /**
     * Elimina todas las etiquetas HTML de la selección. No toca el resto del
     * textarea. Si no hay selección, limpia el contenido completo.
     *
     * @param {HTMLTextAreaElement} textarea
     */
    function stripTags(textarea) {
        if (!textarea) return;

        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var value = textarea.value;

        var target;
        var offset;

        if (start === end) {
            target = value;
            offset = 0;
        } else {
            target = value.substring(start, end);
            offset = start;
        }

        // Regex conservadora: solo retira pares <etiqueta ...>...</etiqueta>
        // y etiquetas sueltas. No pretende ser un parser HTML: es una utilidad
        // de limpieza rápida para el usuario, no un saneamiento de seguridad
        // (de eso se encarga wp_kses_post al guardar).
        var cleaned = target.replace(/<\/?[a-z][a-z0-9]*(\s[^>]*)?>/gi, '');

        textarea.value = value.substring(0, offset) + cleaned + value.substring(offset + target.length);

        var newCaret = offset + cleaned.length;
        textarea.focus();
        try {
            textarea.setSelectionRange(newCaret, newCaret);
        } catch (e) {
            // Silencio.
        }

        textarea.dispatchEvent(new Event('input', { bubbles: true }));
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
         * Barra de formato del campo Descripción (pestaña General).
         *
         * Los botones son type="button" y no tienen atributo name, así que no
         * entran en la recolección del AJAX de guardado. Simplemente modifican
         * el textarea #ygb-description; al pulsar "Guardar cambios", el nuevo
         * valor se envía y el servidor lo filtra con wp_kses_post().
         *
         * Delegación de eventos: la pestaña General puede ocultarse/mostrarse
         * al cambiar de pestaña sin recargar la página, así que enlazamos el
         * handler a un contenedor estable (document) para no perder los
         * listeners.
         */
        $(document).on('click', '.ygb-desc-toolbar button', function(e) {
            e.preventDefault();

            var $btn = $(this);
            var textarea = document.getElementById('ygb-description');
            if (!textarea) return;

            var wrapTag = $btn.data('ygb-wrap');
            if (wrapTag) {
                wrapSelection(textarea, String(wrapTag));
                return;
            }

            if ($btn.data('ygb-link')) {
                insertLink(textarea);
                return;
            }

            var insertValue = $btn.attr('data-ygb-insert');
            if (typeof insertValue === 'string' && insertValue.length > 0) {
                insertText(textarea, insertValue);
                return;
            }

            if ($btn.hasClass('ygb-desc-clear')) {
                stripTags(textarea);
            }
        });

        /**
         * Atajos de teclado dentro del textarea de descripción: Ctrl/Cmd+B
         * (negrita) y Ctrl/Cmd+I (cursiva). Coherente con el editor de WP.
         */
        $(document).on('keydown', '#ygb-description', function(e) {
            if (!(e.ctrlKey || e.metaKey)) {
                return;
            }

            var key = (e.key || '').toLowerCase();

            if (key === 'b') {
                e.preventDefault();
                wrapSelection(this, 'strong');
            } else if (key === 'i') {
                e.preventDefault();
                wrapSelection(this, 'em');
            }
        });

        /**
         * Selector de medios (imagen del popup en el modo "Solo una imagen")
         */
        var mediaFrame = null;

        function setImage(imageId, imageUrl, imageAlt) {
            $('#custom_image_id').val(imageId);

            if (imageId && imageUrl) {
                $('#ygb-image-preview')
                    .html($('<img>').attr({ src: imageUrl, alt: imageAlt || '' }))
                    .show();
                $('#ygb-remove-image').show();
            } else {
                $('#ygb-image-preview').empty().hide();
                $('#ygb-remove-image').hide();
            }
        }

        $('#ygb-select-image').on('click', function(e) {
            e.preventDefault();

            // Reutilizar la instancia para no recrear el frame en cada clic.
            if (mediaFrame) {
                mediaFrame.open();
                return;
            }

            if (typeof wp === 'undefined' || !wp.media) {
                return;
            }

            mediaFrame = wp.media({
                title: (typeof ygb_media !== 'undefined' && ygb_media.title) ? ygb_media.title : '',
                button: { text: (typeof ygb_media !== 'undefined' && ygb_media.button) ? ygb_media.button : '' },
                multiple: false,
                library: { type: 'image' }
            });

            mediaFrame.on('select', function() {
                var attachment = mediaFrame.state().get('selection').first().toJSON();

                if (!attachment || !attachment.id) {
                    return;
                }

                var url = '';
                if (attachment.sizes && attachment.sizes.medium && attachment.sizes.medium.url) {
                    url = attachment.sizes.medium.url;
                } else if (attachment.url) {
                    url = attachment.url;
                }

                setImage(attachment.id, url, attachment.alt);
            });

            mediaFrame.open();
        });

        $('#ygb-remove-image').on('click', function(e) {
            e.preventDefault();
            setImage('', '', '');
        });

        /**
         * Mostrar/ocultar campos segun el tipo de contenido del popup
         */
        function updatePopupTypeVisibility() {
            var type = $('input[name="popup_type"]:checked').val() || 'product';

            $('.ygb-mode-product').toggle(type === 'product');
            $('.ygb-mode-media').toggle(type !== 'product');
        }

        $(document).on('change', 'input[name="popup_type"]', updatePopupTypeVisibility);

        if ($('input[name="popup_type"]').length) {
            updatePopupTypeVisibility();
        }

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