[titleEn]: <>(Main language files changed and renamed)

Until now, Shopware's main snippet files were the `messages.<locale>.json` files, which included all the storefront snippets.
Because these files are located in the storefront, Shopware had an unintended dependency on it. So we renamed some files
and made changes to the behaviour of:

- Storefront snippet files `messages.<locale>.json` were renamed to `storefront.<locale>.json`
- Core/document snippet files `core.<locale>.json` were renamed to `messages.<locale>.json`
- Core snippet files' `isBase` now is `true` instead `false`, while storefront's `isBase` was changed vice versa

This rename was made necessary due to Symfony's need for a basic file (using the `messages` domain) when constructing
catalogues.
