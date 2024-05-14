---
title: Ensure product keyword dictionary cleanup is retrying on failure and reduce chance of table lock failures
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
issue: NEXT-31264
---
# Core
* Added retry loop and deletion limit to handler of `product_keyword_dictionary.cleanup` scheduled task 
