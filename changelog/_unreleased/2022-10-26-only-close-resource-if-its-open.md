---
title: Only close resource if it's open
issue: NEXT-23947
author: Steven de Vries
author_email: info@stevendevries.nl
author_github: @StevendeVries
---
# Core
*  Changed method `copy()` in `src/Core/Framework/Plugin/Util/AssetService.php` to check if it's still a resource before closing.
