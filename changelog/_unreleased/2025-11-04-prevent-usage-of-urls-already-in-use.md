---
title: Prevent usage of URLs already in use
issue: https://github.com/shopware/shopware/issues/6434
author: Dominik Grothaus
---

# Core
* Add a constraint to check existing routes to prevent adding already in-use routes

___

# Administration
* Change the validation to include a constraint to check for existing routes when addin a SEO URL to a category
* Change the validation to include a constraint to check for existing routes when addin a SEO URL to a product
