---
title: internal-classes
issue: NEXT-19173
author: Oliver Skroblin
author_email: o.skroblin@shopware.com
---
# Core
* Deprecated all service __construct(), where the class is initialized over the di container. The constructor is not part of our BC promise, and we are allowed to change it in any version
* Deprecated all classes within the *\Test\* namespace
* Deprecated different DAL classes. These class will be @internal or @final with the next major version. We want to provide a clean API and BC promise on repository and API level and want the possibility to optimize the DAL underlying layers within minor releases. 
___
# Storefront
* Deprecated all Storefront controller classes, they will be @internal with the next major release. The BC promise of our controller is based on the routing annotation not the php class implementation 