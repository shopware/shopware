---
title: Preview of available variables in e-mail templates
issue: NEXT-12654
flag: FEATURE_NEXT_12654
author: b.neumann
author_email: b.neumann@shopware.com 
---
# Core
*  Added new method `src/Core/Content/Mail/Service/MailService:buildRenderedTemplate`
*  Changed method `src/Core/Content/Mail/Service/MailService:send` to save template data with example content
___
# API
*  Added new route `/api/_action/mail-template/build`
___
# Administration
*  Added component `sw-tree` to `sw-mail-template-detail`
*  Added component `sw-modal` to `sw-mail-template-detail`
*  Added method `loadAvailableVariables` to `sw-mail-template-detail`
*  Added method `onClickShowPreview` to `sw-mail-template-detail`
*  Added method `onCancelShowPreview` to `sw-mail-template-detail`
*  Added method `onCopyVariable` to `sw-mail-template-detail`
*  Added method `onGetTreeItems` to `sw-mail-template-detail`
*  Added method `addVariables` to `sw-mail-template-detail`
*  Added method `loadInitialAvailableVariables` to `sw-mail-template-detail`
*  Added block `sw_mail_template_available_variables_tree` to `sw-mail-template-detail`
*  Added block `sw_mail_template_detail_preview_modal` to `sw-mail-template-detail`
*  Added block `sw_mail_template_detail_preview_modal_footer` to `sw-mail-template-detail`
*  Added block `sw_mail_template_detail_preview_modal_footer_cancel` to `sw-mail-template-detail`
*  Added method `buildRenderPreview` to `mail.api.service`
___
