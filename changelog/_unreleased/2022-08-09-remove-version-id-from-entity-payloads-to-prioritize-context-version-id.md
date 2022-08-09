---
title: Remove versionId from entity payloads to prioritize context versionId
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Changed entity payload normalization to remove values for primary key version fields so the context version is used in any case
