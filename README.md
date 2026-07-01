# HTML Post Importer

A WordPress plugin that imports HTML files as WordPress posts. It extracts content from specific HTML elements and creates posts with the title, content, date, featured image, and any other images included.

## Features

- **Multiple File Import**: Select and import multiple HTML files at once
- **Smart Content Extraction**:
  - Title from `<h1>` tag
  - Content from `<div class="page-content">`, converted to WordPress blocks
  - Date from `<small>` tag
  - Featured image (the first image found in the content) and any other images referenced in the content
- **Flexible Import Options**:
  - Set post status (Draft, Published, Pending Review)
  - Optionally assign an existing category to all imported posts
- **Clean HTML**: Automatically removes inline styles, extra attributes (paraeid, paraid), and cleans up the content
- **Import Tracking**: Logs all imports with success/failure status
- **User-Friendly Interface**: Modern admin interface with progress tracking and detailed results

## Installation

1. Copy the `post-importer-from-html` folder to `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to 'HTML Post Importer' in the WordPress admin menu

## Usage

1. **Navigate to the Plugin**
   - Go to WordPress Admin → HTML Post Importer

2. **Select HTML Files**
   - Click "Select HTML Files" button
   - Choose one or more HTML files from your computer
   - Files must be in `.html` or `.htm` format

3. **Configure Import Settings**
   - **Post Status**: Choose Draft, Published, or Pending Review
   - **Category**: Optionally assign a category to all imported posts

4. **Import Files**
   - Click "Import Files" button
   - Watch the progress bar as files are processed
   - Review the results showing successful and failed imports

5. **Upload Missing Media**
   - If any images/documents referenced in the HTML weren't already in your media library, a "Step 2 — Upload Missing Media" panel appears listing exactly which filenames are needed
   - Select the folder(s) on your computer containing those files; only the matched files are uploaded
   - The first image uploaded for a post is set as its featured image

6. **Review Imported Posts**
   - Successful imports show Edit and View links
   - Edit posts to make any final adjustments
   - Publish draft posts when ready

## HTML File Requirements

Your HTML files should have the following structure:

```html
<!DOCTYPE html>
<html>
<head>
    <title>Post Title</title>
</head>
<body>
    <h1>Post Title Here</h1>

    <div class="page-content">
        <img src="featured.jpg" alt="">
        <p>Your content here...</p>
        <p>More content, with any other images inline...</p>
    </div>

    <div><small>6th January 2026</small></div>
</body>
</html>
```

### Required Elements

- **`<h1>`**: The first h1 tag will be used as the post title
- **`<div class="page-content">`**: Content within this div will be imported as post content
- **`<small>`**: The first small tag will be parsed as the post date (optional)

### Images

- The first `<img>` found inside `<div class="page-content">` is used as the featured image
- Every other `<img>` in the content is uploaded to the media library and its URL rewritten in place
- Media is not uploaded during the initial import — instead, the plugin records every image/document filename referenced in the content and lists them in a "Step 2 — Upload Missing Media" panel shown right after import
- You select the folder(s) containing those files and only the needed ones are uploaded and linked to the right posts

### Content Cleaning

The plugin automatically:
- Removes `style` attributes
- Removes `class` attributes
- Removes custom attributes like `paraeid` and `paraid`
- Preserves semantic HTML tags (p, img, a, strong, em, etc.)
- Maintains image src and link href attributes

## File Structure

```
post-importer-from-html/
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
- Use the "Upload Missing Media" step shown after import and select the folder(s) containing the referenced image/document files

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
- Featured image and inline image extraction/upload
- HTML cleaning and sanitization
- Import logging and statistics
- Modern admin interface

## License

GPL v2 or later

## Credits

Developed by Ink & Water
