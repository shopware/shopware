---
title: Add cookie hash endpoint
issue: 9451
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# API
* Added new Store API endpoint `/store-api/cookie-hash` to get a hash representing the current cookie configuration
* Added `calculateCookieHash` method to `CookieService` to calculate a SHA-1 hash of all cookie groups and entries
* Added `CookieHash` struct to represent the cookie hash in API responses
