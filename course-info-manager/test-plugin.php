<?php
/**
 * Test file to verify plugin compatibility
 * This file can be removed after successful installation
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Simple test function
function cim_test_function() {
    return 'CIM Plugin Test: OK';
}

// Test if basic WordPress functions are available
if (function_exists('add_action')) {
    add_action('init', function() {
        error_log('CIM Plugin: Test function loaded successfully');
    });
} 