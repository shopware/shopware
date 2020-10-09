[titleEn]: <>(App System in contrast to plugin and themes)
[metaDescriptionEn]: <>(In this article we explore the differences between apps, themes and plugins)
[hash]: <>(article:app_plugin_theme_differences)

If you are already familiar with the plugin system, you are probably wondering what will change for you in apps?
 
## Extension possibilities

If you used to write plugins, you will probably be used to everything working somehow. This is incredibly powerful 
on the one hand. On the other hand, it can be confusing at times.

With apps, the extension possibilities are much more defined. Especially in the beginning you will have to 
rethink sometimes or add a new event to Shopware via a github pull request.
 
## Simplicity

The possibility to connect events directly via webhooks, in order to integrate external pages into the admin via iframe 
or to address an external URL from the admin at the push of a button makes app development easier in many ways. 
Instead of asking yourself how to implement a 5 star rating component in your module with TwigJS and VueJS 
in an existing grid component, you build it directly in the way you want to have it 
- in the technologies you already know, for example with Bootstrap.

## Explanation of terms

To explain the differences between the plugins, apps and themes, let's start with a short definition of those three 
things:

* Plugins: A code extension that runs exclusively in the self-hosted (onPrem) variant of Shopware 
(Shopware 4, Shopware 5, Shopware 6).
* Apps: A new type of extension system that runs in both the self-hosted and cloud variants of Shopware 6.
* Themes: Provide a new design for the Shopware 6 storefront. Will be delivered either as an app or 
as a plugin

## Similarities of plugins and apps

Plugins and apps are both extension systems for Shopware. 
With them you can customize a Shopware shop according to your needs. Typical adaptations are for example:

* When an order is received, the following processes should be triggered (e.g. an e-mail with a voucher is sent)
* On certain pages (e.g. the product detail page) additional information should be displayed 
(e.g. "Recommended accessory products")
* Completely new functions and pages are to be created (for example, a separate notepad function)
* In the administration, interfaces are being extended or being created from scratch
* Integration of merchandise management, payment and shipping service providers

All these extensions are basically possible with plugins as well as with apps. 

## Important differences concerning plugins

The plugin system offers you the possibility to go deep into the Shopware core. In other words, the PHP code of 
your plugin is executed as if it were a normal part of the Shopware core. This offers many possibilities, 
but also poses challenges for developers:

* Plugins tend to be very sensitive to changes in the Shopware core. If the core changes at a point relevant 
to a plugin, the plugin must also be adapted.
* For the development of plugins, a developer needs to understand how Shopware itself works 
* Since the PHP code of your plugin is executed in Shopware itself, it could get impossible to deploy plugins securely 
and stably, as part of a SaaS offering. 

The app system is following a different approach. It is based on highly defined entry points, such as e.g.
`mail.sent` `checkout.customer.register` or `user.recovery.request`. These entry points are called "events". 
With your app you can define that Shopware throws a notification whenever such an event occurs 
- to an URL you have defined. Behind this URL, there's a program you have created, 
which runs your custom logic, e.g. sends an e-mail with a voucher to the customer who has just placed an order.

Apps bring several advantages with them:
* Since you run your extensions yourself, you can write them in any programming language. 
PHP, NodeJS, Java, Go... whatever you like best
* The system is faster to learn because there is a very defined list of entry points. 
A look into the Shopware source code to write an extension is virtually no longer necessary
* The different events are defined by Shopware. However, after that your app actually has very little to do 
with Shopware. Even someone who has never worked with Shopware before can start developing an app quickly that way.
* By the possibility to include self-hosted user-interfaces from your external implementation directly 
in the administration, such use cases become much easier as well.
* The app system can be used both in the self-hosted and in the SaaS variant of Shopware
