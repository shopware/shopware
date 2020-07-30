[titleEn]: <>(Hosting on Platform.sh)
[metaDescriptionEn]: <>(This is all about the platformm.sh example app)
[hash]: <>(article:app_platform_sh_example_app)

## A small example app

This app allows you to generate and print order lists for each order.
These lists contain all products of that order in a print friendly way.
So that they can be used as a checklist during packing.

It demonstrates three ways to interact with an app.
1. Webhooks to be notified of business events
2. An extra admin module to display the the order lists
3. An action button to extend the order detail page

The source code can be found on [GitHub](https://github.com/shopwareLabs/AppExample).

## Getting started

This app allows you to generate and print order lists for each order.
  
To achieve this, we added the `src/Controller/OrderController.php` and the `src/Services/OrderListService.php` which will 
encapsulate our interaction with the shop.
  
The `OrderController` gets the webhook from the shop whenever an order is placed.  
With this order the order list is generated and added to the original order as a custom field.  
A deep link will also be generated and added to the order to print the order list.  

The `OrderListService` authenticates the request for the order list outside the admin.  
It also fetches all necessary data to build the order list and the deep link to it.  
Last but not least it can update an existing order and handle its versions:

```php
// OrderListService.php
public function updateOrder(Client $client, string $orderId, array $data): void
{
    $httpClient = $client->getHttpClient();

    //Creates a new version of the order.
    $versionResponse = $httpClient->post('/api/v2/_action/version/order/' . $orderId);
    $versionId = json_decode($versionResponse->getBody()->getContents(), true)['versionId'];

    //Updates the order.
    $client->updateEntity('order', $orderId, $data);

    //Merges the changes into the new version of the order.
    $httpClient->post('/api/v2/_action/version/merge/order/' . $versionId, ['headers' => ['sw-version-id' => $versionId]]);
}
```


In order to display the order list, we also added the `templates/Order/order-list.html.twig` template.  
This includes our print button and includes the `templates/Order/order-list-table.html.twig`.  

The `order-list-table` template is needed to render the actual order list.  
The order list itself is rendered in the `order-list-table` template.  
This template is also used to render the order list for the custom fields in the order.   

## The manifest.xml

This manifest.xml is pre configured.  
It is necessary to change the `meta->name` because this name has to be unique.  
It is also important to change the `setup->secret`. With this secret the shop can register itself to your app.  
If you don't change it, everyone could register their shops to your app.
  
In order to work with this example, you need to change the url's to yours.  
For the registration: `setup->registrationUrl`  
For action-buttons: `admin->action-button.url`  
For the iframe: `admin-module.source`  
And for the webhook: `webhooks->webhook.url` 

Now you can install it with the command `./bin/console app:install SwagExampleApp` and start exploring the app.  

```xml
<?xml version="1.0" encoding="UTF-8"?>
<manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/app-system/0.1.0/src/Core/Content/App/Manifest/Schema/manifest-1.0.xsd">
    <meta>
        <name>SwagExampleApp</name>
        <label>Swag Example App</label>
        <description>Example App</description>
        <description lang="de-DE">Beispiel App</description>
        <author>shopware AG</author>
        <copyright>(c) by shopware AG</copyright>
        <version>1.0.0</version>
    </meta>
    <setup>
        <registrationUrl>https://your-app.url.com/registration</registrationUrl>
        <secret>143af21f36dda6b4bc40df8cb045616d</secret>
    </setup>

    <admin>
        <action-button action="addOrderList" entity="order" view="detail" url="https://your-app-url.com/actionbutton/add/orderlist">
            <label>Add order list</label>
            <label lang="de-DE">Bestellliste hinzufügen</label>
        </action-button>

        <module name="orderList" source="https://your-app.url.com/iframe/orderlist">
            <label>Order list</label>
            <label lang="de-DE">Bestellliste</label>
        </module>
    </admin>

    <permissions>
        <create>state_machine_history</create>
        <read>order</read>
        <update>order</update>
    </permissions>

    <custom-fields>
        <custom-field-set>
            <name>swag_orderlist</name>
            <label>Order list</label>
            <related-entities>
                <order/>
            </related-entities>
            <fields>
                <text name="order-list-link">
                    <position>1</position>
                    <label>Order list link</label>
                    <label lang="de-DE">Bestellliste Link</label>
                </text>
                <text-area name="order-list">
                    <position>2</position>
                    <label>Order list</label>
                    <label lang="de-DE">Bestellliste</label>
                </text-area>
            </fields>
        </custom-field-set>
    </custom-fields>

    <webhooks>
        <webhook name="checkoutOrderPlaced" url="https://your-app.url.com/hooks/order/placed" event="checkout.order.placed"/>
        <webhook name="appLifecycleDeleted" url="https://your-app.url.com/applifecycle/deleted" event="app_deleted"/>
    </webhooks>
</manifest>
```

## Deployment on platform.sh

To deploy your app on [platform.sh](platform.sh) just follow the instructions:
* [Public GitHub repository](https://docs.platform.sh/integrations/source/github.html)
* [Private GitHub repository](https://docs.platform.sh/development/private-repository.html)
* [Using the Platform.sh CLI](https://github.com/platformsh/platformsh-cli)

After the deployment you can use the [Plaform.sh CLI](https://github.com/platformsh/platformsh-cli) to set up the database.
First ssh to your server: `platform ssh`  
Then run the migrations: `vendor/bin/doctrine-migrations migrations:migrate`  
That's is. Your server is running and you can start developing your own app. 
