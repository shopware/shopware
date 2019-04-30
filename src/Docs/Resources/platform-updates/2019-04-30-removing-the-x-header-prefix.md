[titleEn]: <>(Removing the "X-" header prefix)

The leading "X-" in a header name has been deprecated for years (https://tools.ietf.org/html/rfc6648) and therefore should not be used anymore.

**Before:**

* `x-sw-context-token`
* `x-sw-access-key`
* `x-sw-language-id`
* `x-sw-inheritance`
* `x-sw-version-id`

**After:**

* `sw-context-token`
* `sw-access-key`
* `sw-language-id`
* `sw-inheritance`
* `sw-version-id`