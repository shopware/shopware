[titleEn]: <>(Writing your own app)
[metaDescriptionEn]: <>(Here you can learn about how to write a first app)
[hash]: <>(article:app_write_own)

As we read everything about the app system in theory, lets look at it in the form of a tutorial. In this article, we
got you covered! 

Let's imagine the following scenario: Our App is supposed to allow the shop owner to play out promotion codes to 
customers once they have made and paid for a purchase above a minimum order value.
                                     
Therefore, a promotion has to be created in the Administration by hand, individual keys have to be generated 
and then activated via the button set Promotion. Afterwards you as a shop owner can view the set promotion 
under My apps and enter the minimum order value.

If an order above this minimum order value is received and the status of this order is set to paid, 
the customer will be given one of the previously generated codes. The customer can then view this code under his order and use it for the next order.

## Own implementation outside of Shopware

As emphasized before, you need run the endpoints for your apps, i.e. the logic you want to execute 
when an event occurs, on an accessible web server yourself. 

In this article, we'll give you a short guidance on how to react to Shopware's webhooks by yourself. For this example 
we use a simple Symfony project and use Routes for our webhooks. Please don't forget to read about 
[creating Symfony projects](https://symfony.com/doc/current/setup.html), in case you didn't know about that before.

## First steps in app

To get started with your app, create an `apps` folder in `custom` of your development template installation. In there, 
create another folder for your application and provide a manifest file in it.
```
...
└── custom
    ├── apps
    │   └── MyExampleApp
    │       └── manifest.xml
    └── plugins
...
```

After creating this file structure and the manifest file, you should be able to install your newly created app via 
`bin/console app:refresh`.

### Set first configurations in Manifest file

Afterwards, let's create the `manifest.xml` (called manifest file) and configure the first things:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/app-system/0.1.0/src/Core/Content/App/Manifest/Schema/manifest-1.0.xsd">
    <meta>
        // Let's configure this .. 
    </meta>
    <setup>
        <registrationUrl/> // ...this...
    </setup>
    <permissions>
        // ... and this
    </permissions>
</manifest>
```

We'll start with the meta data. It's quite self-explanatory, so let's just take a look at the things to define here:
```xml
<meta>
    <name>ExampleApp</name>
    <label>Swag Example App</label>
    <label lang="de-DE">Swag Example App</label>
    <description>Example App</description>
    <description lang="de-DE">Beispiel App</description>
    <author>shopware AG</author>
    <copyright>(c) by shopware AG</copyright>
    <version>1.0.12</version>
    <icon>icon.png</icon>
</meta>
```

As you see, it's the place to go to define all those basic information about your app, e.g. technical name, labels 
and version of your app. 

Next up are the permission we grant to our app. In the manifest file, it looks like this:
```xml
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
```

Ok, we're ready to go now!

## Using webhooks to subscribe to then order event

We want the promotion code to be send to the customer after his or her order is paid. That means we need to react as soon
as the order status "Paid" is set. The webhook `state_enter.order_transaction.state.paid` is the right one 
to subscribe to. We subscribe to this event using the `event` attribute in our webhook element in our manifest file:
```xml
<webhooks>
    <webhook name="orderPromotion" url="http://example/promotion/event/state-enter-order-transaction-state-paid" event="state_enter.order_transaction.state.paid"/>
</webhooks>
```

This is for the internal Shopware part. What about our implementation outside of Shopware?

### Own implementation

We use [Symfony routes](https://symfony.com/doc/current/routing.html) to provide the needed endpoints for our app. 
The `url` attribute is holding the URL of the route we want to use, with is implemented in a controller ofour symfony 
project:

```php
/**
 * @Route("/promotion/event/state-enter-order-transaction-state-paid", methods={"POST"})
 */
public function stateEnterOrderTransactionStatePaidEvent()
{
    // get request information
    $requestContent = Container::get('request.content');
    $entityService = Container::get(EntityService::class);

    // set currency of the order connected to the promotion
    $currency = $entityService->getEntity('currency', $requestContent['data']['payload']['order']['currencyId']);

    // get the promotion code 
    $code = self::getPromotionCode($requestContent['data']['payload']['order']['price']['totalPrice'], $currency);

    // prepare the response to give the data for the order's custom fields
    $response = OrderController::addCustomFields($requestContent['data']['payload']['order']['id'], ['code' => $code], $requestContent['source']['url']);

    return new Response(null, $response->getStatusCode());
}
```

From the request origination from Shopware, we can get all content we would get by using the Shopware API. In this case,
we get all information about the order of the customer. This way, we can be able to work with the data from Shopware.
Afterwards, we are able to return a response to Shopware - in our example, the promotion code the customer got to be 
displayed in the custom field of our app.

## Add an own module

In our example app, you should be able to view the set promotion in an own module. In it, you should be able to edit
the minimum value of an order for a new promotion code to be send. Therefore you add a new module to the Shopware
via configuring it in your manifest file:

```xml
<admin>
    <module name="promotionConfig" source="http://localhost:7777/promotion/view/promotion-config">
        <label>Promotion config</label>
        <label lang="de-DE">Gutscheincode Einstellungen</label>
    </module>
</admin>
```

In order to add an own module, you need to use the `<module>` element. In it, please give your module a name
by defining it in the `name` attribute. The `source` attribute of your `module` element contains the URL which will 
be open in the iframe of that module. Within the `<module>` attribute, you set the label of your module which will
be used in the module and as title of your module. You can even add your translations that way, as you see in the
example above.

### Own implementation

Of course you need to display something in this iframe. In our example app, we again use a route to render our view.
More precisely, we need a method in a controller to do that for us:

```php
/**
     * @Route("promotion/view/promotion-config")
     */
    public function promotionCodeIframeView()
    {
        $url = $_ENV['SERVER_URL'];
        $connection = Container::get('database.connection');

        $query = $connection->prepare("SELECT * FROM instance WHERE instance_url = '" . $url . "' AND demo = '" . false . "'");
        $query->execute();
        $instance = $query->fetch();

        Container::register(
            [
                'source' => [
                    'apiKey' => $instance['api_key'],
                    'secretKey' => $instance['secret_key'],
                    'url' => $url,
                ],
            ],
            'request.content'
        );

        $session = FrontendIframeController::setSession($instance['instance_id']);

        $variables = [
            'AppId' => ['value' => $session['sessionId'], 'name' => 'appId'],
            'InstanceId' => ['value' => $instance['instance_id'], 'name' => 'instanceId'],
            'AdminUrl' => ['value' => $url, 'name' => 'adminUrl'],
            'refreshInterval' => ['value' => $session['refreshInterval'], 'name' => 'refreshInterval'],
        ];

        return $this->render('Promotion/promotion-code.html.twig', $variables, new Response(null, 200));
    }
```

In the return statement, you can see the path to our view to be rendered. It looks like seen below:
```twig
{% extends 'base.html.twig' %}

{% block stylesheets %}
    <link rel="stylesheet" type="text/css" href="/get/css/promotion-style.css">
{% endblock %}

{% block body %}
    <div id="promotions"></div>
    <button onclick="getPromotions()">get Promotions</button>
{% endblock %}

{% block javascripts %}
    <script type="text/javascript" src="/get/js/promotion-index.js"></script>
{% endblock %}
```

One last thing in here - the most important of the javascript part of the view:
At first, your new module will use a loading spinner to signalize your view is loading. So we need to give a 
notification when the loading process is done. 

```javascript
function sendReadyState() {
    window.parent.postMessage('connect-app-loaded', '*');
}
```

This has to be done as soon as everything is loaded so that the loading spinner disappears. If your view is not 
fully loaded after 5 seconds, it will be aborted.

## Add the action buttons to the administration

When you as a shop owner open your promotion to set it as the one to be send to the customer, you need a button or
another possibility to make that happen. 

To add own buttons in promotion detail page, you use extensions in the `admin`  area of your manifest file, like you do
for adding an own module. The element to choose in this case is the `<action-button>` one:

```xml
<admin>
    <action-button action="setPromotion" entity="promotion" view="detail" url="http://example/promotion/set-promotion">
        <label>set Promotion</label>
    </action-button>
    <action-button action="deletePromotion" entity="promotion" view="detail" url="http://example/promotion/delete-promotion">
        <label>delete Promotion</label>
    </action-button>
</admin>
```

In order to accomplish our case, we need to set the following attributes:
* action: `setPromotion` as name
* entity: `promotion`, as we're using this particular entity
* view: `detail`, as we want our action buttons in the detail view
* url: Here we set our route we use to set our external logic in motion

### Own implementation

On our external side, we again use routes to react to actions in Shopware. In this case, its the route 
`promotion/set-promotion` where Shopware sends a request to, if our action button is clicked. Then, the action 
`setPromotion` will be executed:

```php
    /**
     * @Route("promotion/set-promotion", methods={"POST"})
     */
    public function setPromotion()
    {
        // get all data you need 
        $requestContent = Container::get('request.content');
        $connection = Container::get('database.connection');
        $entityService = Container::get(EntityService::class);

        // Collect more promotion-related data
        $promotionId = $requestContent['data']['ids'][0];
        $promotionEntity = $entityService->getEntity('promotion', $promotionId);
        $promotionName = $promotionEntity->getName();

        // Store promotion in database
        $connection->prepare("INSERT INTO promotion(promotion_id, instance_id, name) VALUES ('" . $promotionId . "', '" . hash('sha512', $requestContent['source']['apiKey'] . $requestContent['source']['secretKey']) . "', '" . $promotionName . "')")
            ->execute();

        self::insertPromotionCodes($promotionId, self::getAllPromotionCodes($requestContent['source']['url'], $requestContent['data']['ids'][0]));

        return new Response(null, 200);
    }
```

## Add a custom field for the promotion code

Before, we send a response containing information for a custom field in an order. How do we use it in Shopware? We
accomplish that by adding that custom field by our app. This happens in the `<custom fields>` area of your manifest 
file, mimicking the structure and (required) fields of the custom fields you would create by creating them via API
or in the administration by hand.

```xml
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
                <label>Code</label>
            </text>
        </fields>
    </custom-field-set>
</custom-fields>
```

In our case, we do the following set configuration:
* `name` of the set: Promotioncode
* Our labels would be "Promotioncode" or "Gutscheincode" as german translation
* We need to set `order` as related entity, as we want to have it implemented in orders

Finally, we create our field in the `fields` element:
* `text` for it being a text field, with the `name`
* Setting it on first position and using the label "Code"

## Enable the customer to see the promotion code

We're almost done. However, one thing would still be nice: If you send the promotion code to your customer, of course 
you want him to be able to find it. A possibility is to display the code in his order. 

This can be done in our app itself - as it's a tiny theme adjustment. If you never did an adjustment in a theme, 
we got you covered: Please read our [theme guide](https://docs.shopware.com/en/shopware-platform-dev-en/theme-guide/twig-templates?category=shopware-platform-dev-en/theme-guide)
if you need.

Similar as in our 
[example theme app](./20-create-own-theme.md), we'll create a file `order-detail.html.twig` in folder 
`Example-App/Resources/views/storefront/page/account/order-history`:

```html
{# Example-App/Resources/views/storefront/page/account/order-history/order-detail.html.twig #}

{% extends '@Storefront/storefront/page/account/order-history/order-detail.html.twig' %}

{% block page_account_order_item_detail_tracking_code %}
    {{ parent() }}

    {% if order.customFields.code is defined and order.customFields.code is not empty %}
        <dt class="col-6 col-md-5">Promotion-Code:</dt>
        <dd class="col-6 col-md-7">{{ order.customFields.code }}</dd>
    {% endif %}
{% endblock %}
```

We extend the block `page_account_order_item_detail_tracking_code` and add the promotion code to it using the variable
`order.customFields.code`. `code` is the name of our custom field.

## Full manifest file

Well, there you go! I hope this example helps you creating your first app. Last but not least, here's the full 
manifest file of our example app, for reference purposes:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/app-system/0.1.0/src/Core/Content/App/Manifest/Schema/manifest-1.0.xsd">
    <meta>
        <name>ExampleApp</name>
        <label>Swag Example App</label>
        <label lang="de-DE">Swag Example App</label>
        <description>Example App</description>
        <description lang="de-DE">Beispiel App</description>
        <author>shopware AG</author>
        <copyright>(c) by shopware AG</copyright>
        <version>1.0.12</version>
        <icon>icon.png</icon>
    </meta>
    <setup>
        <registrationUrl/>
    </setup>
    <admin>
        <action-button action="setPromotion" entity="promotion" view="detail" url="http://example/promotion/set-promotion">
            <label>set Promotion</label>
        </action-button>
        <action-button action="deletePromotion" entity="promotion" view="detail" url="http://example/promotion/delete-promotion">
            <label>delete Promotion</label>
        </action-button>

        <module name="promotionConfig" source="http://localhost:7777/promotion/view/promotion-config">
            <label>Promotion config</label>
            <label lang="de-DE">Gutscheincode Einstellungen</label>
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
        <webhook name="orderPromotion" url="http://example/promotion/event/state-enter-order-transaction-state-paid" event="state_enter.order_transaction.state.paid"/>
    </webhooks>
</manifest>
``` 
