<?php
/**
 * Admin AJAX handlers
 */

// Match course AJAX handler
add_action('wp_ajax_cim_match_course', 'cim_ajax_match_course');
function cim_ajax_match_course() {
    // Check nonce
    if (!wp_verify_nonce($_POST['nonce'], 'cim_ajax_nonce')) {
        error_log('CIM: Nonce verification failed');
        wp_send_json_error('Security check failed');
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        error_log('CIM: Insufficient permissions');
        wp_send_json_error('Insufficient permissions');
    }
    
    $cim_course_id = intval($_POST['cim_course_id']);
    $lifterlms_course_id = intval($_POST['lifterlms_course_id']);
    
    error_log('CIM: Attempting to match course ' . $cim_course_id . ' with LifterLMS course ' . $lifterlms_course_id);
    
    if (!$cim_course_id || !$lifterlms_course_id) {
        error_log('CIM: Invalid course IDs - CIM: ' . $cim_course_id . ', LLMS: ' . $lifterlms_course_id);
        wp_send_json_error('Invalid course IDs');
    }
    
    $matcher = new CIM_Course_Matcher();
    $result = $matcher->create_match($cim_course_id, $lifterlms_course_id);
    
    error_log('CIM: Match result: ' . ($result ? 'success' : 'failed'));
    
    if ($result) {
        wp_send_json_success('Courses matched successfully');
    } else {
        wp_send_json_error('Failed to match courses');
    }
}

// Search courses AJAX handler
add_action('wp_ajax_cim_search_courses', 'cim_ajax_search_courses');
function cim_ajax_search_courses() {
    // Check nonce
    if (!wp_verify_nonce($_POST['nonce'], 'cim_ajax_nonce')) {
        wp_die('Security check failed');
    }
    
    $args = array(
        'search' => sanitize_text_field($_POST['search']),
        'matched' => sanitize_text_field($_POST['matched']),
        'certification' => sanitize_text_field($_POST['certification'])
    );
    
    $manager = new CIM_Course_Manager();
    $courses = $manager->search_courses($args);
    
    ob_start();
    foreach ($courses as $course) {
        ?>
        <tr>
            <td><?php echo esc_html($course->four_digit); ?></td>
            <td><?php echo esc_html($course->edition); ?></td>
            <td><?php echo esc_html($course->course_title); ?></td>
            <td><?php echo $course->cfp_credits ?: '-'; ?></td>
            <td><?php echo $course->cpa_credits ?: '-'; ?></td>
            <td><?php echo $course->ea_otrp_credits ?: '-'; ?></td>
            <td><?php echo $course->cdfa_credits ?: '-'; ?></td>
            <td>
                <?php if ($course->lifterlms_course_id): ?>
                    <span class="dashicons dashicons-yes" style="color: green;"></span>
                <?php else: ?>
                    <span class="dashicons dashicons-no" style="color: red;"></span>
                <?php endif; ?>
            </td>
            <td>
                <a href="#" class="cim-edit-course" data-id="<?php echo $course->id; ?>">Edit</a> |
                <a href="<?php echo admin_url('admin.php?page=cim-history&course_id=' . $course->id); ?>">History</a>
            </td>
        </tr>
        <?php
    }
    $html = ob_get_clean();
    
    wp_send_json_success($html);
}

// Update course AJAX handler
add_action('wp_ajax_cim_update_course', 'cim_ajax_update_course');
function cim_ajax_update_course() {
    // Check nonce
    if (!wp_verify_nonce($_POST['nonce'], 'cim_ajax_nonce')) {
        wp_die('Security check failed');
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    $course_id = intval($_POST['course_id']);
    if (!$course_id) {
        wp_send_json_error('Invalid course ID');
    }
    
    // Prepare data
    $data = array();
    $fields = array(
        'cfp_credits', 'cpa_credits', 'ea_otrp_credits', 
        'erpa_credits', 'cdfa_credits', 'iar_credits',
        'price_pdf', 'price_print', 'notes'
    );
    
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $data[$field] = sanitize_text_field($_POST[$field]);
        }
    }
    
    $manager = new CIM_Course_Manager();
    $result = $manager->update_course($course_id, $data);
    
    if ($result !== false) {
        wp_send_json_success('Course updated successfully');
    } else {
        wp_send_json_error('Failed to update course');
    }
}

// Auto-match courses AJAX handler
add_action('wp_ajax_cim_auto_match', 'cim_ajax_auto_match');
function cim_ajax_auto_match() {
    // Check nonce
    if (!wp_verify_nonce($_POST['nonce'], 'cim_ajax_nonce')) {
        wp_die('Security check failed');
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    $matcher = new CIM_Course_Matcher();
    $matched_count = $matcher->auto_match_all();
    
    wp_send_json_success(sprintf('%d courses automatically matched', $matched_count));
} 