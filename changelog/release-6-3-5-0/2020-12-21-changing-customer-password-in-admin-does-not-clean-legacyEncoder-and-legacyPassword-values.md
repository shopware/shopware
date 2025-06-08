---
title: Changing customer password in admin does not clean legacyEncoder and legacyPassword values
issue: NEXT-10717
---
# Core
* Added `CustomerChangePasswordSubscriber` to listening to the customer written event when the password of the user has changed and the legacy values will be clean when the password of the user changed.
