# AI-Powered Department Selection

## Overview

The plugin uses AI to intelligently select the most appropriate department when multiple keyword matches are found, and to extract text from images using OCR.

## Keyword Matching

The plugin first performs keyword-based matching:

1. All ticket content (subject, body, attachments) is collected into a single text string
2. Content is converted to lowercase for case-insensitive matching
3. Each department rule's keywords are checked against the content
4. Keywords can be separated by commas or semicolons
5. Partial text matching is used (keyword can appear anywhere in content)
6. Departments are sorted by number of matched keywords (descending)

## AI Selection Process

When multiple departments match:

1. The plugin prepares a prompt with:
   - Ticket content (truncated to 2000 characters)
   - List of matched departments with their IDs, names, and matched keywords
2. AI analyzes the content and selects the best department
3. AI returns JSON with:
   - `best_dept_id`: Selected department ID
   - `reasoning`: Explanation for the selection
4. The plugin validates the selection and performs the transfer

## Single Match Handling

- If only one department matches: transfer happens immediately without AI call
- This saves API costs and improves response time
- Confidence level is marked as "high" for single matches

## AI Vision for Images

For image attachments:

1. Image is converted to base64 encoding
2. Sent to AI Vision API with OCR prompt
3. AI extracts all readable text from the image
4. Extracted text is included in ticket content analysis
5. Uses `gpt-4o` model specifically for vision tasks (regardless of configured model)

## API Integration

### Supported Providers

- **OpenAI**: Official OpenAI API (default)
- **Custom**: Any OpenAI-compatible API endpoint

### Model Selection

Supports various models:
- GPT-5 series (latest models)
- Reasoning models (o-series: o3, o3-mini, o4-mini, o1, o1-mini)
- GPT-4.1 series (coding-focused)
- GPT-4o series (multimodal, recommended for vision)
- Legacy models (GPT-4 Turbo, GPT-3.5 Turbo)

### Configuration Options

- **Model Name**: Select from dropdown (OpenAI) or enter manually (Custom)
- **Temperature**: Controls response randomness (0.0-2.0, default: 0.3)
  - Lower values = more deterministic (recommended for classification)
  - Higher values = more creative/random
- **API Timeout**: Maximum wait time for API response (seconds)

## User-Facing Behavior

- AI selection happens automatically when multiple departments match
- Transfer notes include AI reasoning when AI was used
- Confidence levels:
  - "high": Single keyword match (no AI needed)
  - "medium": AI selection from multiple matches
- If AI selection fails, an error is logged and transfer is not performed

## Error Handling

- API errors are logged with details
- Invalid AI responses are caught and logged
- If AI selects an invalid department ID, transfer is not performed
- All errors result in a note being added to the ticket explaining the failure

