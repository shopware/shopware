---
title: Implement admin translation via AdminExtensionSDK
issue: NEXT-22761
author: Marcel Brode
author_email: m.brode@shopware.com
author_github: Marcel Brode
---
# Core
* Added `\Shopware\Core\System\Snippet\Administration\AppAdministrationSnippetEntity` which stores all snippets for one app for one locale.
* Added `\Shopware\Core\Framework\App\Lifecycle\Persister\AppAdministrationSnippetPersister` in order to persist snippets from apps.
* Changed `\Shopware\Core\Framework\App\Lifecycle\AppLoader` to persist snippets from apps via the `\Shopware\Core\Framework\App\Lifecycle\Persister\AppAdministrationSnippetPersister`.
* Added the possibility to provide snippet files for the administration via app.
  * Snippet files must be given in `yourAppDirectory/Resources/administration/snippet/[localeCode].json`.
  * Snippets for `en-GB` must always be given.
___
# Administration
* Changed `locale.factory` to convert it to typescript.
* Changed `\Shopware\Administration\Snippet\SnippetFinder` to also load snippets from active apps.
