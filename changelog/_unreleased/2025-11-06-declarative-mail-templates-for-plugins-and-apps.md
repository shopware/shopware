---
title: Declarative mail templates for plugins and apps
issue: NEXT-00000
author: shopware
author_email: info@shopware.com
author_github: shopware
---
# Core
* Added `Shopware\Core\Content\MailTemplate\MailTemplateXmlLoader` to load and validate `mail-templates.xml` files
* Added `Shopware\Core\Content\MailTemplate\MailTemplateLoader` to combine XML metadata with Twig template files from disk
* Added `Shopware\Core\Content\MailTemplate\MailTemplateSetPersister` to sync mail template types and templates to the database
* Added `Shopware\Core\Content\MailTemplate\Xml\MailTemplates` and `Shopware\Core\Content\MailTemplate\Xml\MailTemplate` DTOs for parsed XML representation
* Added XSD schema `src/Core/Content/MailTemplate/Schema/mail-templates-1.0.xsd` for mail template XML validation
* Added `Shopware\Core\Framework\App\Lifecycle\Persister\MailTemplatePersister` for app lifecycle integration
* Added mail template sync/remove to `Shopware\Core\Framework\Plugin\PluginLifecycleService` for plugin install/update/uninstall
* Added `Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\MailTemplateGenerator` for plugin scaffolding
