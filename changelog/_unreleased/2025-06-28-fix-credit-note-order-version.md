---
title: Fix order version for determining credit notes when creating credit notes
author: Justus Geramb
author_email: justus@devite.io
author_github: @jgeramb
---
# Core
* Uses the LIVE version of the order to determine the credit items and then creates a new version to maintain the document state.
