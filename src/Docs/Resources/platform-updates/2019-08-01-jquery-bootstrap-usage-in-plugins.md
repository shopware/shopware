[titleEn]: <>(Best practice - Storefront jQuery & Bootstrap usage)

We optimized the usage of jQuery & Bootstrap in Storefront plugins as well as themes. Libraries, helpers and the plugin
system are now using the same instance across multiple entry points.

Technically speaking we added chunk splitting, moved all of our third party libraries to a separate chunk which will be
shared over the multiple entry points (Storefront default entry point + plugins + themes). To share this chunk we had
to generate a "runtime" chunk which contains the glue code for the sharing of the instance.

### Do you need to explicitly import Bootstap & jQuery?
No, you don't have to but it's strongly suggested for enhanced auto completion support in IDEs, easier refactoring
and cleaner code style.

### How can I work with Bootstrap SCSS system?
The Bootstrap SCSS components / mixins / variables don't have to be imported explicitly. It's strongly suggested for
the same reasons: Enhanced auto completion support in IDEs, easier refactoring and cleaner code style.

### Can I access jQuery without using Webpack?
Yes, you can. We exposing the `jQuery` object to the global `window` object to enable legacy jQuery plugins as well
as using it without working with Webpack.