---
title: Add error on unstoppable submit events, that should be handled by form-ajax-submit plugin
issue: NEXT-37427
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Storefront
* Add check and exception to not handle submit events, that are not cancable
___
# Upgrade Information

See `preventDefault` [documentation](https://developer.mozilla.org/en-US/docs/Web/API/Event/preventDefault#notes), that it will fail to stop non-`cancelable`.
Therefore do event dispatching by ensuring you have the right parameters in the event constructor:

```
form.dispatchEvent(new Event('submit', { cancelable: true }));
```
