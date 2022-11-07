---
title: Mark DemoData and StaticAnalysis namespaces as internal
issue: NEXT-23541
---
# Core
* Deprecated all classes in `Shopware\Core\DevOps\StaticAnalyze` and `Shopware\Core\DevOps\DemoData` namespaces, those classes will be internal in v6.5.0.0.
* Deprecated `\Shopware\Core\Migration\Traits\MigrationUntouchedDbTestTrait`, this trait will be removed, use `\Shopware\Core\Migration\Test\MigrationUntouchedDbTestTrait` instead.
