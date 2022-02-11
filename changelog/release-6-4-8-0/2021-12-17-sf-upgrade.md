---
title: Symfony 5.4 Upgrade
issue: NEXT-19222
---
# Administration
* Changed dependency version of `symfony/framework-bundle` from version `~5.3.0` to version `~5.4.0`
* Changed dependency version of `symfony/http-foundation` from version `~5.3.0` to version `~5.4.0`
* Changed dependency version of `symfony/mime` from version `~5.3.0` to version `~5.4.0`
* Changed dependency version of `symfony/routing` from version `~5.3.0` to version `~5.4.0`
___
# Core
* Added class `Shopware\Core\DevOps\System\Command\SyncComposerVersionCommand`
* Added new parameter `$router` to method `Shopware\Core\Framework\Api\Controller\AclController::__construct`
* Added return type `int` for method `Shopware\Core\Framework\Changelog\Command\ChangelogChangeCommand::execute`
* Added return type `int` for method `Shopware\Core\Framework\Changelog\Command\ChangelogCheckCommand::execute`
* Added return type `int` for method `Shopware\Core\Framework\Changelog\Command\ChangelogCreateCommand::execute`
* Added return type `bool` for method `Shopware\Core\Framework\Routing\Annotation\Since::allowArray`
* Added return type `ReturnNode` for method `Shopware\Core\Framework\Adapter\Twig\TokenParser\ReturnNodeTokenParser::parse`
* Changed dependency version of `symfony/routing` from version `~5.3.11` to version `~5.4.0`
* Changed dependency version of `symfony/monolog-bridge` from version `~5.3.7` to version `~5.4.0`
* Changed dependency version of `symfony/options-resolver` from version `~5.3.7` to version `~5.4.0`
* Changed dependency version of `symfony/phpunit-bridge` from version `~5.3.0` to version `~5.4.0`
* Changed dependency version of `symfony/process` from version `~5.3.12` to version `~5.4.0`
* Changed dependency version of `symfony/property-access` from version `~5.3.8` to version `~5.4.0`
* Changed dependency version of `symfony/property-info` from version `~5.3.8` to version `~5.4.0`
* Changed dependency version of `symfony/proxy-manager-bridge` from version `~5.3.0` to version `~5.4.0`
* Changed dependency version of `symfony/rate-limiter` from version `~5.3.11` to version `~5.4.0`
* Changed dependency version of `symfony/stopwatch` from version `~5.3.0` to version `~5.4.0`
* Changed dependency version of `symfony/security-core` from version `~5.3.11` to version `~5.4.0`
* Changed dependency version of `symfony/serializer` from version `~5.3.12` to version `~5.4.0`
* Changed dependency version of `symfony/messenger` from version `~5.3.11` to version `~5.4.0`
* Changed dependency version of `symfony/translation` from version `&gt;= 5.3.0 &lt; 5.3.7` to version `~5.4.0`
* Changed dependency version of `symfony/twig-bridge` from version `~5.3.11` to version `~5.4.0`
* Changed dependency version of `symfony/twig-bundle` from version `~5.3.10` to version `~5.4.0`
* Changed dependency version of `symfony/validator` from version `~5.3.12` to version `~5.4.0`
* Changed dependency version of `symfony/var-dumper` from version `~5.3.0` to version `~5.4.0`
* Changed dependency version of `symfony/var-exporter` from version `~5.3.11` to version `~5.4.0`
* Changed dependency version of `symfony/web-profiler-bundle` from version `~5.3.0` to version `~5.4.0`
* Changed dependency version of `symfony/mime` from version `~5.3.11` to version `~5.4.0`
* Changed dependency version of `symfony/http-foundation` from version `~5.3.11` to version `~5.4.0`
* Changed dependency version of `symfony/mailer` from version `~5.3.9` to version `~5.4.0`
* Changed dependency version of `symfony/console` from version `~5.3.11` to version `~5.4.0`
* Changed dependency version of `mbezhanov/faker-provider-collection` from version `~1.2.1` to version `~2.0.1`
* Changed dependency version of `phpunit/phpunit` from version `~9.5.2` to version `~9.5.6`
* Changed dependency version of `sensio/framework-extra-bundle` from version `5.5.6` to version `~6.2.1`
* Changed dependency version of `squirrelphp/twig-php-syntax` from version `1.6.0` to version `1.7.0`
* Changed dependency version of `symfony/asset` from version `~5.3.11` to version `~5.4.0`
* Changed dependency version of `symfony/browser-kit` from version `~5.3.0` to version `~5.4.0`
* Changed dependency version of `symfony/cache` from version `~5.3.12` to version `~5.4.0`
* Changed dependency version of `symfony/config` from version `~5.3.11` to version `~5.4.0`
* Changed dependency version of `symfony/debug-bundle` from version `~5.3.4` to version `~5.4.0`
* Changed dependency version of `symfony/inflector` from version `~5.3.11` to version `~5.4.0`
* Changed dependency version of `symfony/dependency-injection` from version `~5.3.11` to version `~5.4.0`
* Changed dependency version of `symfony/dom-crawler` from version `~5.3.0` to version `~5.4.0`
* Changed dependency version of `symfony/dotenv` from version `~5.3.10` to version `~5.4.0`
* Changed dependency version of `symfony/error-handler` from version `~5.3.11` to version `~5.4.0`
* Changed dependency version of `symfony/event-dispatcher` from version `~5.3.11` to version `~5.4.0`
* Changed dependency version of `symfony/filesystem` from version `~5.3.4` to version `~5.4.0`
* Changed dependency version of `symfony/finder` from version `~5.3.7` to version `~5.4.0`
* Changed dependency version of `symfony/framework-bundle` from version `~5.3.11` to version `~5.4.0`
* Changed dependency version of `symfony/http-kernel` from version `~5.3.12` to version `~5.4.0`
* Changed dependency version of `symfony/yaml` from version `~5.3.11` to version `~5.4.0`
___
# Docs
* Changed dependency version of `phpunit/phpunit` from version `~9.5.2` to version `~9.5.6`
* Changed dependency version of `symfony/config` from version `~5.3.0` to version `~5.4.0`
* Changed dependency version of `symfony/console` from version `~5.3.0` to version `~5.4.0`
* Changed dependency version of `symfony/dependency-injection` from version `~5.3.0` to version `~5.4.0`
* Changed dependency version of `symfony/filesystem` from version `~5.3.0` to version `~5.4.0`
* Changed dependency version of `symfony/finder` from version `~5.3.0` to version `~5.4.0`
* Changed dependency version of `symfony/http-kernel` from version `~5.3.0` to version `~5.4.0`
___
# Elasticsearch
* Changed dependency version of `phpunit/phpunit` from version `~9.5.2` to version `~9.5.6`
* Changed dependency version of `symfony/http-foundation` from version `~5.3.0` to version `~5.4.0`
* Changed dependency version of `symfony/messenger` from version `~5.3.0` to version `~5.4.0`
___
# Recovery
* Changed dependency version of `phpunit/phpunit` from version `^9.5` to version `~9.5.6`
___
# Storefront
* Changed dependency version of `symfony/security-csrf` from version `~5.3.0` to version `~5.4.0`
* Changed dependency version of `symfony/security-core` from version `~5.3.0` to version `~5.4.0`
* Changed dependency version of `symfony/routing` from version `~5.3.0` to version `~5.4.0`
* Changed dependency version of `symfony/polyfill-php80` from version `~1.23.0` to version `~1.23.1`
* Changed dependency version of `symfony/mime` from version `~5.3.0` to version `~5.4.0`
* Changed dependency version of `symfony/http-kernel` from version `~5.3.0` to version `~5.4.0`
* Changed dependency version of `symfony/http-foundation` from version `~5.3.0` to version `~5.4.0`
* Changed dependency version of `symfony/framework-bundle` from version `~5.3.0` to version `~5.4.0`
* Changed dependency version of `symfony/event-dispatcher` from version `~5.3.0` to version `~5.4.0`
* Changed dependency version of `symfony/dependency-injection` from version `~5.3.0` to version `~5.4.0`
* Changed dependency version of `symfony/css-selector` from version `^5.3` to version `~5.4.0`
* Changed dependency version of `symfony/console` from version `~5.3.0` to version `~5.4.0`
* Changed dependency version of `symfony/config` from version `~5.3.0` to version `~5.4.0`
* Changed dependency version of `symfony/cache` from version `~5.3.0` to version `~5.4.0`
* Changed dependency version of `phpunit/phpunit` from version `~9.5.2` to version `~9.5.6`
* Changed dependency version of `symfony/validator` from version `~5.3.0` to version `~5.4.0`
* Changed `Shopware\Storefront\Framework\Csrf\CsrfPlaceholderHandler` to use session factory to create a session when request doesn't have set one
___
