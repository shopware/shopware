---
title: Support the global flag for custom fields provided by apps
issue: NEXT-15872
---
# Core
* Changed the content in file `src/Core/Framework/App/Manifest/Schema/manifest-1.0.xsd`:
    * Added `global` attribute in custom-field-set element
* Changed `toEntityArray` and `parse` in `src/Core/Framework/App/Manifest/Xml/CustomFieldSet.php`
