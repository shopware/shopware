[titleEn]: <>(Favicons for each module)

It is now possible to define a favicon for each module in the administration. The favicon, which is just a .png version of the module-icon, is switched dynamically depending on what module is active at the moment. Currently there are 7 favicons that are located in `administration/static/img/favicon/modules/`.

When no favicon is defined for the module the default shopware signet is used as a fallback.

The favicon can be defined in the module registration.

```javascript
Module.register('sw-category', {
	name: 'Categories',
	icon: 'default-package-closed',
	favicon: 'icon-module-products.png'
});
```
