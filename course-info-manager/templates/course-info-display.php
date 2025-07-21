<?php
/**
 * Template for displaying course information on LifterLMS course pages
 * 
 * @var object $course_info Course information object
 */

if (!defined('ABSPATH')) {
    exit;
}

$manager = new CIM_Course_Manager();
$credits_display = $manager->get_formatted_credits($course_info);
?>

<div class="cim-course-info-display">
    <h3>Course Certification Information</h3>
    
    <?php if ($credits_display): ?>
        <div class="cim-credits">
            <strong>Continuing Education Credits:</strong>
            <span class="cim-credits-list"><?php echo esc_html($credits_display); ?></span>
        </div>
    <?php endif; ?>
    
    <div class="cim-course-details">
        <?php if ($course_info->cfp_board_number): ?>
            <div class="cim-detail">
                <strong>CFP Board #:</strong> <?php echo esc_html($course_info->cfp_board_number); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($course_info->ea_number): ?>
            <div class="cim-detail">
                <strong>IRS EA #:</strong> <?php echo esc_html($course_info->ea_number); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($course_info->exam_q): ?>
            <div class="cim-detail">
                <strong>Exam Questions:</strong> <?php echo intval($course_info->exam_q); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($course_info->annual_update): ?>
            <div class="cim-detail">
                <strong>Last Updated:</strong> <?php echo date('F Y', strtotime($course_info->annual_update)); ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if (current_user_can('manage_options')): ?>
        <div class="cim-admin-info">
            <small>
                Course Code: <?php echo esc_html($course_info->four_digit); ?> | 
                Edition: <?php echo esc_html($course_info->edition); ?>
            </small>
        </div>
    <?php endif; ?>
</div>

<style>
.cim-course-info-display {
    background: #f5f5f5;
    border: 1px solid #ddd;
    padding: 20px;
    margin: 20px 0;
    border-radius: 5px;
}

.cim-course-info-display h3 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #333;
}

.cim-credits {
    font-size: 16px;
    margin-bottom: 15px;
    padding: 10px;
    background: #fff;
    border-radius: 3px;
}

.cim-credits-list {
    color: #2271b1;
    font-weight: 500;
}

.cim-course-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 10px;
    margin-top: 15px;
}

.cim-detail {
    padding: 8px 0;
    border-bottom: 1px dotted #ddd;
}

.cim-admin-info {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #ddd;
    color: #666;
}
</style> 