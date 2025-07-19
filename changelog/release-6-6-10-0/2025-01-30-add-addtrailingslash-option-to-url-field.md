---
title: Add addTrailingSlash option to url field
issue: NEXT-21017
author: Iván Tajes Vidal
author_email: tajespasarela@gmail.com
author_github: @Iván Tajes Vidal
---
# Administration
* Added `addTrailingSlash` option to the `sw-url-field` component.
___
# Upgrade Information

## Added `addTrailingSlash` option to the `sw-url-field` component
This option allows you to add a trailing slash to the URL and adds it to the value if it is missing.
The option is disabled by default.

### Example
```html
<sw-url-field v-model:value="currentValue" addTrailingSlash />
```
