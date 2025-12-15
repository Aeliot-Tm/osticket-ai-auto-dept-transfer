# Automatic Ticket Transfer

## Overview

The plugin automatically analyzes and transfers new tickets to appropriate departments when they are created, based on keyword matching rules and AI-powered selection.

## Business Logic

When a new ticket is created in osTicket:

1. The plugin intercepts the ticket creation event
2. Extracts all content from the ticket (subject, message body, and attachments)
3. Searches for keywords matching configured department rules
4. Determines the appropriate target department:
   - If one department matches: transfers immediately
   - If multiple departments match: uses AI to select the best match
   - If no matches: logs a note but does not transfer
5. Performs the transfer and adds an internal note explaining the action

## Configuration

- **Auto-transfer on ticket creation**: Toggle to enable/disable automatic processing (default: enabled)
- Requires valid API configuration (API key, model, URL)
- Requires at least one department rule to be configured

## User-Facing Behavior

- Transfers happen automatically in the background when tickets are created
- Each transfer operation is logged as an internal note with:
  - Source and target department names
  - Reason for transfer (matched keywords or AI reasoning)
  - Optional: analyzed file contents (if enabled)
  - List of ignored files with reasons (if any)
- If transfer cannot be performed, a note is still added explaining why
- The plugin respects existing osTicket permissions and department access controls

## Notes

- Automatic transfer only occurs on ticket creation, not on updates
- The plugin checks if the ticket is already in the target department before transferring
- Only active departments are considered for transfers

