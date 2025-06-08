---
title: Add sync theme compile CLI option
issue: NEXT-34730
---
# Core
* Changed `theme:compile` and `theme:change ` command to accept `--sync` option to compile themes synchronously. The `--sync` option is useful for CI/CD pipelines, when at runtime themes should be compiled async, but during the build process you want sync generation.
___
# Upgrade Information
## Sync option for CLI theme commands

The `theme:compile` and `theme:change ` command now accept `--sync` option to compile themes synchronously. The `--sync` option is useful for CI/CD pipelines, when at runtime themes should be compiled async, but during the build process you want sync generation.