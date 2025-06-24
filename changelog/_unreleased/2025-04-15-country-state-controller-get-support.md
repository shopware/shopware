---
title: "CountryStateController: Support GET for /country/country-state-data and deprecate POST"
---
# Storefront
* The `CountryStateController` route `/country/country-state-data` now supports both GET and POST methods. The POST method is deprecated and will be removed in v6.8.0. The controller now reads the `countryId` from the query string instead of the request body. This change improves compatibility with HTTP caching and aligns with best practices for data retrieval routes.
