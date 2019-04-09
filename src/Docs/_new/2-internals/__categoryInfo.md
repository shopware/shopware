[titleEn]: <>(Internals)

The Shopware Platform is an online eCommerce platform. It provides Services through REST-APIs and rich user interfaces to customers and administrators alike.

## Context

![Shopware Platform Context](./dist/platform-context.png)

The diagram shows how the Shopware Platform fits into your enterprise. It provides web frontends for management and for commerce through a multitude of sales channels. It comes with a set of user facing interfaces and provides the ability to connect to your own infrastructure and outside services through REST-APIs.

# On the inside

![Shopware Platform Container](./dist/platform-container.png)

The [Shopware Platform][platform-gh] consists of 3 top level building blocks. The [**Core**][core] is the center of the Platform and wraps all eCommerce specific workflows and resources. The two satellites **Storefront** and [**Administration**][admin] provide web frontends for specific use cases. The **Storefront** is a Web-UI providing customer views and operating the sales channel interfaces of the core. The **Administration** on the other hand provides a Single Page Application that enables you to manage the core.

The next article introduces you to the [directory structure](./110-directory-structure/__categoryInfo.md) of all applications so you should be able to find the general place you are looking for. 

Or feel free to head over to the subsections introducing the three different applications in depth.

[platform-gh]: https://github.com/shopware/platform/tree/master/src
[core]: ./1-core/__categoryInfo.md
[admin]: ./2-administration/__categoryInfo.md
[storefront]: 13-storefront.md
