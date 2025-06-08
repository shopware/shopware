---
title: Fix remaining PHP 8.2 deprecations
issue: NEXT-29164
---
# Core
* Remove properties added in error to `SalesChannelContext`
* Added missing properties to entities so that dynamically created property deprecations are not raised
* Add getters and setters for new properties
