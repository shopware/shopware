---
title: Remove unneeded CSS
issue: NEXT-29246
---
# Storefront
* Removed unused CSS for `.account-item-children` since it was replaced with `.line-item` in v6.5.0.
* Removed SCSS file `app/storefront/src/scss/page/account/_aside.scss` because the account aside is not shown on mobile and the styling is never used.
* Removed SCSS file `app/storefront/src/scss/page/account/_login.scss` and replaced it with utility classes in `views/storefront/page/account/register/index.html.twig`.
* Removed SCSS file `app/storefront/src/scss/page/account/_profile.scss` and replaced it with utility classes in `views/storefront/page/account/profile/index.html.twig`.
* Removed SCSS file `app/storefront/src/scss/page/contact/_contact.scss` because the styling was unused.
* Removed SCSS file `app/storefront/src/scss/page/newsletter/_newsletter.scss` because the styling was unused.
