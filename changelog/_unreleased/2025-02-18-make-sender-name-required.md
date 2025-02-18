---
title: Make sender name required for email templates 
author: Melvin Achterhuis
author_email: melvin@achterhuis.work
author_github: @MelvinAchterhuis
---
# Core
* Changed definition `\Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateTranslation\MailTemplateTranslationDefinition` to require `sender_name`.
* Added migration `\Shopware\Core\Migration\V6_7\Migration1739916340SenderNameRequired` to fix existing mail templates without a sender name.

# Administration
* Added prop `required` to `sw-text-field` element in `src/module/sw-mail-template/page/sw-mail-template-detail/sw-mail-template-detail.html.twig`.
* Added prop `required` to `sw-text-field` element in `src/module/sw-flow/component/modals/sw-flow-create-mail-template-modal/sw-flow-create-mail-template-modal.html.twig`.

