---
title: Fix working with Feature Flags in Plugin Configuration
issue: NEXT-33717
author: Rafael Kraut
author_email: 14234815+RafaelKr@users.noreply.github.com
author_github: RafaelKr
---
# Core
* Changed `Shopware\Core\System\SystemConfig\Service\ConfigurationService::getConfiguration()` to ensure that it returns sequentially
  indexed Arrays which are reliably encoded as Arrays instead of sometimes resulting in Objects when using json_encode.
