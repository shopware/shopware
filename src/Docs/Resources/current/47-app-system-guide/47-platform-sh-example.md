[titleEn]: <>(Hosting on Platform.sh)
[metaDescriptionEn]: <>(This is all about the platformm.sh example app)
[hash]: <>(article:app_platform_sh_example_app)

##Caution

This is a pre configured app.  
You should **not** use this in production.
  
In order to use this in production, you need to change the `APP_NAME` and the `APP_SECRET`.
Head over to the [documentation](./45-platform-sh-delelopment-template.md) of the app system development template to configure this.

##Getting started

This app allows you to generate and print order lists for each order.
  
To achieve this, we added the `src/Controller/OrderController.php` and the `src/Services/OrderListService.php` which will do all this stuff.
  
The `OrderController` gets the webhook from the shop whenever an order is placed.  
With this order the order list is generated and added to the original order as a custom field.  
A deep link will also be generated and added to the order to print the order list.  

The `OrderListService` authenticate the request for the order list outside the admin.  
It also fetches all necessary data to build the order list and the deep link to it.  
Last but not least it can update an existing order.  
This can't be done using the normal `src/SwagAppsystem/Client.php`'s `update` function  
because each time an order gets changed, a new version of it will be created.  
This is what happens in the `updateOrder` function in the `OrderListService`. 

In order to display the order list, we also added the `templates/Order/order-list.html.twig` template.  
This includes our print button and includes the `templates/Order/order-list-table.html.twig`.  

The `order-list-table` template is needed to render the actual order list.  
The order list itself is rendered in the `order-list-table` template.  
This template is also used to render the order list for the custom fields in the order.   

Only when the order list wants to be displayed with the print button or within the iframe in the admin,  
it will render the `order-list` template and not the `order-list-table` template.  

##The manifest.xml

This manifest.xml is pre configured.  
It is necessary to change the `meta->name` because this name has to be unique.  
It is also important to change the `setup->secret`. With this secret the shop can register itself to your app.  
If you don't change it, everyone could register their shops to your app.
  
In order to work with this example, you need to change the url's to yours.  
For the registration: `setup->registrationUrl`  
For action-buttons: `admin->action-button.url`  
For the iframe: `admin-module.source`  
And for the webhook: `webhooks->webhook.url` 

That's it. Now you can test with this example and start developing your own app. 

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
    </webhooks>
</manifest>
```
