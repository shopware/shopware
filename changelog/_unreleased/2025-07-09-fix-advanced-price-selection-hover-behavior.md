---
title: Fix advanced price selection hover behavior
issue: 9996
---

# Administration
* Fixed missing hover effects for "Use advanced selection" option in advanced pricing selection dropdown
* Added CSS hover styling for `sw-single-select-filtering__advanced-selection` class
* Enhanced user experience by providing consistent hover feedback for all advanced pricing selection options
___
# Upgrade Information

## Fix Advanced Price Selection Hover Behavior

The hover behavior for advanced price selection options has been improved. Previously, the "Use advanced selection" option didn't show hover effects when users moved their mouse over it. Now both the "Use advanced selection" and "Create new rule..." options properly highlight on hover, providing consistent visual feedback.

### Before
- Only "Create new rule..." showed hover effects
- "Use advanced selection" option had no visual feedback on hover

### After  
- Both "Use advanced selection" and "Create new rule..." options show hover effects
- Consistent visual feedback across all advanced pricing selection options