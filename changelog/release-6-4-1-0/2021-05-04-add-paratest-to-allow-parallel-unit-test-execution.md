---
title: Add paratest to allow parallel unit test execution
issue: NEXT-14684
---
# Core
* Added paratest testsuite
* Added phpunit test group `skip-paratest` to declare tests that don't work with paratest
* Changed the platform to make it possible to run unit tests without a template
  * Added `config/bundles.php`
  * Added `bin/shopware`
  * Added bundle `Shopware\Core\DevOps\DevOps`
  * Added `System*Commands` from production template
  * Changed `src/Core/TestBootstrap.php` to automatically initialize the database with the `system:install` command
  * Added `.env.dist`
  * Added minimal docker-compose.yml, which should sufficient for unit tests
