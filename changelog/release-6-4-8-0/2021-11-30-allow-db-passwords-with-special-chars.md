---
title: Allow special chars in DB passwords during installation
issue: NEXT-19088
---
# Core
* Changed `\Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation` to url_encode the database password, thus allowing special characters in the password.
