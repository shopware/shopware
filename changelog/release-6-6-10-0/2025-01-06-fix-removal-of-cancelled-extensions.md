---
title: Fix deletion of extensions with cancelled licenses
issue: NEXT-39821
author: Albert Scherman
author_github: @bird87za
---
# Core
* Changed the way deletion of extensions with cancelled licenses is handled. The cancellation flow now looks for the correct error type and proceeds as intended.
