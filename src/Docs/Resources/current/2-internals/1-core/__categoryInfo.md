[titleEn]: <>(Core)
[hash]: <>(category:core)

Repository Link: [`https://github.com/shopware/platform/tree/master/src/Core`](https://github.com/shopware/platform/tree/master/src/Core)

The core is structured as a modular non strictly layered monolith. The modules are categorized and spread across different directories. 

```
<platform/src/Core/>
└── Checkout
└── Content
└── Flag
└── Framework
└── Migration
└── System
└── Kernel.php
└── [...]
```

The `Framework` is the technical basis for Shopware. `System`, `Content` and `Checkout` contain the eCommerce functionality, everything else is configuration.

The relation between these different categories is shown in the diagram below:

![Shopware Core](./dist/core-component.png)


## Functional layers

The relationship between the `eCommerce` and `Framework` directories is of course the most interesting. 

![Shopware Core eCommerce](./dist/core-component-eCommerce.png)

`Framework`
 : Framework itself is not the eCommerce Core, but enables eCommerce on a technical level. On the inside you find general abstractions for relational data handling, file handling, internationalization and routing and the general rest API. 

`System`
 : System provides a non technical basis for Shopware. It contains the elementary entities like configuration, number ranges or taxes. Additionally the System contains the entry point to the sales channel rest api.
 
`Content`
 : Content contains the user curated content to enable a rich shopping experience. Here you find products, the content management system and navigation amongst others.
    
`Checkout`
 : Checkout provides the workflow and process management of Shopware 6. The Checkout process contains the Cart, Orders, Payment and Shipping as well as state management and customer management.
 
 The whole structure follows a strict *domain first* approach. This means that you won't find a global `models` or `controllers` directory, but instead will find domain concepts as the scope of a module. So what you will find is a `Product` module or a `Language` module.
 
 ## Technical layers
 
 Inside each of these modules there is of course technical layering present. Shopware 6 employs a typical multitier architecture, with optional and required layers.
  
The required layers are `Presentation` and `Data access` with an optional `Application` and `Business` layer,  depending on the complexity of the case.
  
  ![layers](./dist/core-component-module-layers.png) 
 
 The modules are structured with discoverability as the central objective. You should take away the following:
 
 Aside from the `Framework` where modules are layed out according to technical necessities, the eCommerce modules follow a rigid structure. Modules are categorized according to certain characteristics:
 
 **Data store**
  : These modules are related to database tables and are manageable through the API. Simple CRUD actions will be available.
  
**Maintenance**
  : Provide commands executable through CLI to trigger maintenance tasks.
  
**Custom actions**
  : These modules contain more than simple CRUD actions. They provide special actions and services that ease management and additionally check internal consistency.
  
**SalesChannel-API**
 : These modules provide logic through a sales channel for the storefront.

Although a module can contain all or any of the characteristics above, this is not required. Please refer to the [list of modules and characteristics](./10-modules.md) to get a full overview.
