[titleEn]: <>(Glossary)
[hash]: <>(article:glossary)

eCommerce and Shopware specific terms explained
 
(eCommerce) Core
 : The Core is the heart of Shopware 6. It houses the various eCommerce specific entities and workloads and provides REST interfaces to access, retrieve and modify data.
  
Satellite
 : Parts of Shopware 6 that are not the core. Although the Administration as well as the Storefront provide integral services they are not part of the eCommerce core but auxiliary bundles.
  
Entity
  : The name of a typable object mapped to a specific row in a database table. Is identifiable by an `id` and can be stored, retrieved and searched for.
  
REST-API
  : Shopware 6 primarily communicates through a JSON based REST-API with the outside world. 
  
REST-Resource
  : A Resource is the representation of a specific Entity in the System (e.g. Product, Catalogue, or Price). Whereas an Entity is mapped to a specific Database table a resource can have multiple table sources and is usually the result of an SQL-Query.
  
Sales channel
 : A sales channel is the combination of all customer facing data and workflows. It is part of the core.
 
Admin API
 : A REST-API providing access to all of Shopware 6.
  
SalesChannel-API
 : A REST-API limited by settings applied to a specific sales channel. 
  
Storefront
 : A satellite of the core. A Storefront is generally a customer facing interface that provides among other things a catalogue and order process.
 
Administration
  : A satellite of the core. The administration provides a user interface to manage the whole Shopware 6.
  
SPA (Single Page Application)
 : A rich client application in the browser.

Web-UI
 : A user interface for webbrowsers, based on HTML, CSS and JavaScript
