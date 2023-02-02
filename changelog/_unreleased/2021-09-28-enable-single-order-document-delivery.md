---
title: Enable single order document delivery
issue: NEXT-16681
flag: FEATURE_NEXT_7530
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com 
author_github: seggewiss
---
# Core
* Deprecated `\Shopware\Core\Content\MailTemplate\MailTemplateTypes::MAILTYPE_DOCUMENT_STORNO`
* Added `\Shopware\Core\Content\MailTemplate\Service\AbstractAttachmentService`
* Added `\Shopware\Core\Content\MailTemplate\Service\AttachmentService`
* Added MailTemplateType `\Shopware\Core\Content\MailTemplate\MailTemplateTypes::MAILTYPE_DOCUMENT_INVOICE`
* Added MailTemplateType `\Shopware\Core\Content\MailTemplate\MailTemplateTypes::MAILTYPE_DOCUMENT_DELIVERY_NOTE`
* Added MailTemplateType `\Shopware\Core\Content\MailTemplate\MailTemplateTypes::MAILTYPE_DOCUMENT_CREDIT_NOTE`
* Added MailTemplateType `\Shopware\Core\Content\MailTemplate\MailTemplateTypes::MAILTYPE_DOCUMENT_CANCELLATION_INVOICE`
* Added MailTemplate for MailTemplateType `\Shopware\Core\Content\MailTemplate\MailTemplateTypes::MAILTYPE_DOCUMENT_INVOICE`
* Added MailTemplate for MailTemplateType `\Shopware\Core\Content\MailTemplate\MailTemplateTypes::MAILTYPE_DOCUMENT_DELIVERY_NOTE`
* Added MailTemplate for MailTemplateType `\Shopware\Core\Content\MailTemplate\MailTemplateTypes::MAILTYPE_DOCUMENT_CREDIT_NOTE`
* Added MailTemplate for MailTemplateType `\Shopware\Core\Content\MailTemplate\MailTemplateTypes::MAILTYPE_DOCUMENT_CANCELLATION_INVOICE`
___
# API
* Added parameter `documentIds` to route `api.action.mail_template.send`
___
# Administration
* Added `sendMailTemplate` function to `mail.api.service`
___
# Upgrade Information
## Core
* Replace `\Shopware\Core\Content\MailTemplate\MailTemplateTypes::MAILTYPE_DOCUMENT_STORNO` with `\Shopware\Core\Content\MailTemplate\MailTemplateTypes::MAILTYPE_DOCUMENT_CANCELLATION_INVOICE`.
