# Master Course List Management System

This repository contains tools for managing course information for online education platforms.

## Contents

### Course Info Manager Plugin (`/course-info-manager`)
A WordPress plugin that safely manages course information alongside LifterLMS installations. 

**Features:**
- Import course data from CSV spreadsheets
- Track version history and changes
- Smart matching with existing LifterLMS courses
- Display certification credits (CFP, CPA, EA/OTRP, CDFA, etc.)
- Safe database separation - doesn't modify existing data

[Full documentation →](./course-info-manager/README.md)

### Master Course Spreadsheet
The `master-course-spreadsheet.csv` file contains the course data to be imported.

## Quick Start

1. Install the Course Info Manager plugin in WordPress
2. Import your CSV file through the admin interface
3. Match courses with your LifterLMS courses
4. Credits automatically display on course pages

## Repository Structure

```
master-course-list/
├── course-info-manager/        # WordPress plugin
│   ├── assets/                # CSS and JavaScript
│   ├── includes/              # PHP classes
│   ├── templates/             # Display templates
│   └── README.md             # Plugin documentation
└── master-course-spreadsheet.csv  # Course data
```

## License

This project is proprietary. All rights reserved. 