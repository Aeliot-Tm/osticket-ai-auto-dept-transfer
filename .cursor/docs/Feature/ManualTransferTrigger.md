# Manual Transfer Trigger

## Overview

Staff members can manually trigger department transfer analysis for existing tickets through a button in the ticket view interface.

## Business Logic

When a staff member clicks the manual transfer button:

1. A confirmation dialog appears asking to proceed
2. The plugin analyzes the ticket content (same process as automatic transfer)
3. If a suitable department is found, the ticket is transferred
4. The user receives immediate feedback via notification
5. The page reloads to show the updated department

## UI Integration

- The "Auto Transfer Department" option appears in the "More" dropdown menu (three dots icon) in the ticket view
- The menu item is marked with an exchange icon (⇄)
- The option appears at the top of the dropdown list
- Button shows a loading state ("Analyzing...") during processing

## Access Control

- **Departments with Manual Transfer Button**: Configuration setting that controls visibility
  - If left empty: button is visible to all staff members from any department
  - If specific departments selected: button only appears for staff whose department is in the allowed list
- This setting only affects manual button visibility; automatic transfer works for all departments

## User-Facing Behavior

- Staff members see the button only if their department is allowed (or if no restrictions are set)
- Clicking the button shows a confirmation dialog
- After processing, a success or informational notification appears
- The page automatically reloads after 2 seconds to show the updated ticket state
- If transfer fails or no match is found, an informational message explains why

## Technical Notes

- Uses AJAX for asynchronous processing without full page reload during analysis
- Respects the same department rules and API configuration as automatic transfer
- Processing happens server-side; JavaScript handles UI feedback only

