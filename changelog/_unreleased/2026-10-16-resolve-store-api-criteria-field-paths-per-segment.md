---
title: Resolve Store API criteria field paths per path segment
---
# Core
* Changed Store API criteria field paths (filters, sortings, and aggregations) to resolve per path segment. A path that uses `extensions` as a whole segment keeps working; a segment that only contains the text `extensions` is treated literally and rejected when it is not a real field. Integrations that use valid field paths are unaffected.
