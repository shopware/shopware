[titleEn]: <>(Setup of app system)
[metaDescriptionEn]: <>(Here you can find all information you need concerning setup of apps)
[hash]: <>(article:app_setup)

## App system in Shopware platform

The app system itself is integrated into the shopware core since version 6.3.3.0. 
If you want to use the app system with a prior version of shopware you can install the app system as a plugin from its [repository in github](https://github.com/shopware/app-system).

## Setup of your app

It's important to emphasize that you can define the endpoints for your apps - You have to run it yourself. 
So you have to make sure that your program runs somewhere, on a server you take care of. 

In order to write apps by yourself, you don't need lots of things concerning setup:
 
* A publicly accessible web server with a language of your choice (PHP, NodeJS, Golang)
* Optional other services you need for your app, e.g. MySQL, Redis. 
* An app that calls URLs on your web server for certain Shopware events
 
The advantage of this approach is that in case of errors, you no longer have to wait until all 
merchants using your plugin have installed the update. Since you are the one running the program, any 
customizations you make will directly affect all merchants using your app.

This is more work and more responsibility - but we can help you with that as well: 

In cooperation with platform.sh we plan to provide templates that will make it easier for you to get started 
with running apps. With just a few clicks you can run your environment on which you build extensions 
for Shopware in your favorite programming language.

### File structure

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

### Start writing apps

After creating this file structure and the manifest file, you can start filling the manifest.xml with life:

## Manifest file

The manifest file is the central point of your app. It defines the interface between your app and the Shopware instance. 
It provides all the information concerning your app, as seen in the minimal version below:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/platform/trunk/src/Core/Framework/App/Manifest/Schema/manifest-1.0.xsd">
    <meta>
        <name>MyExampleApp</name>
        <label>Label</label>
        <label lang="de-DE">Name</label>
        <description>A description</description>
        <description lang="de-DE">Eine Beschreibung</description>
        <author>Your Company Ltd.</author>
        <copyright>(c) by Your Company Ltd.</copyright>
        <version>1.0.0</version>
        <license>MIT</license>
    </meta>
</manifest>
```

The app can now be installed by running `bin/console app:install --activate MyExampleApp`.

**Note:** Like with plugins, apps get installed as inactive. You can activate them by passing the `--activate` flag
to the `app:install` command or by executing the `app:activate` command.

### Meta data

At first, all general information will be stored in the manifest file. Via `<meta>` element you are able to define all
meta data of your app. Most are self-explanatory:

* `name`: This is the element for the technical name of your app and must equal the name of the folder your app is contained in
* `label`: In this element, you can set al label for your app. You can even use it to include translations using the `lang`
attribute
* `description`
* `author`
* `copyright`
* `version`
* `icon`: Optional, you can set the path to an icon that should be shown for your app, the icon needs to a `png` file.
* `license`
* `privacy`: Optional, in this element you can link to your privacy policy
* `privacyPolicyExtensions`: Optional, in this element you can describe the changes the shop owner needs to apply to his shops privacy policy, e.g. because you process personal information on an external server.
### Authorisation

Because the app uses the api for all its interactions, it needs credentials to gain full API access to the shop.
For a safe exchange a predetermined secret is needed. For development purposes this secret can be defined as 
through the `secret` element as part of the `setup` node as follows:

```xml
<setup>
    <secret>mysecret</secret>
</setup>
```

For live apps the secret will not be part of the manifest file, it will be saved in the app store.

With this secret your app can now verify that a shop that tries to connect to your app is authorised to do so.

To exchange credentials the app-system uses a http based workflow as follows:

Once the app is installed the shop will send a GET request to the endpoint given in the `registrationUrl` element:

```xml
<setup>
    ...
    <registrationUrl>https://my.example.com/registration</registrationUrl>
</setup>
```

A request is sent there, with the first part of the data needed to register the shop. The data comes as a query parameters:
`https://my.example.com/registration?shop-id=KIPf0Fz6BUkN&shop-url=http%3A%2F%2Fmy.shop.com&timestamp=1592397288`

The current shopware version will be sent as a `sw-version` header.

Your app can verify by checking that the header `shopware-app-signature` contains the sha256 hmac of the query string
signed with the secret defined in your manifest.

The signature is build like so:
```php
$signature = hash_hmac('sha256', $queryString, $this->secret);

```
You can take a look at the code [here](https://github.com/shopware/app-system/blob/6d2ed40f575392b8991a7c4bd36e64dae5f56b62/src/Core/Content/App/Lifecycle/Registration/PrivateHandshake.php#L53)


There may be valid cases where the app installation fails, because the domain is blocked,
or some other prerequisite in that shop is not met, in which case you can return the message error as follows
```json
{
  "error": "Shop url is not met"
}
```

The response is expected to contain a json object as follows
```json
{
  "proof": "94b42d39280141de84bd6fc8e538946ccdd182e4558f1e690eabb94f924e7bc7",
  "secret": "random secret string",
  "confirmation_url": "https://my.example.com/registration/confirm"
}
```

The proof is needed to verify your response. It consists of a sha256 hmac of the shopid, the shopUrl and your technical appname
 signed with the secret defined in your manifest.
 ```php
$proof = \hash_hmac('sha256', $shopId . $shopUrl . $appname, 'mysecret');
```

The secret in returned in the response will be used by the shop to sign all following requests to prove their 
authenticity.

Once the shop has validated your response it will create an api user and send its api credentials as a POST request to the confirmation url.
```json
{
  "apiKey":"SWIARXBSDJRWEMJONFK2OHBNWA",
  "secretKey":"Q1QyaUg3ZHpnZURPeDV3ZkpncXdSRzJpNjdBeWM1WWhWYWd0NE0",
  "timestamp":"1592398983",
  "shopUrl":"http:\/\/my.shop.com",
  "shopId":"sqX6cqHi6hbj"
}
```

The current shopware version will be sent as a `sw-version` header.

Your app needs to save both the apiKey as well as the secretKey in relation to the shopId. With these keys
you can authenticate your request against the shop api.

This request is also signed through the `shopware-shop-signature` header which contains the sha256 hmac of the whole request
body, this time signed with the secret the app returned above.

Once your app received the confirmation request you are good to go.

### Permissions

Your app needs permissions to read and write data and to receive events. To define the permissions, your app requests during the installation
 you add them to the `<permissions>` element. For each permission, add element as seen in the example below:

```xml
    <permissions>
        <create>product</create>

        <read>product</read>

        <update>product</update>
    </permissions>
```

You set permission to all entities available in Shopware. The permission types to choose from are defined in the 
scheme, e.g. `read`, `create` or `update`. Keep in mind that read permissions also extend to the data contained
in the requests so that your app needs read permissions for the entities contained in the subscribed events.

## Handling the migration of shops

In the real world it may happen that shops are migrated to new servers and are available under a new URL. In the same regard it is possible that a running production shop is duplicated and treated as a staging environment.
These cases are challenging for app developers.
In the first case you may have to make a request against the shop, but the URL you saved during the registration process may not be valid anymore and the shop cannot be reached over this URL.
In the second case you may receive webhooks from both shops (prod & staging), that look like they came from the same shop (as the whole database was duplicated), thus it may corrupt the data associated with the original production shop.
The main reason that this is problematic is that two Shopware installations in two different locations (on two different URLs) are associated to the same shopId, because the whole database was replicated.

That's why we implemented a safe-guard mechanism that detects such situations, stops the communication to the apps to prevent data corruption and then ultimately let's the user decide how to solve the situation.
**Notice: This mechanism relies on the fact that the `APP_URL` environment variable will be set to the correct URL to the shop. Especially it is assumed that the environment variable will be changed, when a shop is migrated to a new domain, or a staging shop is created as a duplicate of a production shop.**

Keep in mind that this is only relevant for apps that have their own backends and where communication between app backends and shopware is necessary. That's why simple themes are not affected by shop migrations, they will continue to work.

### Detecting APP_URL changes

Everytime a request should be made against an app backend, Shopware will check whether the current APP_URL differs from the one used when Shopware generated an ID for this shop.
If the APP_URL differs Shopware will stop sending any requests to the installed apps to prevent data corruption on the side of the apps.
Now the user has the possibility to resolve the solution, by using one of the following strategies.
The user can either run a strategy with the `bin/console app:url-change:resolve` command, or with a modal that pops up when the administration is opened.

### APP_URL change resolver

* **MoveShopPermanently**: This strategy should be used if the live production shop is migrated from one URL to another one.
    This strategy will ultimately notify all apps about the change of the APP_URL and the apps continue working like before, including all the data the apps may already have associated with the given shop. It is important to notice that in this case the apps in the old installation on the old URL (if it is still running) will stop working!
    Technically this is achieved by rerunning the registration process again for all apps. During the registration the same shopId is used like before, but now with a different shop-url and a different key pair used to communicate over the Shopware API. Also you **must** generate a new communication secret during this registration process, that is subsequently used for the communication between Shopware and the app backend.
    This way it is ensured that the apps are notified about the new URL and the integration with the old installation stops working (because a new communication secret is associated with the given shop id, that the old installation does not know).
    
* **ReinstallApps**: This strategy makes sense to use in the case of the staging shop.     
    By running this strategy all installed apps will be reinstalled, this means that this installation will get a new shopId, that is used during registration. 
    Because the new installation will get a new shopId, the installed apps will continue working on the old installation as before, but as a consequence the data on the apps side that was associated with the old shopId can not be accessed on the new installation.
    
* **UninstallApps**: This strategy will simply uninstall all apps on the new installation, thus keeping the old installation working like before.

## Server libraries

There are backend server libraries in various languages to get started with a remote app.

- [Go](https://github.com/shopwareLabs/GoAppserver)
- [Node.js](https://www.npmjs.com/package/@shopware-ag/swag-app-system-package)
- [PHP](https://github.com/shopwareLabs/AppTemplate)
