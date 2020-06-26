UPGRADE FROM 6.2.x to 6.3
=======================

Table of content
----------------

* [Core](#core)

Core
----

* The `\Shopware\Core\System\Snippet\Files\SnippetFileInterface` is deprecated, please provide your snippet files in the right directory with the right name so shopware is able to autoload them.
Take a look at the `Autoloading of Storefront snippets` section in this guide: `Docs/Resources/current/30-theme-guide/40-snippets.md`, for more information.
After that you are able to delete your implementation of the `SnippetFileInterface`.
