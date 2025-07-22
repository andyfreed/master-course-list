<?php
/**
 * Admin Menu Functions
 */

// Add admin menu
add_action('admin_menu', 'cim_add_admin_menu');
function cim_add_admin_menu() {
    // Main menu
    add_menu_page(
        'Course Info Manager',
        'Course Info',
        'manage_options',
        'course-info-manager',
        'cim_dashboard_page',
        'dashicons-welcome-learn-more',
        30
    );
    
    // Submenu pages
    add_submenu_page(
        'course-info-manager',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'course-info-manager',
        'cim_dashboard_page'
    );
    
    add_submenu_page(
        'course-info-manager',
        'Import CSV',
        'Import CSV',
        'manage_options',
        'cim-import',
        'cim_import_page'
    );
    
    add_submenu_page(
        'course-info-manager',
        'Course Matching',
        'Course Matching',
        'manage_options',
        'cim-matching',
        'cim_matching_page'
    );
    
    add_submenu_page(
        'course-info-manager',
        'Version History',
        'Version History',
        'manage_options',
        'cim-history',
        'cim_history_page'
    );
    add_submenu_page('course-info-manager', 'Diagnostics', 'Diagnostics', 'manage_options', 'cim-diagnostics', 'cim_diagnostics_page');
}

/**
 * Dashboard Page
 */
function cim_dashboard_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cim_course_info';
    
    // Get statistics
    $total_courses = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $matched_courses = $wpdb->get_var("SELECT COUNT(DISTINCT cim_course_id) FROM {$wpdb->prefix}cim_course_matching");
    $unmatched_courses = $total_courses - $matched_courses;
    
    ?>
    <div class="wrap">
        <h1>Course Information Manager Dashboard</h1>
        
        <!-- Statistics -->
        <div class="cim-stats">
            <div class="cim-stat-box">
                <h3>Total Courses</h3>
                <p class="cim-stat-number"><?php echo $total_courses; ?></p>
            </div>
            <div class="cim-stat-box">
                <h3>Matched to LifterLMS</h3>
                <p class="cim-stat-number"><?php echo $matched_courses; ?></p>
            </div>
            <div class="cim-stat-box">
                <h3>Unmatched</h3>
                <p class="cim-stat-number"><?php echo $unmatched_courses; ?></p>
            </div>
        </div>
        
        <!-- Course List -->
        <h2>Course Information</h2>
        <div class="cim-filters">
            <input type="text" id="cim-search" placeholder="Search courses..." />
            <select id="cim-filter-matched">
                <option value="">All Courses</option>
                <option value="matched">Matched Only</option>
                <option value="unmatched">Unmatched Only</option>
            </select>
            <select id="cim-filter-certification">
                <option value="">All Certifications</option>
                <option value="cfp">CFP</option>
                <option value="cpa">CPA</option>
                <option value="ea">EA/OTRP</option>
                <option value="cdfa">CDFA</option>
            </select>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Four Digit</th>
                    <th>Edition</th>
                    <th>Course Title</th>
                    <th>CFP</th>
                    <th>CPA</th>
                    <th>EA/OTRP</th>
                    <th>CDFA</th>
                    <th>Matched</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="cim-course-list">
                <?php
                $courses = $wpdb->get_results("
                    SELECT c.*, m.lifterlms_course_id 
                    FROM $table_name c
                    LEFT JOIN {$wpdb->prefix}cim_course_matching m ON c.id = m.cim_course_id
                    ORDER BY c.four_digit, c.edition DESC
                    LIMIT 50
                ");
                
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
                ?>
            </tbody>
        </table>
    </div>
    
    <style>
    .cim-stats {
        display: flex;
        gap: 20px;
        margin: 20px 0;
    }
    .cim-stat-box {
        background: #fff;
        border: 1px solid #ddd;
        padding: 20px;
        text-align: center;
        flex: 1;
    }
    .cim-stat-number {
        font-size: 32px;
        font-weight: bold;
        color: #2271b1;
        margin: 10px 0;
    }
    .cim-filters {
        margin: 20px 0;
        display: flex;
        gap: 10px;
    }
    .cim-filters input, .cim-filters select {
        padding: 5px;
    }
    </style>
    <?php
}

/**
 * Import Page
 */
function cim_import_page() {
    ?>
    <div class="wrap">
        <h1>Course Info Manager - Import</h1>
        
        <?php if (isset($_POST['update_database'])): ?>
            <?php
            try {
                cim_create_database_tables();
                echo '<div class="notice notice-success"><p>Database tables updated successfully!</p></div>';
            } catch (Exception $e) {
                echo '<div class="notice notice-error"><p>Error updating database: ' . esc_html($e->getMessage()) . '</p></div>';
            }
            ?>
        <?php endif; ?>
        
        <?php
        if (isset($_POST['import_csv']) && !empty($_FILES['csv_file']['tmp_name'])) {
            $importer = new CIM_CSV_Importer();
            $result = $importer->import_csv($_FILES['csv_file']['tmp_name']);
            
            if ($result) {
                $results = $importer->get_results();
                echo '<div class="notice notice-success"><p>';
                echo sprintf('Import completed: %d courses imported, %d updated, %d skipped.', 
                    $results['imported'], $results['updated'], $results['skipped']);
                echo '</p></div>';
                
                // Show debug info if there are skipped courses
                if ($results['skipped'] > 0 && !empty($results['debug'])) {
                    echo '<div class="notice notice-warning"><p><strong>Debug Information:</strong></p>';
                    echo '<ul>';
                    foreach (array_slice($results['debug'], 0, 10) as $debug) {
                        echo '<li>' . esc_html($debug) . '</li>';
                    }
                    if (count($results['debug']) > 10) {
                        echo '<li>... and ' . (count($results['debug']) - 10) . ' more</li>';
                    }
                    echo '</ul></div>';
                }
            } else {
                $results = $importer->get_results();
                echo '<div class="notice notice-error"><p>';
                echo 'Import failed: ' . implode(', ', $results['errors']);
                echo '</p></div>';
            }
        }
        ?>
        
        <form method="post" enctype="multipart/form-data">
            <table class="form-table">
                <tr>
                    <th scope="row">Update Database Schema</th>
                    <td>
                        <p>If you're getting field length errors, click this button to recreate the database tables with the updated schema:</p>
                        <input type="submit" name="update_database" class="button button-secondary" value="Update Database Schema">
                    </td>
                </tr>
                <tr>
                    <th scope="row">CSV File</th>
                    <td>
                        <input type="file" name="csv_file" accept=".csv" required>
                        <p class="description">Select the master course spreadsheet CSV file</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="import_csv" class="button button-primary" value="Import CSV">
            </p>
        </form>
    </div>
    <?php
}

/**
 * Matching Page
 */
function cim_matching_page() {
    global $wpdb;
    
    // Get unmatched courses
    $unmatched = $wpdb->get_results("
        SELECT c.* 
        FROM {$wpdb->prefix}cim_course_info c
        LEFT JOIN {$wpdb->prefix}cim_course_matching m ON c.id = m.cim_course_id
        WHERE m.id IS NULL
        ORDER BY c.four_digit, c.edition
    ");
    
    // Get LifterLMS courses using the new discovery function
    $matcher = new CIM_Course_Matcher();
    $lifterlms_courses = $matcher->get_active_courses(); // Use active courses only
    
    // Get total courses for comparison
    $all_courses = $matcher->get_lifterlms_courses();
    
    ?>
    <div class="wrap">
        <h1>Course Matching</h1>
        
        <div class="cim-matching-stats">
            <div class="cim-stat-box">
                <h3>Imported Courses</h3>
                <p class="cim-stat-number"><?php echo count($unmatched); ?></p>
                <p>Ready to match</p>
            </div>
            <div class="cim-stat-box">
                <h3>Active FLMS Courses</h3>
                <p class="cim-stat-number"><?php echo count($lifterlms_courses); ?></p>
                <p>Available for matching</p>
            </div>
            <div class="cim-stat-box">
                <h3>Total FLMS Courses</h3>
                <p class="cim-stat-number"><?php echo count($all_courses); ?></p>
                <p>Including inactive/archived</p>
            </div>
        </div>
        
        <?php if (empty($lifterlms_courses)): ?>
            <div class="notice notice-warning">
                <p><strong>No LifterLMS courses found!</strong></p>
                <p>The plugin searched for courses in the following post types: 'course', 'llms_course', 'sfwd-courses', 'courses'</p>
                <p>It also looked for posts with course-related meta fields like '_course_code', 'course_code', '_sku', 'course_credits', 'cfp_credits', 'cpa_credits'</p>
                <p>If your courses are stored differently, please let us know the post type or meta fields used.</p>
            </div>
        <?php else: ?>
            <p>Match your imported courses with existing LifterLMS courses. The system will suggest matches based on course codes and titles.</p>
            
            <div id="cim-matching-container">
                <h2>Unmatched Courses (<?php echo count($unmatched); ?>)</h2>
                
                <?php foreach ($unmatched as $course): ?>
                <div class="cim-match-row" data-course-id="<?php echo $course->id; ?>">
                    <div class="cim-course-info">
                        <strong><?php echo esc_html($course->four_digit . ' - ' . $course->edition); ?></strong><br>
                        <?php echo esc_html($course->course_title); ?><br>
                        <small>Credits: <?php echo esc_html($course->cfp_credits ? 'CFP: ' . $course->cfp_credits : ''); ?> 
                               <?php echo esc_html($course->cpa_credits ? 'CPA: ' . $course->cpa_credits : ''); ?></small>
                    </div>
                    <div class="cim-match-select">
                        <select class="cim-lifterlms-select">
                            <option value="">-- Select FLMS Course --</option>
                            <?php foreach ($lifterlms_courses as $lms_course): ?>
                                <option value="<?php echo $lms_course['id']; ?>">
                                    <?php echo esc_html($lms_course['title']); ?> 
                                    <?php if (!empty($lms_course['sku'])): ?>
                                        (SKU: <?php echo esc_html($lms_course['sku']); ?>)
                                    <?php endif; ?>
                                    (<?php echo esc_html($lms_course['type']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="button cim-match-button">Match</button>
                        <span class="cim-match-status"></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <style>
    .cim-matching-stats {
        display: flex;
        gap: 20px;
        margin: 20px 0;
    }
    .cim-stat-box {
        background: #fff;
        border: 1px solid #ddd;
        padding: 20px;
        text-align: center;
        flex: 1;
    }
    .cim-stat-number {
        font-size: 32px;
        font-weight: bold;
        color: #2271b1;
        margin: 10px 0;
    }
    .cim-match-row {
        display: flex;
        align-items: center;
        padding: 15px;
        border-bottom: 1px solid #ddd;
        background: #fff;
        margin-bottom: 5px;
    }
    .cim-course-info {
        flex: 1;
    }
    .cim-match-select {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    .cim-lifterlms-select {
        width: 400px;
    }
    </style>
    <?php
}

/**
 * History Page
 */
function cim_history_page() {
    global $wpdb;
    
    $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
    
    if ($course_id) {
        $course = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cim_course_info WHERE id = %d",
            $course_id
        ));
        
        $history = $wpdb->get_results($wpdb->prepare(
            "SELECT h.*, u.display_name 
             FROM {$wpdb->prefix}cim_course_history h
             LEFT JOIN {$wpdb->users} u ON h.changed_by = u.ID
             WHERE h.course_id = %d
             ORDER BY h.change_date DESC",
            $course_id
        ));
    }
    
    ?>
    <div class="wrap">
        <h1>Course Version History</h1>
        
        <?php if ($course_id && $course): ?>
            <h2><?php echo esc_html($course->four_digit . ' - ' . $course->course_title); ?></h2>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Field</th>
                        <th>Old Value</th>
                        <th>New Value</th>
                        <th>Changed By</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $change): ?>
                    <tr>
                        <td><?php echo $change->change_date; ?></td>
                        <td><?php echo esc_html($change->field_name); ?></td>
                        <td><?php echo esc_html($change->old_value); ?></td>
                        <td><?php echo esc_html($change->new_value); ?></td>
                        <td><?php echo esc_html($change->display_name ?: 'System'); ?></td>
                        <td><?php echo esc_html($change->change_notes); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Please select a course to view its history.</p>
        <?php endif; ?>
    </div>
    <?php
} 

/**
 * Diagnostics Page
 */
function cim_diagnostics_page() {
    global $wpdb;
    
    ?>
    <div class="wrap">
        <h1>Course Information Manager - Diagnostics</h1>
        
        <h2>Database Structure Analysis</h2>
        
        <h3>1. Post Types Found</h3>
        <?php
        $post_types = $wpdb->get_results("
            SELECT post_type, COUNT(*) as count
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            GROUP BY post_type
            ORDER BY count DESC
        ");
        
        if ($post_types): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Post Type</th>
                        <th>Count</th>
                        <th>Likely Course Type?</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($post_types as $pt): ?>
                        <tr>
                            <td><?php echo esc_html($pt->post_type); ?></td>
                            <td><?php echo $pt->count; ?></td>
                            <td>
                                <?php 
                                $course_types = array('course', 'llms_course', 'sfwd-courses', 'courses', 'lesson', 'llms_lesson', 'flms-courses', 'mnc-courses');
                                echo in_array($pt->post_type, $course_types) ? '✓ Yes' : '✗ No';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <h3>2. Course-Related Meta Fields</h3>
        <?php
        $meta_fields = $wpdb->get_results("
            SELECT meta_key, COUNT(*) as count
            FROM {$wpdb->postmeta}
            WHERE meta_key LIKE '%course%' 
               OR meta_key LIKE '%cfp%' 
               OR meta_key LIKE '%cpa%' 
               OR meta_key LIKE '%credit%'
               OR meta_key LIKE '%sku%'
               OR meta_key LIKE '%code%'
            GROUP BY meta_key
            ORDER BY count DESC
            LIMIT 20
        ");
        
        if ($meta_fields): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Meta Key</th>
                        <th>Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($meta_fields as $meta): ?>
                        <tr>
                            <td><?php echo esc_html($meta->meta_key); ?></td>
                            <td><?php echo $meta->count; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <h3>3. Sample Course Posts</h3>
        <?php
        $sample_courses = $wpdb->get_results("
            SELECT ID, post_title, post_type, post_status
            FROM {$wpdb->posts}
            WHERE post_type IN ('course', 'llms_course', 'sfwd-courses', 'courses', 'flms-courses', 'mnc-courses')
            AND post_status = 'publish'
            ORDER BY post_title
            LIMIT 10
        ");
        
        if ($sample_courses): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Post Type</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sample_courses as $course): ?>
                        <tr>
                            <td><?php echo $course->ID; ?></td>
                            <td><?php echo esc_html($course->post_title); ?></td>
                            <td><?php echo esc_html($course->post_type); ?></td>
                            <td><?php echo esc_html($course->post_status); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No course posts found with standard post types.</p>
        <?php endif; ?>
        
        <h3>4. Sample Meta Data</h3>
        <?php
        $sample_meta = $wpdb->get_results("
            SELECT p.ID, p.post_title, pm.meta_key, pm.meta_value
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_status = 'publish'
            AND (pm.meta_key LIKE '%course%' 
                 OR pm.meta_key LIKE '%cfp%' 
                 OR pm.meta_key LIKE '%cpa%' 
                 OR pm.meta_key LIKE '%credit%'
                 OR pm.meta_key LIKE '%sku%'
                 OR pm.meta_key LIKE '%code%')
            ORDER BY p.post_title, pm.meta_key
            LIMIT 20
        ");
        
        if ($sample_meta): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Post ID</th>
                        <th>Title</th>
                        <th>Meta Key</th>
                        <th>Meta Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sample_meta as $meta): ?>
                        <tr>
                            <td><?php echo $meta->ID; ?></td>
                            <td><?php echo esc_html($meta->post_title); ?></td>
                            <td><?php echo esc_html($meta->meta_key); ?></td>
                            <td><?php echo esc_html(substr($meta->meta_value, 0, 100)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <h3>5. Plugin Status</h3>
        <table class="wp-list-table widefat fixed striped">
            <tr>
                <td><strong>LifterLMS Active:</strong></td>
                <td><?php echo function_exists('lifterlms_get_post') ? '✓ Yes' : '✗ No'; ?></td>
            </tr>
            <tr>
                <td><strong>CIM Tables Created:</strong></td>
                <td>
                    <?php 
                    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}cim_course_info'");
                    echo $table_exists ? '✓ Yes' : '✗ No';
                    ?>
                </td>
            </tr>
            <tr>
                <td><strong>Imported Courses:</strong></td>
                <td>
                    <?php 
                    $course_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cim_course_info");
                    echo $course_count ? $course_count : '0';
                    ?>
                </td>
            </tr>
            <tr>
                <td><strong>Matched Courses:</strong></td>
                <td>
                    <?php 
                    $matched_count = $wpdb->get_var("SELECT COUNT(DISTINCT cim_course_id) FROM {$wpdb->prefix}cim_course_matching");
                    echo $matched_count ? $matched_count : '0';
                    ?>
                </td>
            </tr>
        </table>
    </div>
    <?php
} 