---
title: AppSystem: replace sequences in manifest schema
issue: NEXT-14997
---
# Core
* Changed the content in file `src/Core/Framework/App/Manifest/Schema/manifest-1.0.xsd`:
    * Replaced xs:sequence with xs:choice
* Added `validateRequiredElements` method in `src/Core/Framework/App/Manifest/Xml/XmlElement.php`
* Added validation in these files:
    * `src/Core/Framework/App/Manifest/Xml/Admin.php`
    * `src/Core/Framework/App/Manifest/Xml/CustomFieldSet.php`
    * `src/Core/Framework/App/Manifest/Xml/Metadata.php`
    * `src/Core/Framework/App/Manifest/Xml/PaymentMethod.php`
