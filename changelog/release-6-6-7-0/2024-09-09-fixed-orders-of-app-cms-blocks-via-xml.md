---
title: Fixed orders of app-cms blocks via xml
issue: NEXT-37767
author: Marcel Brode
author_email: m.brode@shopware.com
author_github: @Marcel Brode
---
# Core
* Changed `src/Core/Framework/App/Cms/Xml/Block.php` to add a position attribute to the JSON generated from XML files, reflecting the order of the CMS slots within the file
___
# Administration
* Changed `app-cms.service.js` to sort the blocks by their position attribute when generating Vue components for app CMS blocks
___
# Upgrade Information
## Correct order of app-cms blocks via xml files
The order of app CMS blocks is now correctly applied when using XML files to define the blocks. This is achieved by using a position attribute in the JSON generated from the XML file, which reflects the order of the CMS slots within the file. Since it's not possible to determine the correct order of CMS blocks that have already been loaded into the database, this change will only affect newly loaded blocks.

To ensure the correct order is applied, you should consider to reinstall apps that provide app CMS blocks.
