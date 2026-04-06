<?php
/**
 * API Master - Logs Directory Index
 * 
 * Dizin listelemesini engelle
 */

if (!defined('ABSPATH')) {
    exit;
}

// Sessizce dur - hiçbir şey gösterme
http_response_code(403);
echo "Access denied.";