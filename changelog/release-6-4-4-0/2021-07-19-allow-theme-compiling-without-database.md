---
title: Allow theme compiling without database
issue: NEXT-15802
---
# Storefront
* Added new storefront configuration to save the theme configuration as static files, to allow compiling the theme without a database
___
# Upgrade Information
## Compiling the Storefront theme without database

We have added a new configuration to load the theme configuration from static files instead of the database. This allows building in the CI process the entire storefront assets before deploying the application.
To enable this, create a new file `config/packages/storefront.yml` with the following content:

```yaml
storefront:
    theme:
        config_loader_id: Shopware\Storefront\Theme\ConfigLoader\StaticFileConfigLoader
        available_theme_provider: Shopware\Storefront\Theme\ConfigLoader\StaticFileAvailableThemeProvider
```

With this configuration `theme:compile` will force that the configuration will be loaded from the private filesystem. Per default the private file system writes into the `files` folder. It is highly recommended saving into an external storage like s3, to have it accessible also from the CI.
The static files can be generated using `theme:dump` (requires database access) or by changing a theme configuration option in the administration.
