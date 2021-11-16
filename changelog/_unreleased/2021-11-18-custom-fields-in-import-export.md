---
title: Custom fields in import-export
issue: NEXT-18304
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Core
* Added `Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\CustomFieldsSerializer` for de/serializing instances of `CustomField`
___
# Administration
* Changed `sw-import-export-entity-path-select` to include options for specific custom fields in mapping of import/export profiles
