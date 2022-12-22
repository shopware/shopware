---
title: Limit URL upload file size
issue: NEXT-23679
---
# Core
* Added parameter `shopware.media.url_upload_max_size` to limit the maximum file size of media file uploads via URL. 
* Deprecated property `enableUrlUploadFeature` in class `\Shopware\Core\Content\Media\File\FileFetcher`, it will be private in 6.5.0
* Deprecated property `enableUrlValidation` in class `\Shopware\Core\Content\Media\File\FileFetcher`, it will be private in 6.5.0
___
# Upgrade Information
## Limit remote URL file upload max file size
By default, there is no limit on how large a file is allowed to be when using the URL upload feature. The new parameter
`shopware.media.url_upload_max_size` can be used to limit the maximum file size. The values can be written in bytes or 
in a human-readable format like: 1mb, 512kb, 2gb. The default is 0 (unlimited). 
