<?php
// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="ygb-ofertas-popup" class="ygb-ofertas-popup popup-<?php echo esc_attr($settings['animation']); ?> ygb-type-<?php echo esc_attr(isset($popup_type) ? $popup_type : 'product'); ?>"<?php echo $producto['id'] > 0 ? ' data-product-id="' . esc_attr($producto['id']) . '"' : ''; ?>
     style="display: none;"
     role="dialog"
     aria-modal="true"
     aria-label="<?php echo esc_attr($settings['title']); ?>"
     aria-hidden="true">
    
    <div class="popup-overlay" style="background-color: <?php echo esc_attr($settings['overlay_color']); ?>;"></div>
    
    <div class="popup-container" 
         style="--ygb-popup-width: <?php echo esc_attr($settings['width']); ?>px;
                max-width: <?php echo esc_attr($settings['width']); ?>px; 
                background-color: <?php echo esc_attr($settings['background_color']); ?>;
                color: <?php echo esc_attr($settings['text_color']); ?>;">
        
        <?php if ($settings['close_button']) : ?>
        <button type="button" class="popup-close" aria-label="<?php esc_attr_e('Cerrar popup', 'ygb-ofertas'); ?>">
            <span aria-hidden="true">&times;</span>
        </button>
        <?php endif; ?>
        
        <div class="popup-content">
            
            <?php
            // Tipo de popup efectivo ('product' o 'image'). Si por lo que sea
            // no llega definido, asumimos 'product' para no romper el layout
            // clásico del plugin.
            $ygb_popup_type = isset($popup_type) ? (string) $popup_type : 'product';
            ?>
            
            <?php if (!empty($producto['image'])) : ?>
            <div class="popup-image">
                <?php
                $ygb_image_alt = esc_attr($producto['title']);

                if (!empty($producto['image_id'])) {
                    // Adjunto de la biblioteca: srcset/sizes generados por WordPress.
                    echo wp_get_attachment_image(absint($producto['image_id']), 'large', false, array('alt' => $ygb_image_alt));
                } else {
                    // Fallback (por ejemplo, imagen de producto sin ID resolvable).
                    printf('<img src="%s" alt="%s">', esc_url($producto['image']), $ygb_image_alt);
                }
                ?>
            </div>
            <?php endif; ?>
            
            <?php if ('product' === $ygb_popup_type) : ?>
                
                <div class="popup-header">
                    <h2 class="popup-title"><?php echo esc_html($settings['title']); ?></h2>
                    
                    <?php if (!empty($producto['discount']) && $producto['discount'] > 0) : ?>
                    <div class="popup-discount-badge">
                        -<?php echo esc_html($producto['discount']); ?>%
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($producto['title']) || !empty($producto['description'])) : ?>
                <div class="popup-product">
                    <?php if (!empty($producto['title'])) : ?>
                    <h3 class="product-title"><?php echo esc_html($producto['title']); ?></h3>
                    <?php endif; ?>
                    
                    <?php if (!empty($producto['description'])) : ?>
                    <p class="product-description"><?php echo esc_html($producto['description']); ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($producto['price']) || !empty($producto['sale_price'])) : ?>
                    <div class="product-price">
                        <?php if (!empty($producto['sale_price'])) : ?>
                            <span class="price-sale"><?php echo wc_price($producto['sale_price']); ?></span>
                            <span class="price-regular"><?php echo wc_price($producto['regular_price']); ?></span>
                        <?php else : ?>
                            <span class="price-regular"><?php echo wc_price($producto['price']); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
            <?php endif; ?>
            
            <?php if (!empty($settings['description'])) : ?>
            <div class="popup-description">
                <p><?php echo esc_html($settings['description']); ?></p>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($producto['permalink'])) : ?>
            <div class="popup-actions">
                <a href="<?php echo esc_url($producto['permalink']); ?>" 
                   class="popup-button"
                   style="background-color: <?php echo esc_attr($settings['button_color']); ?>;">
                    <?php
                    $ygb_button_text = !empty($producto['button_text']) ? $producto['button_text'] : $settings['button_text'];
                    echo esc_html($ygb_button_text);
                    ?>
                </a>
            </div>
            <?php endif; ?>
            
            <?php if ($settings['show_close_after']) : ?>
            <div class="popup-footer">
                <button type="button" class="popup-close-link"><?php _e('Cerrar', 'ygb-ofertas'); ?></button>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>