---
title: Prevent setting company field as required in shipping address with personal account type
issue: NEXT-15809
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Storefront
* Added data attribute `data-form-field-toggle-trigger-nested` for `FormFieldTogglePlugin` to declare if nested instances of the plugin should be triggered. This is meant for instances where form fields should not automatically be required but be dependent on a nested instance of `FormFieldTogglePlugin`.
