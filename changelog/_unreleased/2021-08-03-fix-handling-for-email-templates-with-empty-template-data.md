---
title: Fix handling for mail template variables without template data
issue: NEXT-16500
---
# Administration
* Added `hasTemplateData` to `sw-mail-template-detail` to check if the template has any data. 
* Changed the preview sidebar button in `sw-mail-template-detail` to be disabled if the template has no data.
* Changed the variables sidebar button in `sw-mail-template-detail` to be disabled if the template has no data.