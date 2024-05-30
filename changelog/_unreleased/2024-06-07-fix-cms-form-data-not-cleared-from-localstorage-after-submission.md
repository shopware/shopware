---
title: Fix cms form data not cleared from localstorage after submission
issue: 00000
author: Paik Paustian
author_email: mail@paik.dev
author_github: hype09
---
# Storefront
* Changed `FormCmsHandler` to reset form after successful AJAX submission.
* Changed `FormPreserverPlugin` to clear localstorage-cache on both `submit` and `reset` form events.
