<?php
/**
 * Course Manager Class
 * Handles course data operations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if class already exists to prevent conflicts
if (!class_exists('CIM_Course_Manager')) {
    class CIM_Course_Manager {
        
        /**
         * Get course by ID
         */
        public function get_course($id) {
            global $wpdb;
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}cim_course_info WHERE id = %d",
                $id
            ));
        }
        
        /**
         * Get course by LifterLMS ID
         */
        public function get_course_by_lifterlms_id($lifterlms_id) {
            global $wpdb;
            return $wpdb->get_row($wpdb->prepare(
                "SELECT c.* FROM {$wpdb->prefix}cim_course_info c
                JOIN {$wpdb->prefix}cim_course_matching m ON c.id = m.cim_course_id
                WHERE m.lifterlms_course_id = %d",
                $lifterlms_id
            ));
        }
        
        /**
         * Get course by edition
         */
        public function get_course_by_edition($edition) {
            global $wpdb;
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}cim_course_info WHERE edition = %s",
                $edition
            ));
        }
        
        /**
         * Update course
         */
        public function update_course($id, $data) {
            global $wpdb;
            
            // Get current data for history
            $current = $this->get_course($id);
            if (!$current) {
                return false;
            }
            
            // Track changes
            $history_table = $wpdb->prefix . 'cim_course_history';
            foreach ($data as $field => $value) {
                if (isset($current->$field) && $current->$field != $value) {
                    $wpdb->insert($history_table, array(
                        'course_id' => $id,
                        'edition' => $current->edition,
                        'change_type' => 'manual_update',
                        'field_name' => $field,
                        'old_value' => $current->$field,
                        'new_value' => $value,
                        'changed_by' => get_current_user_id()
                    ));
                }
            }
            
            // Update course
            return $wpdb->update(
                $wpdb->prefix . 'cim_course_info',
                $data,
                array('id' => $id)
            );
        }
        
        /**
         * Search courses
         */
        public function search_courses($args = array()) {
            global $wpdb;
            
            $defaults = array(
                'search' => '',
                'matched' => '',
                'certification' => '',
                'limit' => 50,
                'offset' => 0
            );
            
            $args = wp_parse_args($args, $defaults);
            
            $where = array('1=1');
            
            // Search filter
            if (!empty($args['search'])) {
                $search = '%' . $wpdb->esc_like($args['search']) . '%';
                $where[] = $wpdb->prepare(
                    "(four_digit LIKE %s OR edition LIKE %s OR course_title LIKE %s)",
                    $search, $search, $search
                );
            }
            
            // Matched filter
            if ($args['matched'] === 'matched') {
                $where[] = "m.id IS NOT NULL";
            } elseif ($args['matched'] === 'unmatched') {
                $where[] = "m.id IS NULL";
            }
            
            // Certification filter
            if (!empty($args['certification'])) {
                switch ($args['certification']) {
                    case 'cfp':
                        $where[] = "c.cfp_credits > 0";
                        break;
                    case 'cpa':
                        $where[] = "c.cpa_credits > 0";
                        break;
                    case 'ea':
                        $where[] = "c.ea_otrp_credits > 0";
                        break;
                    case 'cdfa':
                        $where[] = "c.cdfa_credits > 0";
                        break;
                }
            }
            
            $where_clause = implode(' AND ', $where);
            
            $query = "
                SELECT c.*, m.lifterlms_course_id 
                FROM {$wpdb->prefix}cim_course_info c
                LEFT JOIN {$wpdb->prefix}cim_course_matching m ON c.id = m.cim_course_id
                WHERE $where_clause
                ORDER BY c.four_digit, c.edition DESC
                LIMIT %d OFFSET %d
            ";
            
            return $wpdb->get_results($wpdb->prepare($query, $args['limit'], $args['offset']));
        }
        
        /**
         * Get course credits formatted
         */
        public function get_formatted_credits($course) {
            $credits = array();
            
            if ($course->cfp_credits > 0) {
                $credits[] = 'CFP: ' . $course->cfp_credits;
            }
            if ($course->cpa_credits > 0) {
                $credits[] = 'CPA: ' . $course->cpa_credits;
            }
            if ($course->ea_otrp_credits > 0) {
                $credits[] = 'EA/OTRP: ' . $course->ea_otrp_credits;
            }
            if ($course->erpa_credits > 0) {
                $credits[] = 'ERPA: ' . $course->erpa_credits;
            }
            if ($course->cdfa_credits > 0) {
                $credits[] = 'CDFA: ' . $course->cdfa_credits;
            }
            if ($course->iar_credits > 0) {
                $credits[] = 'IAR: ' . $course->iar_credits;
            }
            
            return implode(' | ', $credits);
        }
    }
} 