[titleEn]: <>(App system FAQ)
[metaDescriptionEn]: <>(Frequently asked questions about the app system)
[hash]: <>(article:app_faq)

## Frequently asked questions

>Will it be possible to add cookies to the cookie consent manager in an app?

Yes, we want to add this feature in the future.

>Will it be possible to add a basic config.xml to an app

Yes, we are planning to provide a way to add a config.xml to an app.

>Are you going to create example tutorial connecting any other system to shopware?

We will expand and rework our current set of examples. We want to also provide tutorials and examples
of setting up apps.  
We are not planning on adding a tutorial for a specific system like a ERP system.

>Will it be possible to use an app in a cloud shop without releasing it in the store?

Yes, we want to provide a mechanism for these private apps. Currently we are evaluating ways to model this usecase.

>Are the webhooks sync or async?

Currently they are sync, but they will become async shortly.


>Will the admin-user be able to fire a webhook manually?

No, not directly. As webhooks represent business events, they can only be triggered by business processes.
For example `checkout.order.placed` can only be triggered by creation of an order, although this can happen 
in the administration as well as in the storefront.
For general admin user interaction you can use app action buttons.

>What about Errorhandling?

Once the app system is running asynchronous it will come with retry logic. 
Should the request to the app fail there is no need to manually retry it, it will be automatically retried 
by the message system in the Shopware core.

>Can we use the app system into Community Edition?

Yes, since shopware 6.3.3.0 the app-system is directly built into the shopware core.
If you run an older version of shopware you can install the app system plugin and you are ready to go:
https://github.com/shopware/app-system


>Can Apps be used in a Non-Cloud implementations?

The apps will run on every kind of Shopware 6 installation, no matter if it is
hosted in the cloud or not. But during the development of the app system it is
available as a plugin and therefore currently not available in the cloud. 


>Can I implement the APP with any Framework / Language I want to?

Absolutely, the app interface is based on HTTP requests. Your app can handle 
those requests in whatever language and framework you want.


>In which cases should I use a plugin and in which an app?

In general you should try and model your usecase through an app, to make it accessible for both cloud 
and self hosted versions. But some things require direct modification of the Shopware Core, for example 
changing the behaviour of the storefront search. These performance critical changes need to be made through a
plugin. 


> Are there any performance differences between plugins and apps?

Webhooks will be dispatched asynchronously so they will not affect the response times of the
Shopware instance. Plugins are executed synchronously, unless providing queue messages and their handlers, 
so its business logic will run during the request. The tradeoff is that plugins can change the response of a
request directly, whereas apps can only be notified of a change and then change the data afterwards.


>Is there anything that is exclusive to an app/plugin?

Apps can not change the Shopware core runtime. But we work to make apps
as powerful as they can be.


>Can app developers use platform.sh for free?

There is a free trial period for new customers of platform.sh.


>How can I identify the shop, when I send request directly from the storefront to my app backends?

You can use the twig variable `swagShopId` for this use case. This is the same shopId that is send with every request the Shopware backend makes against your app backend.
However keep in mind that this variable may be null, for example when the shop is running under a different URL, than the one it registered your app with.
