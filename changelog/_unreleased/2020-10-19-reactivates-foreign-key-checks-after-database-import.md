---
title: Reactivates foreign key checks after database import in CLI mode.  
issue:  
author: Manuel Josef  
author_email: manuel.josef89@gmail.com  
author_github: @mjosef89  
---
# Recovery
Changed method `importDatabase()` in `Shopware\Recovery\Install\Command\InstallCommand` to reactivate foreign key checks
after database import.
