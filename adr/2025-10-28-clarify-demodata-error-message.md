---
title: Clarify
date: 2025-10-28
area: development
tags: command, demodata, framework
author: Adrian Bacani
---

## Context
For development purposes the `framework:demodata` command is useful for generating demodata
to work with.
However, when using the command out of the box, it gives a confusing message:
"Demo data command should only be used in production environment. You can provide the environment as follows `APP_ENV=prod bin/console framework:demodata`"
Since demo data is more associated with non-production environments.

## Decision
To give a more precise, clear and instructive message, the text got updated to:
"Demo data command requires APP_ENV=prod to run. Execute with: `APP_ENV=prod bin/console framework:demodata`"

## Consequences
This should prevent developers from getting confused by this error message, as it is
more neutral and clear.
