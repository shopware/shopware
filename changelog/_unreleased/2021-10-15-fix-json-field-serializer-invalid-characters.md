---
title: Fix JsonFieldSerializer for invalid UTF8 characters
issue: NEXT-17698
author: Maximilian Ruesch
author_email: maximilian.ruesch@pickware.de
---
# Core
* The JsonFieldSerializer now ignores invalid UTF8 characters and throws more precise exceptions if it fails to encode a value.
