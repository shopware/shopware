[titleEn]: <>(Setup of app system)
[metaDescriptionEn]: <>(Here you can find all information you need concerning setup of apps)
[hash]: <>(article:app_setup)

## App system in Shopware platform

The app system itself is build as a Shopware 6 plugin. This way, you can install it as you're used to do with plugins.
Please clone the app system from its [repository in github](https://github.com/shopware/app-system).

Afterwards you can finish the installation via command line or by hand in the administration.

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
<manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/app-system/0.1.0/src/Core/Content/App/Manifest/Schema/manifest-1.0.xsd">
    <meta>
        <name>MyExampleApp</name>
        <label>Label</label>
        <label lang="de-DE">Name</label>
        <description>A description</description>
        <description lang="de-DE">Eine Beschreibung</description>
        <author>Your Company Ltd.</author>
        <copyright>(c) by Your Company Ltd.</copyright>
        <version>1.0.0</version>
    </meta>
    <permissions/>
</manifest>
```

The app can now be installed by running `bin/console app:install MyExampleApp`.

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

Your app can verify by checking that the header `shopware-app-signature` contains the sha256 hmac of the query string
signed with the secret defined in your manifest.

The signature is build like so:
```php
$signature = hash_hmac('sha256', $queryString, $this->secret);

```
You can take a look at the code [here](https://github.com/shopware/app-system/blob/6d2ed40f575392b8991a7c4bd36e64dae5f56b62/src/Core/Content/App/Lifecycle/Registration/PrivateHandshake.php#L53)


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

Your app needs to save both the apiKey as well as the secretKey in relation to the shopId. With these keys
you can authenticate your request against the shop api.

This request is also signed through the `shopware-shop-signature` header which contains the sha256 hmac of the whole request
body, this time signed with the secret the app returned above.

Once your app received the confirmation request you are good to go.

### Permissions

Your app should be able to work with the data store in your shop. To define the permissions, your app can get in
the `<permissions>` element. For each permission, please add an own element as seen in the example below:

```xml
    <permissions>
        <create>product</create>

        <read>product</read>

        <update>product</update>
    </permissions>
```

You set permission to all entities available in Shopware. The permission types to choose from are defined in the 
scheme, e.g. `read`, `create` or `update`.

