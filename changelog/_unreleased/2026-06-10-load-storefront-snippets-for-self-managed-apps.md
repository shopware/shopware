---
title: Load storefront snippets for self-managed apps
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com
author_github: @mstegmeyer
---
# Core
* Changed `Shopware\Core\System\Snippet\Files\SnippetFileLoader` to resolve snippet files of self-managed apps (e.g. services) through the app source resolver. Previously, the loader built a local path from `app.path`, which for services contains the service host URL instead of a directory, so their storefront snippets were silently never loaded.
