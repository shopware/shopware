---
title: Support php syntax in twig
issue: NEXT-18350
---
# Core
* Added `squirrelphp/twig-php-syntax` for php syntax support in twig templates
* Renamed `src/Core/Framework/Adapter/Twig/InstanceOfExtension.php` to `src/Core/Framework/Adapter/Twig/Extension/InstanceOfExtension.php`
* Renamed `src/Core/Framework/Adapter/Twig/InheritanceExtension.php` to `src/Core/Framework/Adapter/Twig/Extension/NodeExtension.php
* Added `src/Core/Framework/Adapter/Twig/Node/ReturnNode.php`, which allows return statements in twig templates
