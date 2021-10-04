---
title: Use question helper for passwords in commands
issue: NEXT-17106
author: mynameisbogdan
author_email: mynameisbogdan@protonmail.com
author_github: mynameisbogdan
---
# Core
* Changed command `store:login` to ask for password if none is supplied by option.
* Changed command `user:create` to add validation in question for empty passwords to fix max attempts functionality.
* Changed command `user:change-password` to add validation in question for empty passwords to fix max attempts functionality.
