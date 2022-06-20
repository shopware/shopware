---
title: Fix many and huge custom-field-sets by loading the fields asynchronous
issue: NEXT-21408
author: Robert Schönthal
author_email: robert.schoenthal@gmail.com
author_github: digitalkaoz
---
# Administration
* Changed Field-Set-Renderer to load only Fields for the current active tab `sw-custom-field-set-renderer`
* Changed all Modules using this component to initally only load the set without its fields
