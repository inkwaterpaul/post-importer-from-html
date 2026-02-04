# HTML Post Importer

A WordPress plugin that allows you to import HTML files as posts. The plugin extracts content from specific HTML elements and creates WordPress posts with proper formatting.

## Features

- **Multiple File Import**: Select and import multiple HTML files at once
- **Smart Content Extraction**:
  - Title from `<h1>` tag
  - Content from `<div class="page-content">` with cleaned HTML
  - Post date from `<small>` tag
- **Flexible Import Options**:
  - Set post status (Draft, Published, Pending)
  - Assign post author
  - Set post category
- **Clean HTML**: Automatically removes inline styles, extra attributes (paraeid, paraid), and cleans up the content
- **Import Tracking**: Logs all imports with success/failure status
- **User-Friendly Interface**: Modern admin interface with progress tracking and detailed results

## Installation

1. Copy the `post-importer` folder to `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to 'HTML Importer' in the WordPress admin menu

## Usage

1. **Navigate to the Plugin**
   - Go to WordPress Admin → HTML Importer

2. **Select HTML Files**
   - Click "Select HTML Files" button
   - Choose one or more HTML files from your computer
   - Files must be in `.html` or `.htm` format

3. **Configure Import Settings**
   - **Post Status**: Choose Draft, Published, or Pending Review
   - **Post Author**: Select the author for imported posts
   - **Post Category**: Optionally assign a category

4. **Import Files**
   - Click "Import Files" button
   - Watch the progress bar as files are processed
   - Review the results showing successful and failed imports

5. **Review Imported Posts**
   - Successful imports show Edit and View links
   - Edit posts to make any final adjustments
   - Publish draft posts when ready

## HTML File Requirements

Your HTML files should have the following structure:

```html
<!DOCTYPE html>
<html>
<head>
    <title>Page Title</title>
</head>
<body>
    <h1>Post Title Here</h1>

    <div class="page-content">
        <p>Your content here...</p>
        <p>More content...</p>
    </div>

    <div><small>6th January 2026</small></div>
</body>
</html>
```

### Required Elements

- **`<h1>`**: The first h1 tag will be used as the post title
- **`<div class="page-content">`**: Content within this div will be imported as post content
- **`<small>`**: The first small tag will be parsed as the post date (optional)

### Content Cleaning

The plugin automatically:
- Removes `style` attributes
- Removes `class` attributes
- Removes custom attributes like `paraeid` and `paraid`
- Preserves semantic HTML tags (p, img, a, strong, em, etc.)
- Maintains image src and link href attributes

## File Structure

```
post-importer/
├── html-post-importer.php          # Main plugin file
├── README.md                        # This file
├── assets/
│   ├── css/
│   │   └── admin-style.css         # Admin interface styles
│   └── js/
│       └── admin-script.js         # Admin interface JavaScript
└── includes/
    ├── class-admin-ui.php          # Admin interface
    ├── class-ajax-handler.php      # AJAX request handling
    ├── class-content-extractor.php # HTML parsing and extraction
    ├── class-importer.php          # Post creation logic
    └── class-logger.php            # Import logging
```

## Technical Details

### Content Extraction

The plugin uses PHP's DOMDocument and DOMXPath to parse HTML:
- Safely handles malformed HTML
- Extracts content from specific elements
- Cleans and sanitizes HTML before importing

### Date Parsing

Dates are parsed using PHP's DateTime class:
- Handles various date formats
- Removes ordinal suffixes (1st, 2nd, 3rd, etc.)
- Stores in WordPress standard format (Y-m-d H:i:s)
- Falls back to current date if parsing fails

### Import Logging

All imports are logged in the database:
- Track successful and failed imports
- Store original filename
- Record user who performed the import
- View import history and statistics

## Troubleshooting

### "No title found in HTML file"
- Ensure your HTML file contains an `<h1>` tag
- Check that the h1 tag is not empty

### "No content found in HTML file"
- Verify your HTML has a `<div class="page-content">` element
- Check that the div contains content

### "File validation failed"
- Ensure file is .html or .htm format
- Check file size is under 10MB
- Verify file is not corrupted

### Images not displaying
- Relative image URLs may need to be updated
- Consider using absolute URLs for images
- Or upload images to WordPress media library separately

## Support

For issues or questions:
1. Check that your HTML files meet the required structure
2. Review the import results for specific error messages
3. Check WordPress error logs for detailed information

## Changelog

### Version 1.0.0
- Initial release
- Multiple file import support
- Content extraction from h1, div.page-content, and small tags
- HTML cleaning and sanitization
- Import logging and statistics
- Modern admin interface

## License

GPL v2 or later

## Credits

Developed for Diocese of Manchester
Based on the Ultimate Content Importer structure
