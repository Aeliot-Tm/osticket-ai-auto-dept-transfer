# Content Analysis

## Overview

The plugin extracts and analyzes text content from multiple sources within a ticket to determine the appropriate department for routing.

## Content Sources

The plugin collects text from:

1. **Ticket Subject**: The ticket's subject line
2. **Message Body**: All text from thread entries (HTML tags are stripped)
3. **Attachments**: Text extracted from attached files

## Supported File Types

### Images
- **Formats**: JPEG, JPG, PNG, GIF, WebP
- **Processing Method**: AI Vision API (OCR)
- **Requirements**: Model must support vision capabilities (e.g., gpt-4o, gpt-4o-mini)
- **Output**: Extracted text from images

### PDF Documents
- **Format**: PDF files
- **Processing Method**: `pdftotext` command-line utility
- **Requirements**: `pdftotext` must be installed on the server
- **Output**: Extracted text content
- **Note**: If `pdftotext` is not available, PDF files are ignored

### Word Documents
- **Formats**: .doc (legacy) and .docx (Office Open XML)
- **Processing Methods**:
  - `.doc`: `antiword` (primary) or `catdoc` (fallback)
  - `.docx`: `unzip` to extract `word/document.xml`, then text extraction
- **Requirements**: Appropriate utilities must be installed (`antiword`, `catdoc`, or `unzip`)
- **Output**: Extracted text content
- **Note**: If utilities are not available, Word files are ignored

## File Processing Rules

- **File Size Limit**: Configurable maximum file size (default: 10 MB)
  - Files exceeding the limit are ignored with a reason logged
- **Unsupported Types**: Files that don't match supported formats are ignored
- **Empty Content**: Files that yield no text after processing are ignored
- **Error Handling**: Files that cannot be processed due to errors are ignored with error details

## User-Facing Behavior

- All processed content is combined into a single text string for keyword matching
- Ignored files are tracked and listed in transfer notes (if file info display is enabled)
- File processing happens automatically during ticket analysis
- Processing details can be logged for debugging (if debug logging is enabled)

## Configuration

- **Max File Size (MB)**: Maximum file size to process for text extraction
- **Show processed files info**: Option to include extracted file contents in transfer notes (for debugging)
- When enabled, transfer notes show:
  - Extracted text from successfully processed files
  - List of ignored files with reasons for ignoring them

