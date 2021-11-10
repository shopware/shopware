---
title: Separate generic errors in import summary
issue: NEXT-18234
flag: FEATURE_NEXT_8097
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Core
* Changed summary log structure in `ImportExport::logResults` to include an entry for generic other errors and log non-write related errors to said entry
___
# Administration
* Added block `sw_import_export_activity_result_modal_info_mainEntity_list_other_error` and elements in template of `sw-import-export-activity-result-modal` to also display number of generic other errors in summary
