---
title: Don't validate plugin requirements for plugins managed by composer
issue: NEXT-22937
---
# Core
* Changed `\Shopware\Core\Framework\Plugin\Requirement\RequirementsValidator::validateRequirements()` to skip the validation if the plugin is managed by composer, as composer then already validated the requirements. 
