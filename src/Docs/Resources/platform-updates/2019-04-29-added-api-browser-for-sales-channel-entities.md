[titleEn]: <>(Added Api-Browser for SalesChannelEntities)

We have added the ApiBrowser functionality of the EntityDefinitions for the SalesChannelApi-Entities as well.
You can find the SwaggerUI under `/sales-channel-api/v1/_info/swagger.html`.

We also added a configuration, to control whether the ApiBrowser functionality is available or not.
You can control it over the `api.api_browser.public` entry in your shopware.yaml:
```yaml
api:
  api_browser:
    public: true
```