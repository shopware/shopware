---
title:              Use 'birthdayFieldRequired' Config In 'My Account'
issue:              NEXT-31979
author:             Alessandro Aussems
author_email:       me@alessandroaussems.be
author_github:      @alessandroaussems
---

# Storefront
* Changed `views/storefront/page/account/profile/personal.html.twig` to use `config('core.loginRegistration.birthdayFieldRequired')` like it's used in `views/storefront/component/address/address-personal.html.twig` to make sure the setting is also taking into consideration the 'My Account' section
