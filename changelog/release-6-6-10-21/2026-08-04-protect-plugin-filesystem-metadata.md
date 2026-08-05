---
title: Protect plugin filesystem metadata
issue: #18930
---
# Core
* Changed plugin filesystem metadata to be read-only through the Admin API. The `plugin.path` and `plugin.managedByComposer` fields can no longer be created or changed through generic Admin API writes. Plugin discovery and extension management continue to maintain these values automatically.
