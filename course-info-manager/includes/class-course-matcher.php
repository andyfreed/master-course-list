<?php
/**
 * Course Matcher Class
 * Handles matching imported courses with LifterLMS courses
 */

class CIM_Course_Matcher {
    
    /**
     * Find potential matches for a course
     */
    public function find_matches($course_id) {
        global $wpdb;
        
        // Get course info
        $course = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cim_course_info WHERE id = %d",
            $course_id
        ));
        
        if (!$course) {
            return array();
        }
        
        $matches = array();
        
        // Method 1: Match by 4-digit code in title or SKU
        $code_matches = $this->match_by_code($course->four_digit, $course->edition);
        foreach ($code_matches as $match) {
            $match['match_type'] = 'code';
            $matches[] = $match;
        }
        
        // Method 2: Match by title similarity
        $title_matches = $this->match_by_title($course->course_title);
        foreach ($title_matches as $match) {
            // Don't duplicate if already found by code
            $already_found = false;
            foreach ($matches as $existing) {
                if ($existing['course_id'] == $match['course_id']) {
                    $already_found = true;
                    break;
                }
            }
            if (!$already_found) {
                $match['match_type'] = 'title';
                $matches[] = $match;
            }
        }
        
        // Sort by confidence
        usort($matches, function($a, $b) {
            return $b['confidence'] - $a['confidence'];
        });
        
        return $matches;
    }
    
    /**
     * Match by course code
     */
    private function match_by_code($four_digit, $edition) {
        global $wpdb;
        
        $matches = array();
        
        // Search in FLMS courses by SKU (which should contain the course code)
        $sku_results = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, p.post_title, pm.meta_value as sku
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'flms-courses' 
            AND p.post_status = 'publish'
            AND pm.meta_key = '_sku'
            AND (pm.meta_value LIKE %s OR pm.meta_value = %s)
        ", '%' . $four_digit . '%', $edition));
        
        foreach ($sku_results as $result) {
            $confidence = 50;
            
            // Higher confidence if exact match
            if ($result->sku == $four_digit || $result->sku == $edition) {
                $confidence = 95;
            } elseif (strpos($result->sku, $four_digit) !== false) {
                $confidence = 80;
            }
            
            $matches[] = array(
                'course_id' => $result->ID,
                'course_title' => $result->post_title,
                'confidence' => $confidence,
                'match_reason' => 'SKU match: ' . $result->sku
            );
        }
        
        // Search in post title for FLMS courses
        $title_results = $wpdb->get_results($wpdb->prepare("
            SELECT ID, post_title 
            FROM {$wpdb->posts}
            WHERE post_type = 'flms-courses' 
            AND post_status = 'publish'
            AND (post_title LIKE %s OR post_title LIKE %s)
        ", '%' . $four_digit . '%', '%' . $edition . '%'));
        
        foreach ($title_results as $result) {
            $confidence = 50;
            
            // Higher confidence if exact edition match
            if (strpos($result->post_title, $edition) !== false) {
                $confidence = 85;
            } elseif (strpos($result->post_title, $four_digit) !== false) {
                $confidence = 70;
            }
            
            $matches[] = array(
                'course_id' => $result->ID,
                'course_title' => $result->post_title,
                'confidence' => $confidence,
                'match_reason' => 'Title match'
            );
        }
        
        // Search in other post types as fallback
        $other_results = $wpdb->get_results($wpdb->prepare("
            SELECT ID, post_title 
            FROM {$wpdb->posts}
            WHERE post_type IN ('course', 'llms_course', 'sfwd-courses', 'courses', 'mnc-courses')
            AND post_status = 'publish'
            AND (post_title LIKE %s OR post_title LIKE %s)
        ", '%' . $four_digit . '%', '%' . $edition . '%'));
        
        foreach ($other_results as $result) {
            $confidence = 40; // Lower confidence for non-FLMS courses
            
            if (strpos($result->post_title, $edition) !== false) {
                $confidence = 75;
            } elseif (strpos($result->post_title, $four_digit) !== false) {
                $confidence = 60;
            }
            
            $matches[] = array(
                'course_id' => $result->ID,
                'course_title' => $result->post_title,
                'confidence' => $confidence,
                'match_reason' => 'Other post type match'
            );
        }
        
        return $matches;
    }
    
    /**
     * Match by title similarity
     */
    private function match_by_title($title) {
        global $wpdb;
        
        $matches = array();
        
        // Clean title for comparison
        $clean_title = $this->clean_title($title);
        
        // Get all courses
        $courses = $wpdb->get_results("
            SELECT ID, post_title 
            FROM {$wpdb->posts}
            WHERE post_type = 'course' 
            AND post_status = 'publish'
        ");
        
        foreach ($courses as $course) {
            $clean_course_title = $this->clean_title($course->post_title);
            
            // Calculate similarity
            $similarity = 0;
            similar_text($clean_title, $clean_course_title, $similarity);
            
            // Also check Levenshtein distance for short titles
            $lev_distance = levenshtein(
                substr($clean_title, 0, 255), 
                substr($clean_course_title, 0, 255)
            );
            
            // Only include if similarity is high enough
            if ($similarity > 70 || $lev_distance < 5) {
                $confidence = min($similarity, 95); // Cap at 95 for title matches
                
                $matches[] = array(
                    'course_id' => $course->ID,
                    'course_title' => $course->post_title,
                    'confidence' => $confidence
                );
            }
        }
        
        return $matches;
    }
    
    /**
     * Clean title for comparison
     */
    private function clean_title($title) {
        // Remove special characters and extra spaces
        $title = preg_replace('/[^a-zA-Z0-9\s]/', '', $title);
        $title = preg_replace('/\s+/', ' ', $title);
        return strtolower(trim($title));
    }
    
    /**
     * Create a match between courses
     */
    public function create_match($cim_course_id, $lifterlms_course_id, $match_type = 'manual', $confidence = 100) {
        global $wpdb;
        
        // Check if match already exists
        $existing = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM {$wpdb->prefix}cim_course_matching
            WHERE cim_course_id = %d AND lifterlms_course_id = %d
        ", $cim_course_id, $lifterlms_course_id));
        
        if ($existing) {
            return false;
        }
        
        // Create match
        $result = $wpdb->insert(
            $wpdb->prefix . 'cim_course_matching',
            array(
                'cim_course_id' => $cim_course_id,
                'lifterlms_course_id' => $lifterlms_course_id,
                'match_type' => $match_type,
                'match_confidence' => $confidence,
                'matched_by' => get_current_user_id()
            )
        );
        
        // Also update the main course table for quick access
        if ($result) {
            $wpdb->update(
                $wpdb->prefix . 'cim_course_info',
                array('lifterlms_course_id' => $lifterlms_course_id),
                array('id' => $cim_course_id)
            );
        }
        
        return $result !== false;
    }
    
    /**
     * Remove a match
     */
    public function remove_match($cim_course_id) {
        global $wpdb;
        
        // Remove from matching table
        $wpdb->delete(
            $wpdb->prefix . 'cim_course_matching',
            array('cim_course_id' => $cim_course_id)
        );
        
        // Update main course table
        $wpdb->update(
            $wpdb->prefix . 'cim_course_info',
            array('lifterlms_course_id' => null),
            array('id' => $cim_course_id)
        );
        
        return true;
    }
    
    /**
     * Get active courses only (filter out inactive/old versions)
     */
    public function get_active_courses() {
        global $wpdb;
        
        $courses = array();
        
        // Get FLMS courses with active version meta
        $active_courses = $wpdb->get_results("
            SELECT p.ID, p.post_title, p.post_content, p.post_status,
                   pm.meta_value as active_version
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'flms_course_active_version'
            WHERE p.post_type = 'flms-courses' 
            AND p.post_status = 'publish'
            ORDER BY p.post_title
        ");
        
        foreach ($active_courses as $course) {
            // Get course meta data
            $course_meta = $wpdb->get_results($wpdb->prepare("
                SELECT meta_key, meta_value
                FROM {$wpdb->postmeta}
                WHERE post_id = %d
                AND meta_key IN ('_sku', 'flms_woocommerce_course_id', 'cpa', '_cfp', 'cpa_cpe_calc', 'cfp_word_count', 'cpa_word_count', 'texas_subject_code', 'bhfe_archived_course')
            ", $course->ID));
            
            $meta_data = array();
            foreach ($course_meta as $meta) {
                $meta_data[$meta->meta_key] = $meta->meta_value;
            }
            
            // Skip archived courses
            if (isset($meta_data['bhfe_archived_course']) && $meta_data['bhfe_archived_course'] == '1') {
                continue;
            }
            
            $courses[] = array(
                'id' => $course->ID,
                'title' => $course->post_title,
                'content' => $course->post_content,
                'type' => 'flms_course',
                'meta' => $meta_data,
                'sku' => isset($meta_data['_sku']) ? $meta_data['_sku'] : '',
                'woo_id' => isset($meta_data['flms_woocommerce_course_id']) ? $meta_data['flms_woocommerce_course_id'] : '',
                'active_version' => $course->active_version,
                'is_active' => !isset($meta_data['bhfe_archived_course']) || $meta_data['bhfe_archived_course'] != '1'
            );
        }
        
        return $courses;
    }
    
    /**
     * Get all LifterLMS courses for matching
     */
    public function get_lifterlms_courses() {
        global $wpdb;
        
        $courses = array();
        
        // Method 1: Check for FLMS courses (primary method for this setup)
        $flms_courses = $wpdb->get_results("
            SELECT ID, post_title, post_content, post_status
            FROM {$wpdb->posts}
            WHERE post_type = 'flms-courses' 
            AND post_status = 'publish'
            ORDER BY post_title
        ");
        
        if (!empty($flms_courses)) {
            foreach ($flms_courses as $course) {
                // Get course meta data
                $course_meta = $wpdb->get_results($wpdb->prepare("
                    SELECT meta_key, meta_value
                    FROM {$wpdb->postmeta}
                    WHERE post_id = %d
                    AND meta_key IN ('_sku', 'flms_woocommerce_course_id', 'cpa', '_cfp', 'cpa_cpe_calc', 'cfp_word_count', 'cpa_word_count', 'texas_subject_code')
                ", $course->ID));
                
                $meta_data = array();
                foreach ($course_meta as $meta) {
                    $meta_data[$meta->meta_key] = $meta->meta_value;
                }
                
                $courses[] = array(
                    'id' => $course->ID,
                    'title' => $course->post_title,
                    'content' => $course->post_content,
                    'type' => 'flms_course',
                    'meta' => $meta_data,
                    'sku' => isset($meta_data['_sku']) ? $meta_data['_sku'] : '',
                    'woo_id' => isset($meta_data['flms_woocommerce_course_id']) ? $meta_data['flms_woocommerce_course_id'] : ''
                );
            }
        }
        
        // Method 2: Check for other course post types as fallback
        $other_courses = $wpdb->get_results("
            SELECT ID, post_title, post_content, post_type
            FROM {$wpdb->posts}
            WHERE post_type IN ('course', 'llms_course', 'sfwd-courses', 'courses', 'mnc-courses')
            AND post_status = 'publish'
            ORDER BY post_title
        ");
        
        foreach ($other_courses as $course) {
            // Avoid duplicates
            $exists = false;
            foreach ($courses as $existing) {
                if ($existing['id'] == $course->ID) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                $courses[] = array(
                    'id' => $course->ID,
                    'title' => $course->post_title,
                    'content' => $course->post_content,
                    'type' => $course->post_type
                );
            }
        }
        
        // Method 3: Check for courses with specific meta fields
        $meta_courses = $wpdb->get_results("
            SELECT DISTINCT p.ID, p.post_title, p.post_content, p.post_type, pm.meta_key, pm.meta_value
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_status = 'publish'
            AND pm.meta_key IN ('_course_code', 'course_code', '_sku', 'course_credits', 'cfp_credits', 'cpa_credits', 'flms_woocommerce_course_id')
            ORDER BY p.post_title
        ");
        
        foreach ($meta_courses as $course) {
            // Avoid duplicates
            $exists = false;
            foreach ($courses as $existing) {
                if ($existing['id'] == $course->ID) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                $courses[] = array(
                    'id' => $course->ID,
                    'title' => $course->post_title,
                    'content' => $course->post_content,
                    'meta_key' => $course->meta_key,
                    'meta_value' => $course->meta_value,
                    'type' => $course->post_type
                );
            }
        }
        
        return $courses;
    }
    
    /**
     * Auto-match courses with high confidence
     */
    public function auto_match_all($min_confidence = 95) {
        global $wpdb;
        
        $matched_count = 0;
        
        // Get all unmatched courses
        $unmatched = $wpdb->get_results("
            SELECT c.id 
            FROM {$wpdb->prefix}cim_course_info c
            LEFT JOIN {$wpdb->prefix}cim_course_matching m ON c.id = m.cim_course_id
            WHERE m.id IS NULL
        ");
        
        foreach ($unmatched as $course) {
            $matches = $this->find_matches($course->id);
            
            // Auto-match if confidence is high enough and only one match
            if (count($matches) == 1 && $matches[0]['confidence'] >= $min_confidence) {
                if ($this->create_match(
                    $course->id, 
                    $matches[0]['course_id'], 
                    'auto_' . $matches[0]['match_type'],
                    $matches[0]['confidence']
                )) {
                    $matched_count++;
                }
            }
        }
        
        return $matched_count;
    }
} 