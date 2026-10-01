---
title: Reset UpdatedByField for updates via API
issue: #11188
author: Max Stegmeyer
---
# Core
* Changed `UpdatedByFieldSerializer` to reset the `UpdatedBy` field when an object is updated via the API.
___
# Next Major Version Changes
## Updated By Field is cleared on API updates
In the next major version, the `UpdatedBy` field will be cleared when an object is updated via the API. This change ensures that the `UpdatedBy` field reflects the user who last modified the object through the API, rather than retaining the previous value.
