---
title: fix installation with invalid admin user password
issue: NEXT-31427
---
# Core
* Changed the installation process to create admin user before shop config, so shop is not installed until password passes validation
