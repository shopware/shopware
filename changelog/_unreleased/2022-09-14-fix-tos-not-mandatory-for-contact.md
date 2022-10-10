---
title: Checkbox for Contact/Newsletter: agreeing
issue: NEXT-23192
author: jonas-sfx
author_email: jonas@sfxonline.de
author_github: jonas-sfx
---
# Storefront
* Changed `src/Storefront/Resources/snippet/de_DE/storefront.de-DE.json` added `contact.privacyNotice`
* Changed `src/Storefront/Resources/snippet/en_GB/storefront.en-GB.json` added `contact.privacyNotice`
* Changed `src/Storefront/Resources/views/storefront/element/cms-element-form/form-components/cms-element-form-privacy.html.twig` to use the new snippet if needed, but still show the old snippet by default
* Changed `src/Storefront/Resources/views/storefront/element/cms-element-form/form-types/contact-form.html.twig` to include the privacy form without the Terms of service
