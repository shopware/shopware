---
title: Fix issue with overriden product export results
issue: NEXT-19540
---

# Core
* Added an 'isRunning' entry to the productExportEntity
* Changed the ProductExportGenerateTaskHandler and ProductExportPartialGenerationHandler to check if an export is already running before starting another instance.
