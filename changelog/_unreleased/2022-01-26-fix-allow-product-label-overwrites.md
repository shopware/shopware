---
title: Fix allow product label overwrites
issue: NEXT-19863
author: Nils Evers
author_email: evers.nils@gmail.com
author_github: NilsEvers
---
# Core
* Changed condition in `\Shopware\Core\Content\Product\Cart\ProductCartProcessor::enrich` to allow label overwrites for products in cart
