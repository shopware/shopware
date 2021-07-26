---
title: Html Purifier respect umask
author_email: ingowalther@iwebspace.net
author_github: ingowalther
---
# Storefront
* Add `$config->set('Cache.SerializerPermissions', 0775 & ~umask());` to  `src/Core/Framework/Util/HtmlSanitizer.php`
