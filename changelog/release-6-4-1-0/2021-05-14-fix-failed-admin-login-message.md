---
title: Fix failed admin login message
issue: NEXT-15286
---
# Administration
* Changed `login.error-codes.js` to map InvalidGrant error code, to the invalid credentials error message, as the InvalidGrant error is thrown when the credentials are incorrect for the password grant.
