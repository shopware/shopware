---
title: Add a force option to the assets:install command
issue: NEXT-30143
---
# Core
* Added a `--force` option to the `assets:install` command. This will overwrite existing files on the remote filesystem regardless of the manifest file.
```
