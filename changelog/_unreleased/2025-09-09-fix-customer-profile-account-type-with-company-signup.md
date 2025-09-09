---
title: Fix customer profile account type forced to commercial with company signup form
author: Dang Ng
---
# Storefront
* Changed `component/address/address-personal.html.twig` to fix an issue where customers with private account type were incorrectly shown as commercial when "Company signup form" was enabled for customer groups
* Removed the automatic forcing of commercial account type when `onlyCompanyRegistration` is enabled, allowing customers to maintain their correct account type (private/business)
* Removed the disabled state and hidden input field that was overriding the account type selection on the profile edit page