[titleEn]: <>(The app system development template)
[metaDescriptionEn]: <>(Here you can find all informations about the app system development template)
[hash]: <>(article:app_development_template)

## Platform-sh

The development template is optimized for the use with [platform.sh](https://platform.sh/).  

With this development template in addition with platform.sh you can easily develop your own app.  
You don't need to think about the hosting and communication with the shop.  
This will all be done by platform.sh and our controller and services. 

## Getting started

The app template can be found on [GitHub](https://github.com/shopwareLabs/AppTemplate). Fork the repository and use it to develop your apps.
For an example take a look at this [example app](./50-platform-sh-example.md).

In order to use this template for development or for production you need to configure two things.  

* The `APP_NAME` (the unique name of your app)
* The `APP_SECRET` (a secret which is needed for the registration process)

You need to set both of them in your `manifest.xml` but also in the `.platform.app.yaml`.

An example for the `manifest.xml` would be:

```xml
<manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/app-system/0.1.0/src/Core/Content/App/Manifest/Schema/manifest-1.0.xsd">
    <meta>
        <name>myExampleApp</name>
    </meta>
    <setup>
        <secret>myAppSecret</secret>
    </setup>
</manifest>
```

An example for the `.platform.app.yaml` would be:
```yaml
variables:
    env:
        APP_NAME: myExampleApp
        APP_SECRET: myAppSecret
```

### Deployment on platform.sh

To deploy your app on [platform.sh](platform.sh) just follow the instructions:
* [Public GitHub repository](https://docs.platform.sh/integrations/source/github.html)
* [Private GitHub repository](https://docs.platform.sh/development/private-repository.html)
* [Using the Platform.sh CLI](https://github.com/platformsh/platformsh-cli)

After the deployment you can use the [Plaform.sh CLI](https://github.com/platformsh/platformsh-cli) to set up the database.
First ssh to your server: `platform ssh`  
Then run the migrations: `vendor/bin/doctrine-migrations migrations:migrate`  
That's it. Your server is running and you can start developing your own app. 

## The registration process 

The registration is the most important thing in your app.  
To handle this we have the `src\SwagAppsystem\Controller\Registration.php` controller.  
This controller will handle the whole registration.  

The registration will go through several steps.

* authenticate the registration request
* generate an unique secret for the shop
* save the secret with the id and the url of the shop
* send the secret to the shop with a confirmation url
* authenticate the confirmation request
* save the access keys for the shop

Now the shop is registered to the app and you can start communicating with it. 

## Communicating with the shop

To communicate with the shop you can use the `src/SwagAppsystem/Client.php`.  
The client includes all necessary functionality for communication purposes.  

It will authenticate itself to the shop whenever needed.  
For example if you want to fetch a specific product like this: 
```php
$productData = $client->fetchDetail('product', $id);
```
it will first authenticate itself to the shop, then perform the api action:
```php
// Client.php
public function getHttpClient(): HttpClient
    {
        if ($this->httpClient !== null) {
            return $this->httpClient;
        }

        if ($this->credentials->getToken() !== null) {
            $this->httpClient = $this->buildClient($this->credentials->getToken());

            return $this->httpClient;
        }

        $this->credentials = Authenticator::authenticate($this->credentials, $this->authenticationHandlerStack);
        $this->httpClient = $this->buildClient($this->credentials->getToken());

        return $this->httpClient;
    }
```

You can also call `$client->getHttpClient` directly if you want to call a specific api route.
```php
$client->getHttpClient()->post(
    "/api/v2/_action/media/${mediaId}/upload",
    //...
```  
Now you can perform your own requests.  

## Handling events

In your manifest you can define your own webhooks.  
To handle these in your app we included the `src/SwagAppsystem/Event.php`.  
You can use it whenever an event gets triggered.  

The event itself has all the necessary information you might need.  
It includes the `shopUrl`, `shopId`, `appVersion` and the `eventData`.  

## App lifecycle events

There are five app lifecycle events which can be triggered during the lifecycle of an app.  
The events are `app_installed`, `app_updated`, `app_deleted`, `app_activated` and `app_deactivated`.
To use this events you have to create the webhooks in your manifest.  
If you want to implement your own code you only need to implement the `src/SwagAppsystem/AppLifecycleHandler.php` interface and write your own code.  

```php
class LifecycleListener implements App\SwagAppsystem\AppLifecycleHandler
{
    public function appInstalled(Event $event): void
    {
        // ...
    }

    public function appUpdated(Event $event): void
    {
        // ...
    }

    public function appActivated(Event $event): void
    {
        // ...
    }

    public function appDeactivated(Event $event): void
    {
        // ...
    }   

    public function appDeleted(Event $event): void
    {
        // ...
    }
}
```

Then tag the service as `swag.app_lifecycle_handler` and your code will be executed in addition to the default behavior of the
app template.

```yaml
App\LifecycleListener:
        tags: ['swag.app_lifecycle_handler']
```

The `app_installed` event gets triggered each time the app gets installed.  
This will also trigger the `app_activated` event.  
At each of this both events the shop is already installed and registered at your app.  
The webhook could look like this:

```xml
<webhook name="appLifecycleInstalled" url="https://your-shop-url/applifecycle/installed" event="app_installed"/>
```

The `app_updated` event gets triggered each time a shop updated your app.  
The webhook could look like this:

```xml
<webhook name="appLifecycleUpdated" url="https://your-shop-url/applifecycle/updated" event="app_updated"/>
```

The `app_deleted` event gets triggered each time a shop deletes your app.  
At this point the shop is deleted using the `src/Repository/ShopRepository.php`.  
You should delete all shop data you have saved and stop the communication with the shop.  
The webhook could look like this:

```xml
<webhook name="appLifecycleDeleted" url="https://your-shop-url/applifecycle/deleted" event="app_deleted"/>
```

The `app_activated` event gets triggered each time your app gets installed or activated.  
At this point you can start the communication with the shop.  
The webhook could look like this:
```xml
<webhook name="appLifecycleActivated" url="https://your-shop-url/applifecycle/activated" event="app_activated"/>
```

The `app_deactivated` event gets triggered each time your app gets deactivated.  
At this point you should stop the communication with the shop.  
The webhook could look like this:

```xml
<webhook name="appLifecycleDeactivated" url="https://your-shop-url/applifecycle/deactivated" event="app_deactivated"/>
```

## The argument resolver

There are two argument resolver. One for the `Client` and one for the `Event`.  
The purpose of those is to inject the `Client` and the `Event` whenever you need them.  

For example you define a route for incoming webhooks and want to fetch some extra data.  
Then you can use them as a parameter of the method which will be called when a request is send to the route.  

But how do you know that the request is from the shop and not from someone who is sending post requests to your app?
The argument resolver take care of it. Whenever you use one of them as a parameter the request will be authenticated.  
If the request isn't authenticated the `Client` or the `Event` will be null. 

## The shop repository

The `src/Repository/ShopRepository.php` can be used to get the secret of the shop and the `src/SwagAppsystem/Credentials`.  

For example if you want to build your own authentication you can use the `ShopRepository` to get the secret to the corresponding shop.  
But if you want to build your own `Client` you can simply get the `Credentials` for a specific `shopId`.  

## Code quality

To improve your code style we added [EasyCodingStandard](https://github.com/symplify/easy-coding-standard) and for testing purposes [PHPUnit](https://phpunit.de/index.html).

To check your code style just execute `vendor/bin/ecs check` or add `--fix` to also fix your code.

To make sure that your code is working correctly you can write your own tests in `tests`.  
In order to execute those just execute `vendor/bin/phpunit`.  
