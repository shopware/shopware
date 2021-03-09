---
title: Add parent and position field to Module
issue: NEXT-13797
---
# Core
* Changed number of occurrences `module` elements in `admin` section in app manifests from one to unlimited
* Added optional field `parent` to `module` elements
* Added optional field `position` to `module` elements
* Deprecated field `parent` in `module`. it will be required in future version.
