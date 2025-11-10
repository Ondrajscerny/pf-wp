<?php
/**
 * Plugin Name: PF – Pay Footer (Edenred)
 * Description: Přidá do patičky informaci o akceptaci Edenred přes ComGate + loga (Edenred, ComGate, VISA, Mastercard).
 * Author: Private Fitness
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) exit;

add_action('wp_footer', function () {
    $base_url = trailingslashit( plugin_dir_url(__FILE__) . 'pf-pay-footer-assets' );

    $logos = [
        ['file' => 'edenred.png',     'alt' => 'Edenred'],
        ['file' => 'comgate.svg',     'alt' => 'ComGate'],
        ['file' => 'visa.svg',        'alt' => 'VISA'],
        ['file' => 'mastercard.svg',  'alt' => 'Mastercard'],
    ];
    ?>
    <div class="pf-pay-footer" style="margin:24px 0 12px; text-align:center; font-size:14px; line-height:1.4;">
        <p><strong>Přijímáme on-line benefity:</strong> Edenred Card (včetně FKSP). Platby zpracovává ComGate – stejně jako běžné platební karty.</p>
        <p class="pf-pay-logos" style="margin-top:8px;">
            <?php foreach ($logos as $logo):
                $src = $base_url . $logo['file']; ?>
                <img src="<?php echo esc_url($src); ?>"
                     alt="<?php echo esc_attr($logo['alt']); ?>"
                     height="28"
                     loading="lazy"
                     style="vertical-align:middle; margin:0 6px;">
            <?php endforeach; ?>
        </p>
        <p style="opacity:.7; font-size:12px; margin-top:6px;">Loga jsou ochranné známky příslušných vlastníků.</p>
    </div>
    <?php
}, 99);
