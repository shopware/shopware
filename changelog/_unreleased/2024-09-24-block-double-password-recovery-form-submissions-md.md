---
title: Block double password recovery form submissions
issue: NEXT-38520
author: Bart Vanderstukken
author_email: bart.vanderstukken@meteor.be
author_github: @sneakyvv
---
# Storefront
* Changed `Resources/views/storefront/page/account/profile/recover-password.html.twig` and added `data-form-submit-loader` to block double password recovery form submissions by disabling the submit button after the first click.
