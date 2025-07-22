<?php
/**
 * Plugin Name: Course Information Manager
 * Plugin URI: https://github.com/andyfreed/master-course-list
 * Description: Manages course information, credits, and version history separately from LifterLMS. Handles CSV imports, course matching, and certification tracking for CPAs, CFPs, EAs, and other professionals.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Author: Andrew Freed
 * Author URI: https://github.com/andyfreed
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: course-info-manager
 * Domain Path: /languages
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if WordPress is loaded
if (!function_exists('add_action')) {
    exit;
}

// Define plugin constants
define('CIM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CIM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CIM_VERSION', '1.0.0');

// Plugin activation hook
register_activation_hook(__FILE__, 'cim_activate_plugin');
function cim_activate_plugin() {
    try {
        cim_create_database_tables();
        flush_rewrite_rules();
    } catch (Exception $e) {
        error_log('CIM Plugin Activation Error: ' . $e->getMessage());
    }
}

// Plugin deactivation hook
register_deactivation_hook(__FILE__, 'cim_deactivate_plugin');
function cim_deactivate_plugin() {
    flush_rewrite_rules();
}

// Create database tables (separate from LifterLMS)
function cim_create_database_tables() {
    global $wpdb;
    
    // Check if we can access the database
    if (!$wpdb) {
        throw new Exception('Database connection not available');
    }
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Ensure upgrade.php is available
    if (!function_exists('dbDelta')) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    }
    
    // Main course info table
    $table_name = $wpdb->prefix . 'cim_course_info';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        four_digit varchar(10) NOT NULL,
        edition varchar(20) NOT NULL,
        current_year varchar(20) NOT NULL,
        course_title text NOT NULL,
        pages_2025 int(11),
        notes text,
        santucci_title text,
        cfp_credits decimal(5,2),
        cpa_credits decimal(5,2),
        ea_otrp_credits decimal(5,2),
        erpa_credits decimal(5,2),
        cdfa_credits decimal(5,2),
        cima_cpwa_rma_credits decimal(5,2),
        iar_credits decimal(5,2),
        iar_number varchar(50),
        price_pdf decimal(10,2),
        price_print decimal(10,2),
        price_per_pdf_cpe decimal(10,2),
        annual_update date,
        exam_changes varchar(10),
        subs_updates varchar(10),
        cfp_board_number varchar(50),
        ea_number varchar(50),
        erpa_number varchar(50),
        cfp_ce_calc decimal(5,2),
        cpa_cpe_calc decimal(5,2),
        cfp_words int(11),
        cpa_words int(11),
        rev_q int(11),
        exam_q int(11),
        min_exam_q int(11),
        iar_words int(11),
        iar_q int(11),
        cfp_subject varchar(100),
        cpa_subject varchar(100),
        tx_subject_code varchar(20),
        previous_cfp_cr decimal(5,2),
        previous_cpa_cr decimal(5,2),
        previous_ea_otrp_erp_cr decimal(5,2),
        previous_cdfa_cr decimal(5,2),
        notes_1 text,
        notes_2 text,
        lifterlms_course_id bigint(20),
        last_updated timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_course_edition (four_digit, edition),
        KEY idx_four_digit (four_digit),
        KEY idx_lifterlms_id (lifterlms_course_id)
    ) $charset_collate;";
    
    $result = dbDelta($sql);
    
    // Version history table
    $history_table = $wpdb->prefix . 'cim_course_history';
    $sql_history = "CREATE TABLE IF NOT EXISTS $history_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        course_id bigint(20) NOT NULL,
        edition varchar(20) NOT NULL,
        change_type varchar(50) NOT NULL,
        field_name varchar(100),
        old_value text,
        new_value text,
        change_notes text,
        changed_by bigint(20),
        change_date timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_course (course_id),
        KEY idx_edition (edition),
        KEY idx_date (change_date)
    ) $charset_collate;";
    
    $result_history = dbDelta($sql_history);
    
    // Course matching table (links to LifterLMS courses)
    $matching_table = $wpdb->prefix . 'cim_course_matching';
    $sql_matching = "CREATE TABLE IF NOT EXISTS $matching_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        cim_course_id bigint(20) NOT NULL,
        lifterlms_course_id bigint(20) NOT NULL,
        match_type varchar(50) NOT NULL,
        match_confidence int(3),
        matched_by bigint(20),
        match_date timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_match (cim_course_id, lifterlms_course_id),
        KEY idx_cim_course (cim_course_id),
        KEY idx_lifterlms_course (lifterlms_course_id)
    ) $charset_collate;";
    
    $result_matching = dbDelta($sql_matching);
    
    // Log any errors for debugging
    if (!empty($wpdb->last_error)) {
        error_log('CIM Plugin: Database table creation error: ' . $wpdb->last_error);
    }
}

// Include required files with error handling
function cim_include_files() {
    $required_files = array(
        CIM_PLUGIN_PATH . 'includes/class-course-manager.php',
        CIM_PLUGIN_PATH . 'includes/class-csv-importer.php',
        CIM_PLUGIN_PATH . 'includes/class-course-matcher.php',
        CIM_PLUGIN_PATH . 'includes/admin/admin-menu.php',
        CIM_PLUGIN_PATH . 'includes/admin/admin-ajax.php'
    );

    foreach ($required_files as $file) {
        if (file_exists($file)) {
            require_once $file;
        } else {
            error_log('CIM Plugin: Required file not found: ' . $file);
        }
    }
}

// Initialize the plugin
add_action('init', 'cim_init');
function cim_init() {
    // Include files
    cim_include_files();
    
    // Load text domain for translations
    if (function_exists('load_plugin_textdomain')) {
        load_plugin_textdomain('course-info-manager', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
}

// Add admin styles and scripts
add_action('admin_enqueue_scripts', 'cim_admin_enqueue_scripts');
function cim_admin_enqueue_scripts($hook) {
    if (strpos($hook, 'course-info-manager') !== false) {
        wp_enqueue_style('cim-admin-style', CIM_PLUGIN_URL . 'assets/css/admin-style.css', array(), CIM_VERSION);
        wp_enqueue_script('cim-admin-script', CIM_PLUGIN_URL . 'assets/js/admin-script.js', array('jquery'), CIM_VERSION, true);
        wp_localize_script('cim-admin-script', 'cim_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cim_ajax_nonce')
        ));
    }
}

// Hook for displaying course info on LifterLMS course pages (only if matched)
add_action('lifterlms_single_course_after_summary', 'cim_display_course_info', 15);
function cim_display_course_info() {
    global $post;
    
    // Check if LifterLMS is active and we have a valid post
    if (!function_exists('lifterlms_get_post') || !$post) {
        return;
    }
    
    // Get course info if matched
    $course_manager = new CIM_Course_Manager();
    $course_info = $course_manager->get_course_by_lifterlms_id($post->ID);
    
    if ($course_info) {
        // Load template for displaying course credits and info
        $template_path = CIM_PLUGIN_PATH . 'templates/course-info-display.php';
        if (file_exists($template_path)) {
            include $template_path;
        }
    }
} 