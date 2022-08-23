---
title: Add option to dump env vars to env.local.php file
issue: NEXT-21580
---
# Core
* Added `dotenv:dump` command to the container.
* Added `--dump-env` option to `system:setup` command, to allow dumping env vars to `env.local.php` file.
___ 
# Upgrade Information
## Dump env vars
You can now dump the env vars to a optimized `env.local.php` file by running `bin/console system:setup --dump-env` or `bin/console dotenv:dump --env {APP_ENV}` command.
For more information on the `env.local.php` file, see the [symfony docs](https://symfony.com/doc/current/configuration.html#configuring-environment-variables-in-production).
