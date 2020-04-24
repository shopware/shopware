[titleEn]: <>(Snippets)
[hash]: <>(article:developer_administration_snippets)

By default Shopware 6 uses the [Vue I18n](https://kazupon.github.io/vue-i18n/started.html#html) plugin in the `Administration` to deal with translation.
To define you custom snippets you have create files in the `json` format.

Normally you want to use snippets in you custom module. To keep things organized create a new directory named `snippet` inside 
you module directory `<plugin root>/src/Resources/app/administration/src/module/<your-module>/snippet`.
For each language you want to support you need a json file inside here, e.g. `de-DE.json` and of course `en-GB.json`.

By default Shopware 6 will collect those files automatically when your plugin was activate.

Each language then receives a nested object of translations, so let's have a look at an example `snippet/en-GB.json`:
```json
{
    "swag-bundle": {
        "nested": {
            "value": "example"
        },
        "foo": "bar"
    }
}
```

In this example you would have access to two translations by the following paths: `swag-bundle.nested.value` to get the value 'example' and `swag-bundle.foo` to get the
value 'bar'. You can nest those objects as much as you want.

Since those translation objects become rather huge, you want to outsource them into separate files. For this purpose, create a new directory `snippet` in your module's directory
and in there two new files: `de-DE.json` and `en-GB.json`

Let's also create the first translation, which is for your menu's label.
It's key should be something like this: `swag-bundle.general.myCustomText`

Thus open the `snippet/en-GB.json` file and create the new object in there:
```json
{
    "swag-bundle": {
        "general": {
            "myCustomText": "My custom text snippet"
        }
    }
}
```

Now use this path in your plugin like this:
```js
Component.register('my-custom-page', {
    ...

    methods: {
        createdComponent() {
            const myCustomText = this.$tc('swag-bundle.general.myCustomText');

            console.log(myCustomText);
        }
    }
    ...
});
```

Or use it in you template like in this example:

```twig
{% block my_custom_block %}
    <p>
       {{ $tc('swag-bundle.general.myCustomText') }}
    </p>
{% endblock %}
```
