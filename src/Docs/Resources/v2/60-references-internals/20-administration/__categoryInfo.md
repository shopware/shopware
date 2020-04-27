[titleEn]: <>(Administration)
[hash]: <>(category:administration)

* [Repository Link](https://github.com/shopware/platform/tree/master/src/Adminitration/Resources/app/administration/source)
* [Component library](https://component-library.shopware.com ) 

The Administration is a Single Page Application that provides a rich user interface on top of REST-API based communication with the core. It is an Interaction-Oriented-System following the example of the Web-Components patterns - albeit through VueJS. 


```
<platform/src/Administration/Resources/app/administration/src/>
└── app
└── core
└── flag
└── module
```

The `src` root directory is structured along the three different use cases the administration faces: Provide common functionality, provide an application skeleton, provide modules.

`app` 
 : Contains the application basis for the administration. Generally you will find framework dependant computational components here.
 
`core`
 : Contains the binding to the core api and services.
 
`modules`
 : UI and state management of specific view pages, structured along the Backend core modules.
 
Contrary to the backend the administration contains no business logic. Therefore there is no functional layering but a flat list of modules.

The dependency structure is: 

![Shopware Administration](./dist/admin-component.png)


## Spreading of functionality

One module represents a navigation entry in the administrations main menu. Since the admin is highly dependant on the eCommerce core of the backend the module names reappear in the administration, albeit in a slightly different order. The main building block, which the administration knows, is called `component`, adjacent to Web-Components.

A `component` is the combination of *styling*, *markup* and *logic*. What a component does will not surprise you, if you already are familiar with the MVC-Pattern. The role of the model and controller collapses into a single class though. 

![Shopware Administration Component](./dist/admin-component-parts.png)

Components can be and often are nested.

## Module structure

Each module provides a maximum three different classes of components. These classes represent an escalation pattern against the depth of the contained structure.

```
└── page1
    └── view1
        └── component1
        └── component2
        └── component3
    └── view2
        └── component4
        └── component5
        └── component6
└── page2
    └── view3
        └── component7
        └── component8
        └── component9
    └── view4
        └── component10
        └── component11
        └── component12
            └── subcomponent1
            └── subcomponent2
                └── [...]
            
```

A `page` represents the entry point or the **page** that needs to be rendered and encapsulates views. A `view` is a subordinate part of the page that encapsulates components. A `component` can itself encapsulate different components, from this level on there is no distinction in the directory structure made.

At least one `page` is mandatory in each module. Though views and components can be present in the module a vast default component library is present to help with default cases.

## Cross cutting concerns

Apart from the - arguably most central - responsibility of creating the UI itself the administrations components implement a number of cross cutting concerns. The most important are:

Data management
 : The administration displays entities of the eCommerce Core and handles the management of this data. So of course REST-API access is an important concern of pages and views - where necessary. You will find many components working with in-memory representations of API-Data.
 
State-Management
 : In contrast to the backend the administration is a long running process contained in the Browser. Proper state management is key here. There is a router present handling the current page selection. View and component rendering is then local in relation to their parents. Therefore each component manages the state of its subcomponents.
 
So a more accurate representation of a typical module is this diagram:


![Shopware Administration Cross Cuttiong Concerns](./dist/admin-component-cross-cutting.png)
