---
title: Prevent context changes affect outside of the sync service usage
issue: NEXT-17522
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Changed `SyncService::sync` to clone the context before using it so internally used extensions are not affecting later usage of the same context object
