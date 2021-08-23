---
title: Html Purifier respect umask
issue: NEXT-16434
author: Ingo Walther
author_email: ingowalther@iwebspace.net
author_github: ingowalther
---
# Storefront
* Added `$config->set('Cache.SerializerPermissions', 0775 & ~umask());` to  `src/Core/Framework/Util/HtmlSanitizer.php` (Mode 0775: Owner can read, write and execute, Group can read, write and execute, Everyone who is not in the group, and not the owner, can read and execute)
