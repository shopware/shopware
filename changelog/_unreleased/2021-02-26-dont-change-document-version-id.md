---
title:              Don't change the document versionId on document update
issue:              NEXT-13653
author_github:      @ssltg
---
# Core
* Added Migration `Core/Migration/V6_4/Migration1612442786ChangeVersionOfDocuments.php` to fix broken document versions.
* Changed `DocumentService` to repair broken document versions on the fly.
