---
issue: NEXT-36018
title: Unify SendMailAction constants
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Deprecated constants `Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig::{ACTION_NAME,MAIL_CONFIG_EXTENSION}` use `Shopware\Core\Content\Flow\Dispatching\Action::{ACTION_NAME,MAIL_CONFIG_EXTENSION}` instead
* Deprecated constant `Shopware\Core\Content\MailTemplate\MailTemplateActions::MAIL_TEMPLATE_MAIL_SEND_ACTION` use `Shopware\Core\Content\Flow\Dispatching\Action::ACTION_NAME` instead
* Deprecated not needed class `Shopware\Core\Content\MailTemplate\MailTemplateActions`
