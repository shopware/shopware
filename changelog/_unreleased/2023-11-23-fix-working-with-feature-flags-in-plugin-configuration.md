---
title: Fix working with Feature Flags in Plugin Configuration
issue: -
author: Rafael Kraut
author_email: 14234815+RafaelKr@users.noreply.github.com
author_github: RafaelKr
---
# Core
* Ensure `Shopware\Core\System\SystemConfig\Service\ConfigurationService::getConfiguration()` returns sequentially
  indexed Arrays which are reliably encoded as Arrays instead of sometimes resulting in Objects when using json_encode.
