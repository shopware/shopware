---
title: Fix deprecated method in sw-url-field template
issue: NEXT-25003
author: Marcel Hakvoort
author_email: m.hakvoort@shopware.com
author_github: celha
---
# Administration
* Remove deprecated input event handler in `administration/src/app/component/form/sw-url-field/sw-url-field.html.twig`. It now only handles blur events.
