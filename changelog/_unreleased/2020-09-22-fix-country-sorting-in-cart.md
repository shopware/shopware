---
title: Fix country sorting in cart
issue: NA
author: Huzaifa Mustafa
author_email: 24492269+zaifastafa@users.noreply.github.com 
author_github: zaifastafa
---
# Core
*  Added criteria in `\Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoader::getCountries` to first sort by 
position and then by name, so the countries are sorted properly.   
___
