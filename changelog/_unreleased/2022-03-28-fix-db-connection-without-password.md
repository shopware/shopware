---
title: Fix database connection without password.
issue: ?
author: Daniel Sturm
author_github: dsturm
---
# Core
* Removes the check against the password in `DatabaseConnectionInformation::asDsn` to fix a connection error if no password is needed.
