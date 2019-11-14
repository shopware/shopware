[titleEn]: <>(Product streams)

Product streams are now released. With this feature you can filter products based on DAL fields in the admin and via API.

The filters start from the product entity and can be restricted for the admin with a blacklist.

This blacklist can be found in the module `app/service/product-stream-condition`. There you can add blacklist keywords for general or entity based purpose.

With a `ServiceProviderDecorator` you can extend the blacklists for the admin view with a plugin. An rule-builder based implementation can be found here: `platform/src/Administration/Resources/app/administration/src/app/decorator/condition-type-data-provider.js`.

If you extend the DAL, please check the admin for a possible new restriction with the blacklists. If the new DAL field should be used in the product streams, then translate the field. The translations can be found here: `platform/src/Administration/Resources/app/administration/src/module/sw-product-stream/snippet`.