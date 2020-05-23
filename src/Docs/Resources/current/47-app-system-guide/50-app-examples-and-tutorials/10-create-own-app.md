[titleEn]: <>(Writing your own app)
[metaDescriptionEn]: <>(Here you can learn about how to write a first app)
[hash]: <>(article:app_write_own)

As we read everything about the app system in theory, lets look at it in the form of a tutorial. In this article, we
got you covered! 

## Own implementation outside of shopware

// TBA

## First steps in app

To get started with your app, create an `apps` folder in `custom` of your development template installation. In there, 
create another folder for your application and provide a manifest file in it.
```bash
...
└── custom
    ├── apps
    │   └── MyExampleApp
    │       └── manifest.xml
    └── plugins
...
```

After creating this file structure and the manifest file, you should be able to install your app via 
`bin/console app:refresh`.

### Set first configurations in Manifest file

```xml
<?xml version="1.0" encoding="UTF-8"?>
<manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="../../plugins/connect/src/Core/Content/App/Manifest/Schema/manifest-1.0.xsd">
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
    <permissions>
        <create>product</create>
        <create>product_visibility</create>
        <create>promotion</create>
        <create>promotion_individual_code</create>
        <create>customer</create>
        <create>customer_address</create>
        <create>state_machine_history</create>

        <list>tax</list>
        <list>currency</list>
        <list>promotion_individual_code</list>
        <list>salutation</list>
        <list>country</list>
        <list>customer_group</list>
        <list>payment_method</list>
        <list>order</list>

        <update>product</update>
        <update>order</update>
    </permissions>
</manifest>
```

## Using webhooks to subscribe to order

// TBA

## Add an own module

// TBA

## Add the action buttons to the administration

// TBA

## Add a custom field for the promotion code

// TBA

## Enable the customer to see the promotion code

// TBA


