---
title: Fix snippet default value sanitize
issue: NEXT-30489
author: Malte Janz
author_email: m.janz@shopware.com
author_github: MalteJanz
---
# Storefront
* Changed default snippet value for `checkout.confirmTermsReminder` from `<br>` to `<br />` to avoid changes during sanitization
* Changed all snippets that still used `data-toggle` or `data-target` and migrated them to `data-bs-toggle` and `data-bs-target` respectively
