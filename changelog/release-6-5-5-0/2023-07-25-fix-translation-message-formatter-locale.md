---
title: Fix translation MessageFormatter locale
issue: NEXT-19616
author: Dumka.pro
author_email: hello@dumka.pro
author_github: @dumka-pro
---
# Core
* Changed `\Shopware\Core\Framework\Adapter\Translation\Translator` to provide correct locale for translation message formatting: using shopware locale to format translation messages correctly. Get correct position for locale pluralization rules.
