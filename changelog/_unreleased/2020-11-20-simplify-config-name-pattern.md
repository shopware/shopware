---
title: Simplify config name pattern
issue: NEXT-12325
author: Joshua Behrens
author_email: code@joshua-behrens.de 
author_github: @JoshuaBehrens
---
# Core
* Changed regular expression for valid configuration names in `src/Core/System/SystemConfig/Schema/config.xsd` 
___
# Upgrade Information
System and plugin configurations made with `config.xml` can now have less than 4
characters as configuration key but are not allowed anymore to start with a
number.
