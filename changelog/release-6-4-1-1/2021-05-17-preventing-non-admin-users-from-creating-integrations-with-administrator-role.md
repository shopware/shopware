---
title: Preventing non-admin users from creating integrations with administrator role
issue: NEXT-14883
author: Markus Velt
author_email: m.velt@shopware.com 
author_github: @raknison
---
# Core
* Changed the default value to `false` of the `admin` property in `src/Core/System/Integration/IntegrationDefinition.php`
___
# API
* Changed routes `api.integration.create` and `api.integration.update` to protect the `admin` property.
___
# Administration
* Changed the privilege of the `Administration` sw-switch-field in the component `src/Administration/Resources/app/administration/src/module/sw-integration/page/sw-integration-list`. Now it is only enabled for administrator users.
