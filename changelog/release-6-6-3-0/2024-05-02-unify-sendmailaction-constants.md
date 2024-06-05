---
issue: NEXT-36018
title: Unify SendMailAction constants
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Deprecated constants `Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig::{ACTION_NAME,MAIL_CONFIG_EXTENSION}` use `Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction::{ACTION_NAME,MAIL_CONFIG_EXTENSION}` instead
* Deprecated constant `Shopware\Core\Content\MailTemplate\MailTemplateActions::MAIL_TEMPLATE_MAIL_SEND_ACTION` use `Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction::ACTION_NAME` instead
* Deprecated not needed class `Shopware\Core\Content\MailTemplate\MailTemplateActions`
___
# Next Major Version Changes
## Removal of deprecations
* Removed constants `Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig::{ACTION_NAME,MAIL_CONFIG_EXTENSION}` use `Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction::{ACTION_NAME,MAIL_CONFIG_EXTENSION}` instead
* Removed constant `Shopware\Core\Content\MailTemplate\MailTemplateActions::MAIL_TEMPLATE_MAIL_SEND_ACTION` use `Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction::ACTION_NAME` instead
* Removed class `Shopware\Core\Content\MailTemplate\MailTemplateActions` without replacement
