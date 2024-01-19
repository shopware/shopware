---
title: Improved error handling for media file renamings if the provided name is too long
issue: NEXT-30951
author: Krzykawski
author_email: m.krzykawski@shopware.com
author_github: Krzykawski
---
# Core
* Added file name length validation to `Shopware\Core\Content\Media\File\FileNameValidator`
* Added static function `fileNameTooLong` to `Shopware\Core\Content\Media\MediaException`
___
# Administration
* Added improved error message if provided file name is too long for an update in media
