---
title: Fix 'Malformed UTF-8 characters, possibly incorrectly encoded' Error
issue: NEXT-19835
author: Alessandro Aussems
author_email: me@alessandroaussems.be
---
# Core
* Add `createResponse` in `src/Core/Framework/Api/Controller/SyncController.php` that adds the **JSON_INVALID_UTF8_SUBSTITUTE** encoding option to the response
