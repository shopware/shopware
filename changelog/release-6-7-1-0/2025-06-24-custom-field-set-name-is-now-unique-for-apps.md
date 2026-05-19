---
title: Custom field set name is now unique for apps
issue: #10738
author: Michael Telgmann
author_github: @mitelg
---

# Core

* Changed `src/Core/Framework/App/Manifest/Schema/manifest-3.0.xsd` to add a unique constraint to the `name` element of the `custom-field-set`.

___

# Upgrade Information

## Custom field set name is now unique for apps

The `name` element of the `custom-field-set` in the app manifest is now unique per app.
It should not be the case for your app anyway as it caused problems,
but you should check your app manifest and ensure that the `name` of the `custom-field-set` is unique.
