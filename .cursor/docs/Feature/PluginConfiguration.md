# Plugin Configuration

## Overview

The plugin provides comprehensive configuration options for API settings, processing behavior, access control, and debugging features.

## Configuration Sections

### API Settings

Controls how the plugin connects to AI services:

- **API Provider**: 
  - `Open AI`: Use official OpenAI API (default)
  - `Custom`: Use custom OpenAI-compatible API endpoint
- **API Key**: Authentication key for the API (required)
  - For OpenAI: Get from https://platform.openai.com/api-keys
  - For Custom: Use your provider's API key
- **API URL**: Custom endpoint URL (only for Custom provider)
  - Must be OpenAI-compatible
  - Example: `https://api.example.com/v1/chat/completions`
  - Automatically set for OpenAI provider
- **Model Name**: 
  - Dropdown selection for OpenAI provider (includes GPT-5, o-series, GPT-4.1, GPT-4o, legacy models)
  - Text input for Custom provider (enter any model name)
  - Default: `gpt-4o-mini` (recommended for cost-effectiveness)
- **Temperature**: Response randomness control (0.0-2.0)
  - Default: `0.3` (recommended for classification tasks)
  - Lower = more deterministic, Higher = more creative
- **API Timeout**: Maximum wait time for API response in seconds
  - Default: `30` seconds

### Processing Settings

Controls how tickets and files are processed:

- **Max File Size (MB)**: Maximum file size to process for text extraction
  - Default: `10` MB
  - Files exceeding this limit are ignored
- **Auto-transfer on ticket creation**: Toggle to enable/disable automatic processing
  - Default: `enabled`
  - When disabled, only manual transfers work
- **Show processed files info**: Include extracted file contents in transfer notes
  - Default: `disabled`
  - When enabled, shows analyzed file contents and ignored files list in notes
  - Useful for debugging and transparency

### Access Control Settings

Controls who can use manual transfer features:

- **Departments with Manual Transfer Button**: Multi-select dropdown
  - **Empty (default)**: Button visible to all staff from any department
  - **Specific departments selected**: Button only visible to staff from selected departments
  - Only affects manual button visibility; automatic transfer works for all departments

### Debug Settings

Controls logging and diagnostic information:

- **Enable Debug Logging**: Log processing details and AI requests
  - Default: `disabled`
  - When enabled, logs to system error log:
    - Ticket processing details
    - API requests and responses
    - Keyword matches found
    - Errors and exceptions
  - Log location depends on server configuration (usually `/var/log/apache2/error.log` or `/var/log/nginx/error.log`)

## Configuration Validation

The plugin validates:

- API URL is required for Custom provider
- Temperature must be between 0.0 and 2.0
- API Timeout must be a valid number
- Max File Size must be a valid number
- At least one department rule must be configured (enforced during processing, not in form)

## User-Facing Behavior

- Configuration is saved through the standard osTicket plugin configuration interface
- Changes take effect immediately after saving
- Invalid configurations show error messages
- Some settings (like API URL) are automatically set based on provider selection

## Configuration Location

Access configuration via:
- **Admin Panel → Manage → Plugins**
- Find "AI Auto Department Transfer"
- Click on the plugin name to open configuration

## Notes

- API key is stored securely in plugin configuration
- Model selection UI switches between dropdown (OpenAI) and text input (Custom) automatically
- Department rules are configured in a separate table interface (see Department Rules Configuration feature)
- All configuration values persist across plugin updates

