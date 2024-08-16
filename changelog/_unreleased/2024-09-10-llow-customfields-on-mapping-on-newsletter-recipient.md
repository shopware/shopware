---
title: llow customFields on mapping on newsletter_recipient
issue: NEXT-38241
---
### Core
- Changed method `subscribe` to allow `customFields` in request data bag in `src/Core/Content/Newsletter/SalesChannel/NewsletterSubscribeRoute.php`

### Administration
- Added `newsletter_recipient` to `$entityNameStore` const in `src/app/service/custom-field.service.js`
- Added method `loadCustomFieldSets` in `src/module/sw-newsletter-recipient/page/sw-newsletter-recipient-detail/index.js` to load custom field sets of newsletter_recipient
- Added new custom field renderer card in `src/module/sw-newsletter-recipient/page/sw-newsletter-recipient-detail/sw-newsletter-recipient-detail.html.twig` to render newsletter recipient custom fields
