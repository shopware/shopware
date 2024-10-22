---
title: Improve dal:validate command
issue: NEXT-0000
flag: 
author: Raphaël HOMANN
author_email: raph@e-frogg.com
author_github: raphael-homann
---
# Core
* Added an option `check-unregistered-tables` to `dal:validate` command
* added an event `DefinitionValidatorViolationsFilterEvent` to be able to reduce violations after check
