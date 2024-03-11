---
title: change import export factory exceptions
issue: NEXT-31188
author: Malte Janz
author_email: m.janz@shopware.com
author_github: MalteJanz
---
# Core
* Changed method `create` of `Content/ImportExport/ImportExportFactory.php` to throw a `ProfileNotFoundException` if the profile is missing on the log.
* Changed method `create` of `Content/ImportExport/ImportExportFactory.php` to throw `ProcessingException` instead of `RuntimeException` if a factory can't be found.
