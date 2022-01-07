---
title: Add configuration option to set a default tax rate
issue: NEXT-18176
author: Ramona Schwering
author_email: r.schwering@shopware.com
author_github: leichteckig
---
# Core
* Added migration to set a default tax rate based on current "Standard rate" or the first entry of tax list
___
# Administration
* Added the possibility to save a default tax rate via `systemConfigApiService`
* Added `sw-switch-field` field in `sw-settings-tax-detail` to offer the option to define a default tax rate
* Added a new column to display the default tax rate in `sw-settings-tax-list`, including the possibility to change it through inline edit.
* Removed duplicate event `inline-edit-cancel` in `sw-data-grid`
