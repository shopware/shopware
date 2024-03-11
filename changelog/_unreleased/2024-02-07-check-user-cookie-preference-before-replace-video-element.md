---
title: Check user cookie preference before replace video element
issue: NEXT-33503
author: Alexandru Dumea
author_email: a.dumea@shopware.com
author_github: Alexandru Dumea
---
# Storefront
* Added a new method `checkConsentAndReplaceVideo` to the `cms-gdpr-video-element.plugin.js` which checks for the user cookie preference before replacing video element.
