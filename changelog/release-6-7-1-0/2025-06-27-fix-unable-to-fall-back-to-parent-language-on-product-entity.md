---
title: Fix unable to fall back to parent language on product entity
issue: 9909
---
# Administration
* Changed `validate` method in `entity-validation` service to allow ignoring fields when validating product entity.
* Changed method `onSave` in `sw-product-detail` component to set ignore fields to validate product entity with ignored field.
* Added `ignoreFieldsValidate` computed in `sw-product-detail` component to get ignore fields with language inherited.
* Added `loadLanguage` method in `sw-product-detail` component to load language data when change language.
