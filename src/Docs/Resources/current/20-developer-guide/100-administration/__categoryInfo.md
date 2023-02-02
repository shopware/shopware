[titleEn]: <>(Administration)
[hash]: <>(category:developer_administration)

The administration of Shopware 6 mainly uses [VueJS](https://vuejs.org/) as a framework. How to develop with VueJS is **not** explained here, head over to the [official documentation](https://vuejs.org/v2/guide/)
to learn more about the framework itself.

Of course any Shopware 6 specific code will be explained, don't worry about that.

## Setting up the administration

Each plugin has a main entry point to add custom javascript code to the administration. By default, Shopware 6 is looking for a 
`main.js` file inside a `src/Resources/app/administration/src` directory in your plugin.
Thus, create a new file `main.js` in the directory `<plugin root>/src/Resources/app/administration/src`. That's it, this file will now be considered when building
the administration.
