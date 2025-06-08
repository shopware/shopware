---
title: Fix session handling on logouts
issue: NEXT-13664
author: Timo Altholtmann
---
# Storefront
*  Changed behaviour of the setting `invalidateSessionOnLogOut` in `loginRegistration.xml` which can be found under settings -> login / registration.
* The session of the user now gets invalidated on every logout, regardless of the value of this setting. This settings controls only, if the cart gets saved on logout.
___
