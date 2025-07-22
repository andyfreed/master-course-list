<?php
/**
 * CSV Importer Class
 * Handles importing course data from CSV file
 */

class CIM_CSV_Importer {
    
    private $errors = array();
    private $imported_count = 0;
    private $skipped_count = 0;
    private $debug_info = array();
    
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
        
        // Get headers and remove BOM (Byte Order Mark) if present
        $headers = fgetcsv($handle);
        $headers = array_map('trim', $headers);
        
        // Remove BOM from first header if present
        if (!empty($headers[0])) {
            $headers[0] = $this->remove_bom($headers[0]);
        }
        
        // Debug: Log the actual headers found
        $this->debug_info[] = "CSV Headers found: " . implode(', ', $headers);
        
        // Map CSV headers to database fields
        $field_mapping = $this->get_field_mapping();
        
        // Debug: Log the field mapping
        $this->debug_info[] = "Field mapping keys: " . implode(', ', array_keys($field_mapping));
        
        // Check if 'Four Digit' header exists
        if (!in_array('Four Digit', $headers)) {
            $this->debug_info[] = "WARNING: 'Four Digit' header not found in CSV";
            $this->debug_info[] = "Available headers: " . implode(', ', $headers);
            
            // Try to find a similar header
            foreach ($headers as $header) {
                if (stripos($header, 'four') !== false && stripos($header, 'digit') !== false) {
                    $this->debug_info[] = "Found similar header: '$header'";
                }
            }
        }
        
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
                
                // Handle empty edition - try to use current_year as fallback
                if (empty($course_data['edition']) && !empty($course_data['current_year'])) {
                    $course_data['edition'] = $course_data['current_year'];
                    $this->debug_info[] = "Used current_year as edition for course {$course_data['four_digit']}";
                }
                
                // Handle empty current_year - try to use edition as fallback
                if (empty($course_data['current_year']) && !empty($course_data['edition'])) {
                    $course_data['current_year'] = $course_data['edition'];
                    $this->debug_info[] = "Used edition as current_year for course {$course_data['four_digit']}";
                }
                
                // Handle empty course_title - use a default
                if (empty($course_data['course_title'])) {
                    $course_data['course_title'] = "Course {$course_data['four_digit']}";
                    $this->debug_info[] = "Used default title for course {$course_data['four_digit']}";
                }
                
                // Skip if no four_digit code
                if (empty($course_data['four_digit'])) {
                    $this->skipped_count++;
                    $this->debug_info[] = "Skipped: No four_digit code";
                    continue;
                }
                
                // Skip if no edition (required for unique identification)
                if (empty($course_data['edition'])) {
                    $this->skipped_count++;
                    $this->debug_info[] = "Skipped: No edition for course {$course_data['four_digit']}";
                    continue;
                }
                
                // Check if course already exists by four_digit + edition combination
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM $table_name WHERE four_digit = %s AND edition = %s",
                    $course_data['four_digit'],
                    $course_data['edition']
                ));
                
                // Insert or update course data
                if ($existing) {
                    // Update existing record and track changes
                    $this->update_course_with_history($existing->id, $course_data);
                    $this->updated_count++;
                } else {
                    // Insert new record
                    $result = $wpdb->insert($table_name, $course_data);
                    
                    if ($result === false) {
                        $error_msg = $wpdb->last_error;
                        $this->debug_info[] = "Database insert error for course {$course_data['four_digit']}: " . $error_msg;
                        
                        // Log the problematic data for debugging
                        $this->debug_info[] = "Problematic data: " . json_encode($course_data);
                        
                        $this->skipped_count++;
                        continue;
                    }
                    
                    $this->imported_count++;
                }
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
            'FourDigit' => 'four_digit',
            'four_digit' => 'four_digit',
            'four digit' => 'four_digit',
            'Previous Edition' => 'edition',
            'PreviousEdition' => 'edition',
            'previous_edition' => 'edition',
            'Current Year' => 'current_year',
            'CurrentYear' => 'current_year',
            'current_year' => 'current_year',
            'Course (Shaded by author)' => 'course_title',
            'Course' => 'course_title',
            'course_title' => 'course_title',
            '2025 Pages' => 'pages_2025',
            '2025Pages' => 'pages_2025',
            'pages_2025' => 'pages_2025',
            'NOTES' => 'notes',
            'notes' => 'notes',
            'Santucci Title:' => 'santucci_title',
            'SantucciTitle' => 'santucci_title',
            'santucci_title' => 'santucci_title',
            'CFP' => 'cfp_credits',
            'cfp_credits' => 'cfp_credits',
            'CPA' => 'cpa_credits',
            'cpa_credits' => 'cpa_credits',
            'EA OTRP' => 'ea_otrp_credits',
            'EAOTRP' => 'ea_otrp_credits',
            'ea_otrp_credits' => 'ea_otrp_credits',
            'ERPA' => 'erpa_credits',
            'erpa_credits' => 'erpa_credits',
            'CDFA' => 'cdfa_credits',
            'cdfa_credits' => 'cdfa_credits',
            'CIMA CPWA RMA' => 'cima_cpwa_rma_credits',
            'CIMACPWARMA' => 'cima_cpwa_rma_credits',
            'cima_cpwa_rma_credits' => 'cima_cpwa_rma_credits',
            'IAR' => 'iar_credits',
            'iar_credits' => 'iar_credits',
            'IAR #' => 'iar_number',
            'IAR#' => 'iar_number',
            'iar_number' => 'iar_number',
            '$ PDF or Exam Only' => 'price_pdf',
            '$PDForExamOnly' => 'price_pdf',
            'price_pdf' => 'price_pdf',
            '$ Print' => 'price_print',
            '$Print' => 'price_print',
            'price_print' => 'price_print',
            '$ per PDF CPE' => 'price_per_pdf_cpe',
            '$perPDFCPE' => 'price_per_pdf_cpe',
            'price_per_pdf_cpe' => 'price_per_pdf_cpe',
            'Annual Update (Launch)' => 'annual_update',
            'AnnualUpdate' => 'annual_update',
            'annual_update' => 'annual_update',
            'Exam Changes?' => 'exam_changes',
            'ExamChanges' => 'exam_changes',
            'exam_changes' => 'exam_changes',
            'Subs Updates' => 'subs_updates',
            'SubsUpdates' => 'subs_updates',
            'subs_updates' => 'subs_updates',
            'CFP Board #' => 'cfp_board_number',
            'CFPBoard#' => 'cfp_board_number',
            'cfp_board_number' => 'cfp_board_number',
            'EA #' => 'ea_number',
            'EA#' => 'ea_number',
            'ea_number' => 'ea_number',
            'ERPA #' => 'erpa_number',
            'ERPA#' => 'erpa_number',
            'erpa_number' => 'erpa_number',
            'CFP CE  Calc' => 'cfp_ce_calc',
            'CFPCE Calc' => 'cfp_ce_calc',
            'cfp_ce_calc' => 'cfp_ce_calc',
            'CPA CPE Calc' => 'cpa_cpe_calc',
            'CPACPE Calc' => 'cpa_cpe_calc',
            'cpa_cpe_calc' => 'cpa_cpe_calc',
            'CFP Words' => 'cfp_words',
            'CFPWords' => 'cfp_words',
            'cfp_words' => 'cfp_words',
            'CPA words' => 'cpa_words',
            'CPAwords' => 'cpa_words',
            'cpa_words' => 'cpa_words',
            'Rev Q' => 'rev_q',
            'RevQ' => 'rev_q',
            'rev_q' => 'rev_q',
            'Exam Q' => 'exam_q',
            'ExamQ' => 'exam_q',
            'exam_q' => 'exam_q',
            'Min. No. Exam Q' => 'min_exam_q',
            'MinNoExamQ' => 'min_exam_q',
            'min_exam_q' => 'min_exam_q',
            'IAR Words' => 'iar_words',
            'IARWords' => 'iar_words',
            'iar_words' => 'iar_words',
            'IAR Q' => 'iar_q',
            'IARQ' => 'iar_q',
            'iar_q' => 'iar_q',
            'CFP  Subj' => 'cfp_subject',
            'CFPSubj' => 'cfp_subject',
            'cfp_subject' => 'cfp_subject',
            'CPA  Subj' => 'cpa_subject',
            'CPASubj' => 'cpa_subject',
            'cpa_subject' => 'cpa_subject',
            'TX Subject Code' => 'tx_subject_code',
            'TXSubjectCode' => 'tx_subject_code',
            'tx_subject_code' => 'tx_subject_code',
            'Previous CFP Cr' => 'previous_cfp_cr',
            'PreviousCFPCr' => 'previous_cfp_cr',
            'previous_cfp_cr' => 'previous_cfp_cr',
            'Previous CPA Cr' => 'previous_cpa_cr',
            'PreviousCPACr' => 'previous_cpa_cr',
            'previous_cpa_cr' => 'previous_cpa_cr',
            'Previous EA OTRP ERP Cr' => 'previous_ea_otrp_erp_cr',
            'PreviousEAOTRPERPCr' => 'previous_ea_otrp_erp_cr',
            'previous_ea_otrp_erp_cr' => 'previous_ea_otrp_erp_cr',
            'Previous CDFA Cr' => 'previous_cdfa_cr',
            'PreviousCDFACr' => 'previous_cdfa_cr',
            'previous_cdfa_cr' => 'previous_cdfa_cr',
            'Notes' => 'notes_1',
            'notes_1' => 'notes_1'
        );
    }
    
    /**
     * Clean and validate field values
     */
    private function clean_value($field, $value) {
        // Handle empty values
        if ($value === '' || $value === 'na' || $value === '-' || $value === ' ' || $value === null) {
            return null;
        }
        
        // Trim whitespace
        $value = trim($value);
        
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
            // Remove commas, spaces, and other non-numeric characters except decimal points
            $value = preg_replace('/[^0-9.-]/', '', $value);
            return is_numeric($value) && $value !== '' ? $value : null;
        }
        
        // Handle date fields
        if ($field === 'annual_update') {
            if (empty($value)) {
                return null;
            }
            $date = date_create_from_format('n/j/Y', $value);
            if (!$date) {
                $date = date_create_from_format('n/j/y', $value);
            }
            if (!$date) {
                $date = date_create_from_format('Y-m-d', $value);
            }
            return $date ? $date->format('Y-m-d') : null;
        }
        
        // Handle text fields - return empty string as null
        if (empty($value)) {
            return null;
        }
        
        return $value;
    }

    /**
     * Remove BOM (Byte Order Mark) from a string
     */
    private function remove_bom($string) {
        // Remove UTF-8 BOM
        if (substr($string, 0, 3) === "\xEF\xBB\xBF") {
            return substr($string, 3);
        }
        // Remove UTF-16 BOM (little endian)
        if (substr($string, 0, 2) === "\xFF\xFE") {
            return substr($string, 2);
        }
        // Remove UTF-16 BOM (big endian)
        if (substr($string, 0, 2) === "\xFE\xFF") {
            return substr($string, 2);
        }
        return $string;
    }
    
    /**
     * Get import results
     */
    public function get_results() {
        return array(
            'imported' => $this->imported_count,
            'skipped' => $this->skipped_count,
            'errors' => $this->errors,
            'debug' => $this->debug_info
        );
    }
} 