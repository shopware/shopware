---
title: Fix possible null reference error in computed product title
issue: NEXT-15699
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# API
* Added if statement checking for `$i18n` instance in computed product title, avoiding possible null reference errors 
