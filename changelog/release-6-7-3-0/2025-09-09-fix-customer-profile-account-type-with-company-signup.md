---
title: Fix customer profile account type forced to commercial with company signup form
author: Dang Ng
---
# Storefront
* Changed `page/account/profile/index.html.twig` to fix an issue where logged-in customers couldn't change their account type when "Company signup form" (`onlyCompanyRegistration`) was enabled for their customer group
* Added logic to override `onlyCompanyRegistration` to `false` when a customer is logged in and editing their profile, allowing them to freely switch between private and business account types