<?php
/**
 * Plugin Name: PF – Pay Footer (Edenred + Up)
 * Description: Info řádek v patičce o akceptaci Edenred a Up přes ComGate + loga.
 * Author: Private Fitness
 * Version: 1.1.0
 */
if (!defined('ABSPATH')) exit;

add_action('wp_footer', function () {
    $base_url = trailingslashit( plugin_dir_url(__FILE__) . 'pf-pay-footer-assets' );

    $logos = [
        ['file' => 'edenred.png', 'alt' => 'Edenred'],     // už máš PNG
        ['file' => 'up.svg',      'alt' => 'Up eBenefity'],// přidáš soubor
        ['file' => 'comgate.svg', 'alt' => 'ComGate'],
        ['file' => 'visa.svg',    'alt' => 'VISA'],
        ['file' => 'mastercard.svg', 'alt' => 'Mastercard'],
    ];
    ?>
    <div class="pf-pay-footer" style="margin:24px 0 12px; text-align:center; font-size:14px; line-height:1.45;">
        <p style="margin:0 0 8px;">
            <strong>Přijímáme on-line benefity:</strong> Edenred Card a Up eBenefity (včetně FKSP) přes <strong>ComGate</strong>.
            Platíte stejně jako běžnou platební kartou.
        </p>
        <p class="pf-pay-logos" style="margin:0;">
            <?php foreach ($logos as $logo):
                $src = $base_url . $logo['file']; ?>
                <img src="<?php echo esc_url($src); ?>"
                     alt="<?php echo esc_attr($logo['alt']); ?>"
                     height="28"
                     loading="lazy"
                     style="vertical-align:middle; margin:0 6px 6px;">
            <?php endforeach; ?>
        </p>
        <p style="opacity:.7; font-size:12px; margin:6px 0 0;">Loga jsou ochranné známky příslušných vlastníků.</p>
    </div>
    <?php
}, 99);
