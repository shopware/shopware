---
title: Update sw-url-input validation
issue: NEXT-16649
author: Benedikt Schulze Baek
author_email: b.schulze-baek@shopware.com
author_github: bschulzebaek
---
# Administration
* Changed `sw-url-field` to allow empty urls. These should be handled by the parent form instead.
* Deprecated the event `beforeDebounce` in `sw-url-field`. Use the event `input` instead.
* Deprecated the method `onInput` in `sw-url-field`. Use `onBlur()` instead.
* Deprecated the methods `onDebounceInput` and `handleInput` in `sw-url-field`. Use `checkInput()` instead.