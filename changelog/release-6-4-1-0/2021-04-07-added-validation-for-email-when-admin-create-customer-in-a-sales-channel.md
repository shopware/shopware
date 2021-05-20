---
title: Add email validation when creating or updating a customer information in administration
issue: NEXT-13348
---
# API
* Added new route `/api/_admin/check-customer-email-valid` into `Shopware\Administration\Controller\AdministrationController` to validate email before submit when create/edit customer in administration.
___
# Administration
* Added service `src/Administration/Resources/app/administration/src/core/service/api/customer-validation.api.service.js`
* Added method `validateEmail` in `sw-customer-detail/index.js`
* Added method `validateEmail` in `sw-customer-create/index.js`
* Changed method `onSave` in `sw-customer-detail/index.js` to validate email before saving when edit a customer in administration.
* Changed method `onSave` in `sw-customer-create/index.js` to validate email before saving when create a customer in administration.
