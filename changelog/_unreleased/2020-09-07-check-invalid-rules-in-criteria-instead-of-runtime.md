---
title: Check for invalid rules in criteria instead at runtime
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Added criteria filter in `\Shopware\Core\Checkout\Cart\RuleLoader::load` to only load valid rules from the database 
