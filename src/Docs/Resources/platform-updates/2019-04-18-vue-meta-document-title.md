[titleEn]: <>(Document title using VueMeta)

We just implemented VueMeta 1.6.0 to shopware!

For now, it's only used to configure dynamic document titles in addition to the recently implemented favicons per module and will be added to every, already implemented module. Please be sure to add it to every new module! Additonally: Every Moduels `name` property has been refactored in the style of its identifier. (If its `sw-product-stream` the name now is `product-stream`.)

To provide more detailed information, we added the `this.$createTitle()` method to get an easily generated document title like `Customers | Shopware administration or Awesome Product | Products | Shopware administration`

Therefore every Module should set a `title` property with a snippet for its in the modules `index.js`:


```javascript
Module.register('sw-product', {
	name: 'sw-product.general.mainMenuItemGeneral',
	...
```

And also add the `metaInfo` property on **every pages** `index.js`:

```javascript
...
metaInfo() {
	return {
		title: this.$createTitle()
	};
},

computed: {
	...
```

Alternativly **for every detail page** add an identifier (e.g. using the placeholder mixin to ensure fallback-translations):

```javascript
...
mixins: [
	Mixin.getByName('placeholder')
],

metaInfo() {
	return {
		title: this.$createTitle(this.identifier)
	};
},

computed: {
	identifier() {
		return this.placeholder(this.product, 'name');
	},
},
...
```

The `$createTitle(String = null, ...String)` method uses the current page component to read its module title. The first parameter should be used in detail pages to also display its identifier like the product name or a full customer name to add it to the title. Every following parameter is fully optional and not in use yet, but if used would be added to the title in the same fashion.
