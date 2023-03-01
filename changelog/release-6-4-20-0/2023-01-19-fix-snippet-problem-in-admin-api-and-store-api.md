---
title: Fix snippet problem in Admin API and Store API
issue: NEXT-24678
---
# Core
* Changed method `\Shopware\Core\System\Snippet\SnippetService::getStorefrontSnippets` to add 4th optional parameter `$salesChannelId` to indicate which sales channel should we get the snippets
* Changed method `\Shopware\Core\System\Snippet\SnippetService::getUnusedThemes` to add 2nd parameter `$usingThemes` to filter out unused themes
* Deprecated method `\Shopware\Core\System\Snippet\SnippetService::getUnusedThemes` as we will change it to private method from v6.6.0
* Changed method `\Shopware\Core\System\Snippet\SnippetService::fetchSnippetsFromDatabase` to add second parameter `$usingThemes` as we want to fetch only snippets of using themes in cloud
