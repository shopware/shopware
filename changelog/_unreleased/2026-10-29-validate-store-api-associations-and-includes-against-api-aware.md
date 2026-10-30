---
title: Validate Store API associations and includes against ApiAware
---
# Core
* Changed API responses and Store API searches to consistently respect field visibility restrictions. Validation errors with code `FRAMEWORK__ACCESSOR_NOT_ALLOWED` now use HTTP status 400 instead of 500.
* Changed `services.store` in extensions and app scripts to only load associated entities exposed through the Store API. App permissions do not grant additional access to protected associations through `services.store`.
