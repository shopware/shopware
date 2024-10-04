---
title: Removing ratingSuccess variable in twig
issue: NEXT-0000
author: Joschi
author_email: joschi.mehta@heptacom.de
author_github: @NinjaArmy
---
# Storefront
* Removed ratingSuccess variable in `Resources/views/storefront/component/review/review.html.twig` to display success messages. Variable comes from the request object, which is set by the controller.
