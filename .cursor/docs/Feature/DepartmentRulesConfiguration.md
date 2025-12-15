# Department Rules Configuration

## Overview

Department transfer rules define which keywords trigger transfers to specific departments. Rules are configured through a user-friendly table interface in the plugin settings.

## Rule Structure

Each rule consists of:

- **Department**: Selected from a dropdown of active departments
- **Keywords**: Comma or semicolon-separated list of keywords to match

## Configuration Interface

The rules are managed through a dynamic table interface:

- **Add Rule**: Button to add a new rule row
- **Remove**: Button on each row to delete that rule
- **Department Dropdown**: Shows all active departments
- **Keywords Field**: Text area for entering keywords

### Empty State

When no rules are configured, the table shows a message: "No rules configured. Click 'Add Rule' to create one."

## Keyword Format

- **Separators**: Keywords can be separated by commas (`,`) or semicolons (`;`)
- **Whitespace**: Spaces after separators are automatically trimmed
- **Case Sensitivity**: Matching is case-insensitive
- **Matching Type**: Partial text matching (keyword can appear anywhere in content)
- **Multiple Keywords**: Each department can have multiple keywords

## Matching Behavior

When analyzing a ticket:

1. All keywords from all rules are checked against ticket content
2. If any keyword from a rule matches, that department is considered a match
3. Multiple keywords matching the same department increase that department's match count
4. Departments are sorted by number of matched keywords (most matches first)
5. Only active departments are considered

## Storage Format

- Rules are stored as JSON array in the configuration
- Each rule object contains:
  - `dept_id`: Department ID (integer)
  - `keywords`: Keyword string (comma/semicolon-separated)
- Empty or invalid rules are automatically filtered out

## User-Facing Behavior

- Rules are saved when the plugin configuration form is submitted
- Changes take effect immediately after saving
- Rules are validated: both department and keywords must be provided
- The interface automatically updates the hidden JSON field as rules are added/removed

## Example Configuration

| Department | Keywords |
|-----------|----------|
| Technical Support | password, login, authentication, forgot password |
| Billing | payment, invoice, billing, refund, subscription |
| Bug Reports | bug, error, crash, not working, broken |

## Notes

- At least one rule must be configured for the plugin to function
- Rules are checked in order of match count (not rule creation order)
- Keywords are matched against the combined content of subject, body, and all processed attachments

