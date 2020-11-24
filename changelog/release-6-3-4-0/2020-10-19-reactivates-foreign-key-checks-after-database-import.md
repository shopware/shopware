---
title: Reactivates foreign key checks after database import in CLI mode.  
issue: NEXT-11749
author: Manuel Josef  
author_email: manuel.josef89@gmail.com  
author_github: @mjosef89  
---
# Core
* Changed method `importDatabase()` in `Shopware\Recovery\Install\Command\InstallCommand` to reactivate foreign key checks after database import.
