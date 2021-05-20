---
title: Fix test mails for inherited languages
issue: NEXT-14876
author: Jannis Leifeld
author_email: j.leifeld@shopware.com 
author_github: @jleifeld
---
# Administration
* Added inherited values for sending test mails in email template modules. This allows the user to send test mails with inherited values.
* Changed method `testMailTemplate` in `Resources/app/administration/src/core/service/api/mail.api.service` and added `translated` properties to `httpClient.post` request
* Changed computed prop `testMailRequirementsMet ` in `Resources/app/administration/src/module/sw-mail-template/page/sw-mail-template-detail/index.js` and added `translated` properties
