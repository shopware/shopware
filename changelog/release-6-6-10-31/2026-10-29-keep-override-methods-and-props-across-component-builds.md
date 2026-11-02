---
title: Keep override methods and props across component builds
issue: 21768
author: Tim Theisinger
author_email: tim.theisinger@pickware.de
author_github: @timtheisinger
---
# Administration
* Changed `build` in `src/core/factory/async-component.factory.ts` to work on a copy of each override config, so that repeated `Component.build()` calls no longer remove methods, props or other options that an override defines from components built earlier.
  Components with three or more overrides, such as the base of a `Component.extend()` that is mounted after other builds, failed with `this.<method> is not a function` or lost props before. No changes to extensions are required.
