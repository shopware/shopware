[titleEn]: <>(App configuration)
[metaDescriptionEn]: <>(This is a guide about how you can offer configuration possibilities for your app to your users.)
[hash]: <>(article:app_configuration)

# Configuring apps

To offer configuration possibilities to your users you can provide a config.xml file that describes your configuration options.
That file follows the same rules and uses the same schema as the config.xml for the plugin system uses. You can find detailed information about the possibilities and the structure of the config.xml in the [according documentation page](./../60-references-internals/40-plugins/070-plugin-config.md).
To include a config.xml file in your app put it into the `Resources/config` folder:
```
...
└── DemoApp
      └── Resources
            └── config  
                  └── config.xml
      └── manifest.xml
```

The configuration page will be displayed in the administration under the `Extension store`.
This means that the UI for configuring apps is currently only available in the Cloud environment, but in the future the extension store will also be available for on-premise.
For development purposes you can use the administration component to configure plugins to provide configuration for your app, therefore use the url `{appUrl}/admin##/sw/plugin/settings/{appName}`.

## Reading the configuration values

The configuration values are saved as part of the `SystemConfig` and you can use the key `{appName}.config.{fieldName}` to identify the values.
There are two possibilities to access the configuration values from your app.
If you need those values on your app-backend server, you can read them over the API.
If you need the configuration values in your storefront twig templates you can use the `systemConfig()` twig function.

### Reading the config over the api

To access your apps configuration over the api make a GET request against the `/api/v{version}/_action/system-config` route.
You have to add the prefix for your configuration as the `domain` query parameter. Optionally you can provide a SalesChannelId, if you want to read the values for a specific SalesChannel, as the `salesChannelId` query param.
The api call will return a JSON-Object containing all of your configuration values.
A sample Request and Response may look like this.
```
GET /api/v{version}/_action/system-config?domain=DemoApp.config&salesChannelId=98432def39fc4624b33213a56b8c944d
{
    "DemoApp.config.field1": true,
    "DemoApp.config.field2": "sucessfully configured"
}
```
Keep in mind that your app needs the `system_config:read` permission to access this api.

### Reading the config in templates

Inside twig templates you can use the `config` property of the global `shopware` object to read your configuration.
An example twig template could look like this:
```twig
{{ shopware.config.DemoApp.config.field1 }}
```
