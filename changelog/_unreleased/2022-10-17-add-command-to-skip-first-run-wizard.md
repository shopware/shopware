---
title: Add command to skip first run wizard
issue: N/A
author: Melvin Achterhuis
author_email: melvin.achterhuis@gmail.com
author_github: melvinachterhuis
---
# Core
* Changed `Shopware/Core/Framework/DependencyInjection/store.xml` to inject new command
* Added `Shopware/Core/Framework/Store/Command/StoreSkipFirstRunWizard.php` to skip the first run wizard through CLI
