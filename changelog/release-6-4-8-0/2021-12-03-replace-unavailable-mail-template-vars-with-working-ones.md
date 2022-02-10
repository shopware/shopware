---
title: Replace unavailable mail template vars with working ones
issue: NEXT-18929
---
# Administration
* Added sorting of mail template lists, this affects:
  * `src/module/sw-mail-template/component/sw-mail-template-list/index.js`
  * `src/module/sw-mail-template/component/sw-mail-header-footer-list/index.js`
___
# Core
* Changed some mail templates to replace unavailable variables with working ones
* Added missing constants to `src/Core/Content/MailTemplate/MailTemplateTypes.php`:
  * `MAILTYPE_USER_RECOVERY_REQUEST`
  * `MAILTYPE_CUSTOMER_RECOVERY_REQUEST`