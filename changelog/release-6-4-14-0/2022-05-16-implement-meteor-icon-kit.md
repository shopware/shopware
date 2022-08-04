---
title: Implement meteor-icon-kit
issue: NEXT-17235
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Added `@shopware-ag/meteor-icon-kit` in version `^2.0.0`
* Deprecated all existing icon names
* Deprecated all default spaces around existing icons
___
# Upgrade Information
## Replacing old icons
___
# Next Major Version Changes
## Removal of old icons:
* Replace any old icon your integration uses with its successor. A mapping can be found here `src/Administration/Resources/app/administration/src/app/component/base/sw-icon/legacy-icon-mapping.js`.
* The object keys of the json file are the legacy icons. The values the replacement.
* In the next major the icons have will have no space around them by default. This could eventually lead to bigger looking icons in some places. If this is the case you need to adjust the styling with CSS so that it matches your wanted look.

### Example:
Before:

```html
<sw-icon name="default-object-image"/>
```

After:
```html
<sw-icon name="regular-image"/>
```
