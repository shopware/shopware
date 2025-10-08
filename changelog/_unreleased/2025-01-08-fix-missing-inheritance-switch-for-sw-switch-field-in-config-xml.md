---
title: Fix missing inheritance-switch for sw-switch-field in config.xml
issue: NEXT-12535
author: AI Assistant
author_email: assistant@example.com
author_github: @ai-assistant
---

# Administration
* Fixed missing inheritance-switch for `sw-switch-field` components in sales-channel-specific settings
* The `getInheritWrapperBind` method now properly provides label and helpText for all field types, including `sw-switch-field`
* This allows users to activate/deactivate `sw-switch-field` settings in sales-channel-specific configurations when they are disabled globally

## Problem
When using `<component name="sw-switch-field">` in config.xml files, the inheritance-switch was not visible in sales-channel-specific settings. If a `sw-switch-field` was deactivated for all sales channels, users couldn't activate it in sales-channel-specific settings because the inheritance-switch was missing.

## Solution
The issue was in the `getInheritWrapperBind` method in `sw-system-config` component. For components with map inheritance support (like `sw-switch-field`), it was returning an empty object `{}` instead of providing the label and helpText properties needed by the inheritance wrapper.

## Changes
* Modified `getInheritWrapperBind` method to always return label and helpText properties
* Added test case for `sw-switch-field` inheritance functionality
* Maintained backward compatibility for all existing field types

## Testing
* Added comprehensive test case in `sw-system-config.spec.js` to verify inheritance functionality
* Verified that the fix works for both `sw-switch-field` and regular field types
* Confirmed that inheritance switch is now visible and functional for `sw-switch-field` components
