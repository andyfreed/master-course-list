<?php
/**
 * CSV Importer Class
 * Handles importing course data from CSV file
 */

class CIM_CSV_Importer {
    
    private $errors = array();
    private $imported_count = 0;
    private $skipped_count = 0;
    
    /**
     * Import CSV file
     */
    public function import_csv($file_path) {
        if (!file_exists($file_path)) {
            $this->errors[] = 'CSV file not found.';
            return false;
        }
        
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            $this->errors[] = 'Unable to open CSV file.';
            return false;
        }
        
        // Get headers
        $headers = fgetcsv($handle);
        $headers = array_map('trim', $headers);
        
        // Map CSV headers to database fields
        $field_mapping = $this->get_field_mapping();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'cim_course_info';
        
        // Begin transaction for safe import
        $wpdb->query('START TRANSACTION');
        
        try {
            while (($data = fgetcsv($handle)) !== FALSE) {
                // Skip empty rows
                if (empty(array_filter($data))) {
                    continue;
                }
                
                $course_data = array();
                
                // Map CSV columns to database fields
                foreach ($headers as $index => $header) {
                    if (isset($field_mapping[$header]) && isset($data[$index])) {
                        $field = $field_mapping[$header];
                        $value = trim($data[$index]);
                        
                        // Clean and validate data
                        $course_data[$field] = $this->clean_value($field, $value);
                    }
                }
                
                // Skip if no four_digit code
                if (empty($course_data['four_digit'])) {
                    $this->skipped_count++;
                    continue;
                }
                
                // Check if course already exists
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM $table_name WHERE edition = %s",
                    $course_data['edition']
                ));
                
                if ($existing) {
                    // Update existing record and track changes
                    $this->update_course_with_history($existing->id, $course_data);
                } else {
                    // Insert new record
                    $wpdb->insert($table_name, $course_data);
                    
                    if ($wpdb->last_error) {
                        throw new Exception('Database insert error: ' . $wpdb->last_error);
                    }
                }
                
                $this->imported_count++;
            }
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
        } catch (Exception $e) {
            // Rollback on error
            $wpdb->query('ROLLBACK');
            $this->errors[] = $e->getMessage();
            fclose($handle);
            return false;
        }
        
        fclose($handle);
        return true;
    }
    
    /**
     * Update course with history tracking
     */
    private function update_course_with_history($course_id, $new_data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cim_course_info';
        $history_table = $wpdb->prefix . 'cim_course_history';
        
        // Get current data
        $current_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $course_id
        ), ARRAY_A);
        
        // Track changes
        $changes = array();
        foreach ($new_data as $field => $new_value) {
            if (isset($current_data[$field]) && $current_data[$field] != $new_value) {
                $changes[] = array(
                    'course_id' => $course_id,
                    'edition' => $new_data['edition'],
                    'change_type' => 'update',
                    'field_name' => $field,
                    'old_value' => $current_data[$field],
                    'new_value' => $new_value,
                    'changed_by' => get_current_user_id()
                );
            }
        }
        
        // Insert history records
        foreach ($changes as $change) {
            $wpdb->insert($history_table, $change);
        }
        
        // Update course
        $wpdb->update($table_name, $new_data, array('id' => $course_id));
    }
    
    /**
     * Get field mapping from CSV headers to database columns
     */
    private function get_field_mapping() {
        return array(
            'Four Digit' => 'four_digit',
            'Previous Edition' => 'edition',
            'Current Year' => 'current_year',
            'Course (Shaded by author)' => 'course_title',
            '2025 Pages' => 'pages_2025',
            'NOTES' => 'notes',
            'Santucci Title:' => 'santucci_title',
            'CFP' => 'cfp_credits',
            'CPA' => 'cpa_credits',
            'EA OTRP' => 'ea_otrp_credits',
            'ERPA' => 'erpa_credits',
            'CDFA' => 'cdfa_credits',
            'CIMA CPWA RMA' => 'cima_cpwa_rma_credits',
            'IAR' => 'iar_credits',
            'IAR #' => 'iar_number',
            '$ PDF or Exam Only' => 'price_pdf',
            '$ Print' => 'price_print',
            '$ per PDF CPE' => 'price_per_pdf_cpe',
            'Annual Update (Launch)' => 'annual_update',
            'Exam Changes?' => 'exam_changes',
            'Subs Updates' => 'subs_updates',
            'CFP Board #' => 'cfp_board_number',
            'EA #' => 'ea_number',
            'ERPA #' => 'erpa_number',
            'CFP CE  Calc' => 'cfp_ce_calc',
            'CPA CPE Calc' => 'cpa_cpe_calc',
            'CFP Words' => 'cfp_words',
            'CPA words' => 'cpa_words',
            'Rev Q' => 'rev_q',
            'Exam Q' => 'exam_q',
            'Min. No. Exam Q' => 'min_exam_q',
            'IAR Words' => 'iar_words',
            'IAR Q' => 'iar_q',
            'CFP  Subj' => 'cfp_subject',
            'CPA  Subj' => 'cpa_subject',
            'TX Subject Code' => 'tx_subject_code',
            'Previous CFP Cr' => 'previous_cfp_cr',
            'Previous CPA Cr' => 'previous_cpa_cr',
            'Previous EA OTRP ERP Cr' => 'previous_ea_otrp_erp_cr',
            'Previous CDFA Cr' => 'previous_cdfa_cr',
            'Notes' => 'notes_1'
        );
    }
    
    /**
     * Clean and validate field values
     */
    private function clean_value($field, $value) {
        // Handle empty values
        if ($value === '' || $value === 'na' || $value === '-') {
            return null;
        }
        
        // Handle numeric fields
        $numeric_fields = array(
            'pages_2025', 'cfp_credits', 'cpa_credits', 'ea_otrp_credits',
            'erpa_credits', 'cdfa_credits', 'cima_cpwa_rma_credits', 'iar_credits',
            'price_pdf', 'price_print', 'price_per_pdf_cpe', 'cfp_ce_calc',
            'cpa_cpe_calc', 'cfp_words', 'cpa_words', 'rev_q', 'exam_q',
            'min_exam_q', 'iar_words', 'iar_q', 'previous_cfp_cr',
            'previous_cpa_cr', 'previous_ea_otrp_erp_cr', 'previous_cdfa_cr'
        );
        
        if (in_array($field, $numeric_fields)) {
            // Remove commas and spaces
            $value = str_replace(array(',', ' '), '', $value);
            return is_numeric($value) ? $value : null;
        }
        
        // Handle date fields
        if ($field === 'annual_update') {
            $date = date_create_from_format('n/j/Y', $value);
            if (!$date) {
                $date = date_create_from_format('n/j/y', $value);
            }
            return $date ? $date->format('Y-m-d') : null;
        }
        
        return $value;
    }
    
    /**
     * Get import results
     */
    public function get_results() {
        return array(
            'imported' => $this->imported_count,
            'skipped' => $this->skipped_count,
            'errors' => $this->errors
        );
    }
} 