<?php
// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="ygb-ofertas-popup" class="ygb-ofertas-popup popup-<?php echo esc_attr($settings['animation']); ?>"<?php echo $producto['id'] > 0 ? ' data-product-id="' . esc_attr($producto['id']) . '"' : ''; ?>
     style="display: none;">
    
    <div class="popup-overlay" style="background-color: <?php echo esc_attr($settings['overlay_color']); ?>;"></div>
    
    <div class="popup-container" 
         style="max-width: <?php echo esc_attr($settings['width']); ?>px; 
                background-color: <?php echo esc_attr($settings['background_color']); ?>;
                color: <?php echo esc_attr($settings['text_color']); ?>;">
        
        <?php if ($settings['close_button']) : ?>
        <button class="popup-close" aria-label="Cerrar">
            <span>&times;</span>
        </button>
        <?php endif; ?>
        
        <div class="popup-content">
            
            <?php if (!empty($producto['image'])) : ?>
            <div class="popup-image">
                <img src="<?php echo esc_url($producto['image']); ?>" 
                     alt="<?php echo esc_attr($producto['title']); ?>">
            </div>
            <?php endif; ?>
            
            <div class="popup-header">
                <h2 class="popup-title"><?php echo esc_html($settings['title']); ?></h2>
                
                <?php if (!empty($producto['discount']) && $producto['discount'] > 0) : ?>
                <div class="popup-discount-badge">
                    -<?php echo esc_html($producto['discount']); ?>%
                </div>
                <?php endif; ?>
            </div>
            
            <div class="popup-product">
                <h3 class="product-title"><?php echo esc_html($producto['title']); ?></h3>
                
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
                    <?php echo esc_html($settings['button_text']); ?>
                </a>
            </div>
            <?php endif; ?>
            
            <?php if ($settings['show_close_after']) : ?>
            <div class="popup-footer">
                <button class="popup-close-link"><?php _e('Cerrar', 'ygb-ofertas'); ?></button>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>