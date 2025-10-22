---
title: Added phpstan rule ensuring that all attribute classes are final
---
# Core
* Added phpstan rule ensuring that all attribute classes are final.
* Added `final` to `\Shopware\Core\Framework\Event\IsFlowEventAware` attribute class.
___
# Upgrade Information
## IsFlowEventAware class is now final
If you have extended it, you need to adjust your code to reflect the changes.
