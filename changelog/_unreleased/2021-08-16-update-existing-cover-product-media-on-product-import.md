---
title: Update existing cover product media on product import
issue: NEXT-14710
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Core
* Changed `ProductSerializer` to fetch existing product media assocation for cover by product id and media id to prevent duplicate product media assocations on repeated imports
