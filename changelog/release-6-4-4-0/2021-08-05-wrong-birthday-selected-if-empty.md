---
title: Wrong birthday selected if empty
issue: NEXT-16645
author: Stephan Franck
author_email: stephan@vierpunkt.de
author_github: @stephan4p
---
# Storefront
* Changed twig template `src/Storefront/Resources/views/storefront/page/account/profile/personal.html.twig` to not preselect current day when no birthdate is provided
