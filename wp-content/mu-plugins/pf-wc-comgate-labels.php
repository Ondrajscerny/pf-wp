<?php
/**
 * Plugin Name: PF – WC ComGate Labels (Edenred + Up)
 * Description: Upraví název a popis platební metody ComGate pro jasnou komunikaci Edenred a Up eBenefity.
 * Author: Private Fitness
 * Version: 1.1.0
 */
if (!defined('ABSPATH')) exit;

add_filter('woocommerce_gateway_title', function ($title, $gateway_id) {
    if (strpos($gateway_id, 'comgate') !== false) {
        $title = 'Platba kartou (ComGate) – přijímáme i benefitní karty Edenred & Up';
    }
    return $title;
}, 10, 2);

add_filter('woocommerce_gateway_description', function ($description, $gateway_id) {
    if (strpos($gateway_id, 'comgate') !== false) {
        $description = 'Zaplaťte bezpečně on-line. Přes bránu ComGate podporujeme také benefitní karty <strong>Edenred</strong> a <strong>Up eBenefity</strong> (včetně FKSP). Po potvrzení objednávky budete přesměrováni na zabezpečenou platební stránku.';
    }
    return $description;
}, 10, 2);
