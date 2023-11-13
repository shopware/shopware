---
title: Make KernelTestBehaviour compatible with Symfony MailerAssertionsTrait
issue: NEXT-25500
author: Rafael Kraut
author_email: rk@vi-arise.com
author_github: RafaelKr
---
# Core
Change all `getKernel` and `getContainer` methods related to `KernelTestBehaviour` trait to `static`.

This makes it compatible with `Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait`.  
The MailerAssertionsTrait was introduces with Symfony 4.4  
https://symfony.com/blog/new-in-symfony-4-4-phpunit-assertions-for-email-messages

Also make the methods static in `Shopware\Tests\Bench` and `Shopware\Tests\Unit\Storefront\DependencyInjection\ReverseProxyCompilerPassTest`
___
# Upgrade Information
## Changes to KernelTestBehaviour trait
If you defined method `getKernel` and/or `getContainer` for anything using the  `Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour` trait, make them static:
```php
abstract protected function getKernel(): KernelInterface;

abstract protected function getContainer(): ContainerInterface;
```
to
```php
abstract protected static function getKernel(): KernelInterface;

abstract protected static function getContainer(): ContainerInterface;
```
