[titleEn]: <>(Refactoring of media pathnames)

Media file pathname generation got refactored. All major information about path and filename can now be adjusted through the `\Shopware\Core\Content\Media\Pathname\PathnameStrategy\PathnameStrategyInterface`.

Additionally the default strategy changed to incorporate the id instead of the filename. This measure was taken for security concerns. If you need the old, by filename, behaviour reconfigure the default strategy in your local configuration (example in `/var/www/swag/shopware-development/platform/src/Core/Framework/Resources/config/packages/shopware.yaml`) to use `filename` instead of `id` as the `shopware.cdn.strategy`.   
