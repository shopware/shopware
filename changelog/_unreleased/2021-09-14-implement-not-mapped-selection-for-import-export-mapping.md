---
title: Implement not mapped selection for import export mapping
issue: NEXT-17089
flag: FEATURE_NEXT_15998
author: Malte Janz
author_email: m.janz@shopware.com 
author_github: Malte Janz
___
# Core
* Changed `set` method on `Shopware\Core\Content\ImportExport\Processing\Mapping\MappingCollection` to not override mappings with a key of an empty string.
___
# Administration
* Changed key value of the mapping to an empty string in all cases where nothing is selected in `sw-import-export-entity-path-select` (it was 'null' in some cases before).
* Added 'not mapped' option to `sw-import-export-entity-path-select` which is selected if the mapping key value is an empty string.
