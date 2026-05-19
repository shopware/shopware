---
title: add nl2br filter to customer review content variable
issue: #7719
author: Marvin Rewer
author_email: marvin.rewer@t-online.de
author_github: marvn-r3
---
# Storefront
* Changed `{{ review.content }}` variable in `storefront/component/review/review-item.html.twig` and added Twig filter `|nl2br` to allow customer to properly structure the review
