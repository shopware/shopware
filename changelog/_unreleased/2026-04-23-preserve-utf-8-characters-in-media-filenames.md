---
title: Preserve UTF-8 characters in media filenames
author: Dennis Menken
author_github: @dennismenken
---
# Core
* Changed filename sanitization in `Shopware\Core\Content\Media\Api\MediaUploadController` and `Shopware\Core\Content\Media\Subscriber\MediaCreationSubscriber` to preserve UTF-8 characters such as umlauts. The previous byte-range regex `/[\x00-\x1F\x7F-\xFF]/` stripped every byte above `0x7F`, which destroyed multi-byte UTF-8 sequences (e.g. `Bäume.pdf` was saved as `Bme.pdf`). The sanitization now uses the Unicode-aware pattern `/\p{C}/u`, which removes Unicode control and format characters (categories `Cc`, `Cf`, `Cs`, `Co`, `Cn`) while keeping regular letters intact.
