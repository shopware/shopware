---
title: Fix webpack cleanup config
issue: NEXT-27442
---

# Administration

* Changed `CleanWebpackPlugin` to clean up only `administration` folder in `src/Resources/public` directory to not delete other files.
