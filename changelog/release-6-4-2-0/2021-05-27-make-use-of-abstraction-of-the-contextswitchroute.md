---
title: Make use of abstraction of the ContextSwitchRoute
issue: NEXT-15477
author: Steffen Beisenherz
author_email: s.beisenherz@kellerkinder.de 
author_github: Sironheart
___
# Storefront
*  Changed the AccountOrderController so it now does not require the shopware implementation of the ContextSwitchRoute 
   anymore, instead accepting any implementation of the AbstractContextSwitchRoute
