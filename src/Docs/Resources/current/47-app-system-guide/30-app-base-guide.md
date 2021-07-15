[titleEn]: <>(App base)
[metaDescriptionEn]: <>(This is a guide about the basic information about apps - the base you need to know when developing them.)
[hash]: <>(article:app_base)

# Interaction

Your app can define a range of ways to be notified of events in Shopware and to extend the administration ui. In general, all of them can be achieved by adding some
nodes to the manifest files. The following paragraphs will show you all possibilities we can provide. 

### Webhooks

With webhooks you are able to subscribe to events occurring in Shopware. Whenever such an event occurs 
- a POST request will be send to your stored URL. This URL points to a web application running on your own 
infrastructure, which for example sends an e-mail with a voucher to the customer who has just placed an order.
It's important to note that you have to operate the endpoints for your apps by yourself, i.e. the logic you want 
to execute when an event occurs.

To use webhooks in your app, please implement a `<webhooks>` element in your manifest file, e.g. like this: 

```xml
    <webhooks>
        <webhook name="product-changed" url="product-changed" event="product.written"/>
    </webhooks>
```

This example illustrates you how to define a webhook with the name `product-changed` and the 
url `https://example.com/event/example-with-paid-order` which will be triggered if the event `product.writte` 
is fired. So every time a product is changed, your custom logic will get executed.

An event contains as much data as is needed to react to that event. The data is json contained in the request body. 
For example:

```json
{
  "data":{
    "payload":[
      {
        "entity":"product",
        "operation":"delete",
        "primaryKey":"7b04ebe416db4ebc93de4d791325e1d9",
        "updatedFields":[
        ]
      }
    ],
    "event":"product.written"
  },
  "source":{
    "url":"http:\/\/localhost:8000",
    "appVersion":"0.0.1",
    "shopId":"dgrH7nLU6tlE"
  },
  "timestamp": 123123123
}

```

Where the `source` property contains all necessary information about the Shopware instance that send the request:
* `url` is the url under which your app can reach the Shopware instance and its api
* `appVersion` is the version of the app that is installed
* `shopId` is the id by which you can identify the Shopware instance

The next property `data` contains the name of the event so that a single endpoint can handle several different events, should you desire.
`data` also contains the event data in the `payload` property, due to the asynchronous nature of theses webhooks the `payload` for `$entity.written` events does not contain
complete entities as these might become outdated. Instead the entity in the payload is characterized by its id, stored under `primaryKey`, so that the 
app can fetch additional data through the shops API. This also has the advantage of giving the app explicit control over the associations that
get fetched instead of relying on the associations determined by the event. Other events in contrast contain the entity data that defines the event,
but keep in mind that event might not contain all associations.

The next property `timestamp` is the time which the webhook was handled. The attacker cannot change the timestamp without validating the signature. If the timestamp is too old, the user's application can decide to reject the request.

The current shopware version will be sent as a `sw-version` header.

You can verify the authenticity of the incoming request by checking the `shopware-shop-signature` every request should have a sha256 hmac of the 
request body, that is signed with the secret your app assigned the shop during the registration.

You can use a variety of events to react to changes in Shopware that way. See the table below for an overview of most
important ones.

| Event        | Description           | 
| -------------- |-------------------- |
| `contact_form.send` | Triggers if a contact form is send | 
| `mail.sent` | Triggers if a mail is send from Shopware | 
| `mail.after.create.message` | Triggers if a mail after creating a message is send | 
| `mail.before.send` | Triggers before a mail is send | 
| `checkout.order.placed` | Triggers if an order is placed checkout-wise | 
| `checkout.customer.register` | Triggers if a new customer was registered yo| 
| `checkout.customer.login` | Triggers as soon as a customer logs in | 
| `checkout.customer.double_opt_in_guest_order` | Triggers as soon as double opt-in is accepted in a guest order | 
| `checkout.customer.before.login` |  Triggers as soon as a customer logs in within the checkout process |
| `checkout.customer.changed-payment-method` |  Triggers if a customer changes his payment method in checkout process |
| `checkout.customer.logout` | Triggers if a customer logs out |
| `checkout.customer.double_opt_in_registration` | Triggers if a customer commits to his registration via double opt in |
| `customer.recovery.request` | Triggers if a customer recovers his password |
| `user.recovery.request` | Triggers if a user recovers his password |
| `product.written` | Triggers if a product is written |
| `product_price.written` | Triggers if product price is written |
| `category.written` | Triggers if a category is written |

#### App lifecycle events

Apps can also register to lifecycle events of its own lifecycle, namely its installation, updates and deletion.
For example they maybe used to delete user relevant data from your data stores once somebody removes your app from
their shop. 

| Event        | Description           | 
| -------------- |-------------------- |
| `app.installed` | Triggers once the app is installed | 
| `app.updated` | Triggers if the app is updated | 
| `app.deleted` | Triggers once the app is removed |
| `app.activated` | Triggers if an inactive app is activated |
| `app.deactivated` | Triggers if an active app is deactivated |

Example request body:
```json
{
  "data":{
    "payload":[

    ],
    "event":"app_deleted"
  },
  "source":{
    "url":"http:\/\/localhost:8000",
    "appVersion":"0.0.1",
    "shopId":"wPNrYZgArBTL"
  }
}
``` 

### Buttons

Another extension possibility in the administration is the ability to add own buttons to the smartbar. For now, you can add
them in the smartbar of detail and index views:

![custom-buttons](./img/custom-buttons.png)

To get those buttons, you start in the `admin` section of your manifest file. There you can define `<action-button>` 
elements in order to add your button, as seen as below:

```xml
<admin>
    <action-button action="setPromotion" entity="promotion" view="detail" url="https://example.com/promotion/set-promotion">
        <label>set Promotion</label>
    </action-button>
    <action-button action="deletePromotion" entity="promotion" view="detail" url="https://example.com/promotion/delete-promotion">
        <label>delete Promotion</label>
    </action-button>
    <action-button action="restockProduct" entity="product" view="detail" url="https://example.com/restock">
        <label>restock</label>
    </action-button>
</admin>
```

An action button can have the following attributes:
* action: Unique identifier for the action, can be set freely.
* entity: Here you define which entity you're working on.
* view: To set the view the button should be added to. Currently, you can choose between index and listing view.

When the user then clicks on the Button your app receives a request similar to the one generated by a webhook above.
The main difference is that it contains the name of the entity and an array of ids that the user selected 
(or an array containing just a single id in case the detail page).

```json
{
  "source":{
    "url":"http:\/\/localhost:8000",
    "appVersion":"1.0.0",
    "shopId":"F0nWInXj5Xyr"
  },
  "data":{
    "ids":[
      "2132f284f71f437c9da71863d408882f"
    ],
    "entity":"product",
    "action":"restockProduct"
  },
  "meta":{
    "timestamp":1592403610,
    "reference":"9e968471797b4f29be3e3cf09f52d8da",
    "language":"2fbb5fe2e29a4d70aa5854ce7ce3e20b"
  }
}
```

The current shopware version will be sent as a `sw-version` header.

Again you can verify the authenticity of the incoming request, like with webhooks, by checking the `shopware-shop-signature` it too should contain a sha256 hmac of the
request body, that is signed with the secret your app assigned the shop during the registration.

If you want to trigger an action inside the administration upon completing the action, the app should return a response with a valid body and the header `shopware-app-signature` contains the sha256 hmac of the whole response body signed with and the app secret.
If you do not need to trigger any actions, a response with an empty body is also always valid.

Examples response body:
To open a new tab in the user browser you can use the `openNewTab` action type. You need to pass the url that should be opened as the `redirectUrl` property inside the payload.
```json
{
  "actionType": "openNewTab",
  "payload": {
    "redirectUrl": "http://google.com"
  }
}

```

To send a notification, you can use the `notification` action type. You need to pass the `status` property and the content of the notification as `message` property inside the payload.
```json
{
  "actionType": "notification",
  "payload": {
    "status": "success",
    "message": "This is the successful message"
  }
}

```

To reload the data in the user's current page you can use the `reload` action type with an empty payload.
```json
{
  "actionType": "reload",
  "payload": {}
}

```

To open a modal with the embedded link in the iframe, you can use the `openModal` action type. You need to pass the url that should be opened as the `iframeUrl` property and the `size` property inside the payload.
```json
{
  "actionType": "openModal",
  "payload": {
    "iframeUrl": "http://google.com",
    "size": "medium",
    "expand": true
  }
}

```
* `actionType`: The type of action the app want to be triggered, including `notification`, `reload`, `openNewTab`, `openModal`
* `payload`: The needed data to perform the action
* `redirectUrl`: The url to open new tab
* `iframeUrl`: The embedded link in modal iframe
* `status`: Notification status, including `success`, `error`, `info`, `warning`
* `message`: The content of the notification
* `size`: The size of the modal in `openModal` type, including `small`, `medium`, `large`, `fullscreen`, default `medium`
* `expand`: The expansion of the modal in `openModal` type, including `true`, `false`, default `false`

### Create own module

In your app, you are able to add your own module to the administration. In this case, your app will add an own module
to the administration, including an own menu item. 

![custom-buttons](./img/app-menu.png)

When clicking on this new menu item, an iframe will be displayed. Within this iframe, your website will be loaded and 
shown. In the iframe, your app can do  all the things an external app can do - outside the administration via API. 
In such a module, the search bar stays accessible. However, the search won't be applied on the iframe. 

![custom-buttons](./img/app-frame.png)

In order to create a custom module, you need to define an admin element to define `<admin>` extensions. In there, please
add your module by defining a `<module>` element. 
* Here you're able to define the technical name of your module and the source: Please insert the link to your website 
there.
* To define the module's title and its translation, you can add a label as child element.

```xml
<admin>
    <module name="exampleConfig" source="https://example.com//promotion/view/promotion-config">
        <label>Example config</label>
        <label lang="de-DE">Beispiel-Einstellungen</label>
    </module>
</admin>
```

When the user opens the module in the administration your app will receive a request to the given source url.
Your app can determine the shop that has opened the module through query parameters added to the url:
`https://example.com//promotion/view/promotion-config?shop-id=HKTOOpH9nUQ2&shop-url=http%3A%2F%2Fmy.shop.com&timestamp=1592406102&sw-version=6.4.9999999.9999999-dev&shopware-shop-signature=3621fffa80187f6d43ce6cb25760340ab9ba2ea2f601e6a78a002e601579f415`

In this case the `shopware-shop-signature` parameter contains an sha256 hmac of the rest of the query string, signed again with the secret your app assigned the shop during the registration.
The `sw-version` is the current version of the shopware that the app installed on.


### Custom fields

![custom-fields](./img/custom-fields.png)

By using `<custom-fields>` element, you can add custom fields to Shopware.
And offer you the possibility to add your own fields extending data records. 

```xml
<custom-fields>
    <custom-field-set>
        <name>example_set</name>
        <label>Example Set</label>
        <label lang="de-DE">Beispiel-Set</label>
        <related-entities>
            <order/>
        </related-entities>
        <fields>
        </fields>
    </custom-field-set>
</custom-fields>
```

For the data needed, please refer to the custom fields in general: At first,you need a custom field set, 
as custom fields in Shopware are organised in sets. Here you need to consider some important fields:
* `name`: A technical name for your set
* `label`: This element provides the label of the text and can be used for defining translations of the label as well. 
* `related-entities`: With this element set the entities the custom field set is used in
* `fields`: Finally, the fields are configured in this section.

```xml
<fields>
    <text name="code">
        <position>1</position>
        <label>Example field</label>
    </text>
</fields>
```

When defining custom fields in the `<fields>` element, you can configure additional properties of the fields.
For example a `placeholder`, `min`, `max` and `step` size of a float field:

```xml
<float name="test_float_field">
    <label>Test float field</label>
    <label lang="de-DE">Test-Kommazahlenfeld</label>
    <help-text>This is an float field.</help-text>
    <position>2</position>
    <placeholder>Enter an float...</placeholder>
    <min>0.5</min>
    <max>1.6</max>
    <steps>0.2</steps>
</float>
```

Please refer to 
[Custom field documentation](https://docs.shopware.com/en/shopware-6-en/settings/custom-fields#create-custom-field)
for further details.

Watch out! The names of the custom fields are global and therefore should always 
contain a vendor prefix, like "swag" for "shopware ag", to keep them unique.

### Cookies

If your app wants to set cookies, you need to define those cookies in the manifest file, so they are included in the cookie consent manager.
You can either add single cookies or group them together. If you use the `snippet_name` from a core cookie group as the name of your group, your cookies will be added to the core group.
To add your cookie to the group of technical required cookies the entry in the manifest.xml would look like this: 
```xml
<cookies>
    <group>
        <snippet-name>cookie.groupRequired</snippet-name>
        <entries>
            <cookie>
                <snippet-name>myApp.cookies.someCookie</snippet-name>
                <cookie>swag.app.something</cookie>
           </cookie>
        </entries>
    </group>
</cookies>
```
For a detailed explanation refer to this [HowTo](./../50-how-to/730-add-plugin-cookies.md). Keep in mind that you won't need to provide a custom CookieProvider, but can add your cookies to the cookie consent manager via entries in your manifest file. 
## Examples

### One full example of a manifest file

Below you can take a look on an extended example on how a full manifest file can look like.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/platform/trunk/src/Core/Framework/App/Manifest/Schema/manifest-1.0.xsd">
    <meta>
        <name>ExampleApp</name>
        <label>Swag Example App</label>
        <label lang="de-DE">Swag Example App</label>
        <description>Example App</description>
        <description lang="de-DE">Beispiel App</description>
        <author>shopware AG</author>
        <copyright>(c) by shopware AG</copyright>
        <version>1.0.1</version>
        <icon>icon.png</icon>
        <license>MIT</license>
        <privacy>https://test.com/privacy</privacy>
        <privacyPolicyExtensions>
Following personal information will be processed on shopware AG's servers:

- Name
- Billing address
- Order value
        </privacyPolicyExtensions>
        <privacyPolicyExtensions lang="de-DE">
Folgende Nutzerdaten werden auf Servern der shopware AG verarbeitet:

- Name
- Rechnungsadresse
- Bestellwert
        </privacyPolicyExtensions>
    </meta>
    <setup>
        <registrationUrl>https://example/</registrationUrl>
    </setup>
    <admin>
        <action-button action="setPromotion" entity="promotion" view="detail" url="https://example.com/promotion/set-promotion">
            <label>set Promotion</label>
        </action-button>
        <action-button action="deletePromotion" entity="promotion" view="detail" url="https://example.com/promotion/delete-promotion">
            <label>delete Promotion</label>
        </action-button>

        <module name="promotionConfig" source="https://example.com//promotion/view/promotion-config">
            <label>Promotion config</label>
            <label lang="de-DE">Gutscheincode-Einstellungen</label>
        </module>
    </admin>

    <permissions>
        <create>product</create>
        <create>product_visibility</create>
        <create>promotion</create>
        <create>promotion_individual_code</create>
        <create>customer</create>
        <create>customer_address</create>
        <create>state_machine_history</create>

        <read>tax</read>
        <read>currency</read>
        <read>promotion_individual_code</read>
        <read>salutation</read>
        <read>country</read>
        <read>customer_group</read>
        <read>payment_method</read>
        <read>order</read>

        <update>product</update>
        <update>order</update>
    </permissions>

    <custom-fields>
        <custom-field-set>
            <name>promotion_code</name>
            <label>Promotioncode</label>
            <label lang="de-DE">Gutscheincodes</label>
            <related-entities>
                <order/>
            </related-entities>
            <fields>
                <text name="code">
                    <position>1</position>
                    <label>Gutscheincode</label>
                </text>
            </fields>
        </custom-field-set>
    </custom-fields>

    <webhooks>
        <webhook name="orderPromotion" url="https://example.com//promotion/event/state-enter-order-transaction-state-paid" event="state_enter.order_transaction.state.paid"/>
    </webhooks>
    <cookies>
	    <cookie>
            <snippet-name>myApp.cookies.analyticsCookie</snippet-name>
            <cookie>swag.app.analytics</cookie>
        </cookie>
        <group>
            <snippet-name>cookie.groupRequired</snippet-name>
            <entries>
                <cookie>
                    <snippet-name>myApp.cookies.someCookie</snippet-name>
                    <cookie>swag.app.something</cookie>
               </cookie>
            </entries>
        </group>
    </cookies>
</manifest>
```
