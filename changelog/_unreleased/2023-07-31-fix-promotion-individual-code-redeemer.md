---
title: Fix promotion individual code redeemer
date: 2023-07-31
issue: NEXT-00000
author: Daniel Schönenborn
author_github: Drumm3r
---

## Core

* Add early return if no code is found in `Checkout/Promotion/Subscriber/PromotionIndividualCodeRedeemer.php` line 57
* Changed return to continue in `Checkout/Promotion/Subscriber/PromotionIndividualCodeRedeemer.php` line 72
