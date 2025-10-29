# Shopware Storefront

The Shopware Storefront uses Vite for building JavaScript files and offers a dev-server for instant feedback during development. There are composer commands available which can be run from the root of your Shopware project, which make the usage easier.

## Building for production

This will build the main Storefront files, and also files from extensions for production use.

```bash
composer storefront:build
```

## Building for development

This is also a normal build, but with source maps enabled for development purposes.

```bash
composer storefront:development
```

## Development Workflow

For active development you can use the Vite dev-server which offers hot module replacement and also compiling SCSS files besides the JavaScript files. Other than the previous Webpack solution, there is no proxy created. You can just open the normal URL of your Storefront in the Browser. The Storefront will automatically detect if you are in the dev-server mode and will serve JS and CSS files from the Vite dev-server. The Storefront will also automatically reload if new changes are detected. Just refresh the page once you have started the dev-server.

**Vite Dev Server (Recommended)**
```bash
composer storefront:dev-server
```

The dev server watches the core Storefront files, but also files from Shopware extensions.

**Configuration options:**
```bash
# Disable SCSS compilation
DISABLE_STOREFRONT_SCSS=true composer storefront:dev-server

# Enable deprecation warnings (default: suppressed)
SILENCE_SCSS_DEPRECATIONS=false composer storefront:dev-server
```

