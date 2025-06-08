---
title: Increased session cookie lifetime in the installer
issue: NEXT-18651
author: Dennis König
author_email: dennis@mkx.berlin 
author_github: denniskoenig
---
# Core
* Changed the session cookie lifetime of the installer to be 30 minutes (1800 seconds) instead of 10 minutes (600 seconds) to prevent issues if the installer takes more than 10 minutes
