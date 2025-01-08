---
title: PHP class properties will be natively typed in the future
issue: NEXT-39436
author: Michael Telgmann
author_github: @mitelg
---
# Core
* Added a "deprecation" message to every PHP class property without a native type. They will be added with version 6.7.0.0.
___
# Administration
* Added a "deprecation" message to every PHP class property without a native type. They will be added with version 6.7.0.0.
___
# Storefront
* Added a "deprecation" message to every PHP class property without a native type. They will be added with version 6.7.0.0.
___
# Upgrade Information
## Native types for PHP class properties
A "deprecation" message was added to every PHP class property without a native type.
The native types will be added with Shopware 6.7.0.0.
If you extend classes with such properties, you will also need to add the type accordingly during the major update. 
___
# Next Major Version Changes
## Native types for PHP class properties
All PHP class properties now have a native type.
If you have extended classes with properties, which didn't have a native type before, make sure you now add them as well.
