# Course Information Manager for WordPress

A WordPress plugin that safely manages course information from CSV files alongside your existing LifterLMS installation without modifying your current database.

## Features

- **Safe Database Separation**: Creates its own database tables - doesn't touch LifterLMS data
- **CSV Import**: Import course information from your spreadsheet
- **Version History**: Track all changes to course information over time
- **Smart Matching**: Automatically match imported courses with LifterLMS courses by:
  - 4-digit course codes
  - Edition numbers
  - Title similarity
- **Credit Display**: Show certification credits on course pages (CFP, CPA, EA/OTRP, CDFA, etc.)
- **Admin Dashboard**: Manage all course information in one place

## Installation

1. Upload the `course-info-manager` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin will create its own database tables on activation

## Usage

### Initial Setup

1. **Import Your CSV**
   - Go to **Course Info > Import CSV**
   - Upload your course spreadsheet CSV file
   - The import uses database transactions - if any error occurs, no data will be saved

2. **Match Courses**
   - Go to **Course Info > Course Matching**
   - The system will suggest matches based on course codes and titles
   - Review and approve matches manually, or use auto-match for high-confidence matches

### Daily Use

- **Dashboard**: View all courses, filter by certification type or match status
- **Version History**: Track all changes to course information
- **Frontend Display**: Matched courses automatically show credits on LifterLMS course pages

### CSV Format Expected

The plugin expects these column headers (among others):
- Four Digit
- Previous Edition  
- Current Year
- Course (Shaded by author)
- CFP, CPA, EA OTRP, ERPA, CDFA, IAR (credit columns)
- $ PDF or Exam Only, $ Print (pricing)
- Annual Update (Launch)
- CFP Board #, EA #, ERPA # (registration numbers)

## Safety Features

1. **Separate Database Tables**: All data stored in custom tables with `cim_` prefix
2. **Transaction Support**: Imports use database transactions for rollback on error
3. **Change Tracking**: Every update is logged with who made it and when
4. **No Direct LifterLMS Modifications**: Only displays data, doesn't modify courses
5. **Permission Checks**: All admin functions require `manage_options` capability

## Database Tables Created

- `wp_cim_course_info` - Main course information
- `wp_cim_course_history` - Version history tracking
- `wp_cim_course_matching` - Links between imported courses and LifterLMS

## Hooks & Filters

### Display Hook
```php
// Displays after LifterLMS course summary
add_action('lifterlms_single_course_after_summary', 'cim_display_course_info', 15);
```

## Troubleshooting

**Import fails**: Check that your CSV file matches the expected format and all required columns exist.

**Courses not matching**: Try different search terms or use manual matching. The auto-matcher requires 95%+ confidence.

**Credits not showing**: Ensure the course is properly matched in the Course Matching interface.

## Developer Notes

- Plugin follows WordPress coding standards
- All database queries use prepared statements
- AJAX endpoints are nonce-protected
- Compatible with WordPress 5.0+ and PHP 7.0+

## Support

For issues or questions, please check:
1. The course is matched correctly
2. CSV format matches expected headers
3. WordPress debug log for any errors

## Future Enhancements

- Inline editing of course information
- Bulk update tools
- Export functionality
- API endpoints for external access
- Automated matching improvements 