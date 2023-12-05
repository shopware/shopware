---
title: Catch numeric json param 
issue: NEXT-32101
---
# Storefront
* Changed `StorefrontController::decodeParam` to return an empty array if the decoded param is a number.
