jQuery(document).ready(function($) {
    
    // Course search functionality
    let searchTimer;
    $('#cim-search, #cim-filter-matched, #cim-filter-certification').on('change keyup', function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function() {
            searchCourses();
        }, 300);
    });
    
    function searchCourses() {
        const data = {
            action: 'cim_search_courses',
            nonce: cim_ajax.nonce,
            search: $('#cim-search').val(),
            matched: $('#cim-filter-matched').val(),
            certification: $('#cim-filter-certification').val()
        };
        
        $.post(cim_ajax.ajax_url, data, function(response) {
            if (response.success) {
                $('#cim-course-list').html(response.data);
            }
        });
    }
    
    // Course matching functionality
    $('.cim-match-button').on('click', function() {
        const $row = $(this).closest('.cim-match-row');
        const courseId = $row.data('course-id');
        const lifterLmsId = $row.find('.cim-lifterlms-select').val();
        
        if (!lifterLmsId) {
            alert('Please select a LifterLMS course to match');
            return;
        }
        
        const $button = $(this);
        const $status = $row.find('.cim-match-status');
        
        $button.prop('disabled', true);
        $status.text('Matching...');
        
        const data = {
            action: 'cim_match_course',
            nonce: cim_ajax.nonce,
            cim_course_id: courseId,
            lifterlms_course_id: lifterLmsId
        };
        
        $.post(cim_ajax.ajax_url, data, function(response) {
            if (response.success) {
                $status.html('<span style="color: green;">✓ Matched</span>');
                setTimeout(function() {
                    $row.fadeOut();
                }, 1000);
            } else {
                $status.html('<span style="color: red;">✗ Failed</span>');
                alert(response.data || 'Failed to match courses');
                $button.prop('disabled', false);
            }
        });
    });
    
    // Edit course functionality
    $(document).on('click', '.cim-edit-course', function(e) {
        e.preventDefault();
        const courseId = $(this).data('id');
        // TODO: Implement inline editing or modal
        alert('Edit functionality coming soon for course ID: ' + courseId);
    });
    
    // Auto-match button (if added to UI)
    $('#cim-auto-match').on('click', function() {
        if (!confirm('This will automatically match courses with high confidence. Continue?')) {
            return;
        }
        
        const $button = $(this);
        $button.prop('disabled', true).text('Matching...');
        
        const data = {
            action: 'cim_auto_match',
            nonce: cim_ajax.nonce
        };
        
        $.post(cim_ajax.ajax_url, data, function(response) {
            if (response.success) {
                alert(response.data);
                location.reload();
            } else {
                alert('Auto-matching failed');
            }
            $button.prop('disabled', false).text('Auto-Match Courses');
        });
    });
}); 