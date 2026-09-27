/**
 * YGB Ofertas - Popup JavaScript
 * 
 * @package YGB_Ofertas
 * @version 1.8.2
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Verificar que exista el popup y la configuración
        if ($('#ygb-ofertas-popup').length === 0) {
            return;
        }

        if (typeof ygb_ofertas === 'undefined') {
            return;
        }

        var popup = $('#ygb-ofertas-popup');
        var settings = ygb_ofertas.settings;
        var hasShown = false;

        /**
         * Normaliza un ajuste booleano que puede llegar como 1, '1' o true
         * segun como lo serialice wp_localize_script.
         */
        function is_on(key) {
            return settings[key] === 1 || settings[key] === '1' || settings[key] === true;
        }

        /**
         * Convierte un ajuste numerico a entero en base 10.
         */
        function to_int(value, fallback) {
            var parsed = parseInt(value, 10);
            return isNaN(parsed) ? fallback : parsed;
        }

        /**
         * Convierte un ajuste numerico a float. Necesario para ajustes que
         * admiten decimales (p. ej. display_delay, que el panel configura
         * con step="0.5"); parseInt perderia la parte decimal.
         */
        function to_float(value, fallback) {
            var parsed = parseFloat(value);
            return isNaN(parsed) ? fallback : parsed;
        }

        /**
         * Inicializar el popup
         */
        function initPopup() {
            // Verificar si el popup está activado
            if (!is_on('enabled')) {
                return;
            }

            // Verificar programación de fechas
            if (!checkDateScheduling()) {
                return;
            }

            // Verificar compatibilidad de dispositivos
            if (!checkDeviceCompatibility()) {
                return;
            }

            // Configurar triggers
            setupTriggers();

            var delay = to_float(settings.display_delay, 0);
            var has_trigger = is_on('show_on_exit') || is_on('show_on_scroll');

            // Sin triggers propios: mostrar tras el delay configurado.
            // Con delay 0 se espera 500 ms para no competir con el render inicial.
            if (!has_trigger) {
                setTimeout(function() {
                    showPopup();
                }, delay > 0 ? delay * 1000 : 500);
            }
        }

        /**
         * Verificar programación de fechas
         */
        function checkDateScheduling() {
            if (settings.popup_status === 'scheduled' && settings.start_date && settings.end_date) {
                var now = new Date();
                var startDate = new Date(settings.start_date);
                var endDate = new Date(settings.end_date);
                
                if (now < startDate || now > endDate) {
                    return false;
                }
            }
            return true;
        }

        /**
         * Verificar compatibilidad de dispositivos
         */
        function checkDeviceCompatibility() {
            var isMobile = window.innerWidth <= 768;
            var isTablet = window.innerWidth > 768 && window.innerWidth <= 1024;
            
            if (isMobile && is_on('mobile_disabled')) {
                return false;
            }
            
            if (isTablet && is_on('tablet_disabled')) {
                return false;
            }
            
            return true;
        }

        /**
         * Configurar triggers
         */
        function setupTriggers() {
            // Exit intent
            if (is_on('show_on_exit')) {
                setupExitIntent();
            }

            // Scroll trigger
            if (is_on('show_on_scroll')) {
                setupScrollTrigger();
            }
        }

        /**
         * Mostrar popup
         */
        function showPopup() {
            if (hasShown) {
                return;
            }
            
            if (!shouldShowPopup()) {
                return;
            }

            popup.fadeIn(400);
            hasShown = true;
            recordAction('view');
            setCookie();
        }

        /**
         * Cerrar popup
         */
        function closePopup() {
            popup.fadeOut(400);
            recordAction('close');
        }

        /**
         * Verificar si se debe mostrar el popup
         */
        function shouldShowPopup() {
            // Verificar cookie
            if (getCookie('ygb_ofertas_shown') && !is_on('show_always')) {
                return false;
            }

            return true;
        }

        /**
         * Configurar exit intent
         */
        function setupExitIntent() {
            var exitIntentTriggered = false;
            
            $(document).on('mouseleave', function(e) {
                if (exitIntentTriggered || hasShown) return;
                
                if (e.clientY < 10) {
                    exitIntentTriggered = true;
                    
                    setTimeout(function() {
                        showPopup();
                    }, 100);
                }
            });
        }

        /**
         * Configurar scroll trigger
         */
        function setupScrollTrigger() {
            var triggered = false;
            var scrollTimeout;
            
            $(window).on('scroll', function() {
                if (triggered || hasShown) return;
                
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(function() {
                    var docHeight = $(document).height() - $(window).height();

                    // Si la página cabe entera en el viewport, docHeight es 0
                    // (o negativo en casos raros). Dividir daría Infinity y
                    // dispararía el popup sin que el usuario haga scroll.
                    if (docHeight <= 0) {
                        return;
                    }

                    var scrollPercent = ($(window).scrollTop() / docHeight) * 100;
                    
                    if (scrollPercent >= to_int(settings.scroll_percentage, 50)) {
                        triggered = true;
                        showPopup();
                    }
                }, 100);
            });
        }

        /**
         * Establecer cookie
         *
         * SameSite=Lax evita que la cookie viaje en requests cross-site,
         * que es lo correcto para una cookie de estado de UI.
         */
        function setCookie() {
            var days = parseInt(settings.cookie_expiration, 10) || 1;
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            document.cookie = 'ygb_ofertas_shown=1; expires=' + date.toUTCString() + '; path=/; SameSite=Lax';
        }

        /**
         * Obtener cookie
         */
        function getCookie(name) {
            var value = '; ' + document.cookie;
            var parts = value.split('; ' + name + '=');
            if (parts.length === 2) return parts.pop().split(';').shift();
        }

        /**
         * Registrar acción
         */
        function recordAction(action) {
            var productId = popup.data('product-id');
            
            if (!productId) return;
            
            $.ajax({
                url: ygb_ofertas.ajax_url,
                type: 'POST',
                data: {
                    action: 'save_popup_stats',
                    stats_action: action,
                    product_id: productId,
                    nonce: ygb_ofertas.nonce
                }
            });
        }

        // Bind de eventos
        $('.popup-close, .popup-close-link').on('click', function(e) {
            e.preventDefault();
            closePopup();
        });

        $('.popup-overlay').on('click', function(e) {
            if ($(e.target).hasClass('popup-overlay')) {
                closePopup();
            }
        });

        $('.popup-button').on('click', function() {
            recordAction('click');
        });

        $(document).on('keyup', function(e) {
            if (e.key === 'Escape' && popup.is(':visible')) {
                closePopup();
            }
        });

        // Inicializar
        initPopup();
    });

})(jQuery);