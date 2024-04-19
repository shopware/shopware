---
title: Fixed elastic search re-throwing exceptions
issue: NEXT-35109
author: Jozsef Damokos
author_email: j.damokos@shopware.com
author_github: jozsefdamokos
---
# Core
* Changed the way exceptions are handled in `ElasticsearchEntitySearcher` to prevent re-throwing the exceptions from the decorated class.
