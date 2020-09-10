---
title:              Extend business events
issue:              NEXT-10702
flag:               FEATURE_NEXT_9351
author:             Oliver Skroblin
author_email:       o.skroblin@shopware.com
author_github:      @OliverSkroblin
---
# Core
* Added `event_action.active` field to enable or disable business event actions
* Added `event_action.rules` association
* Added `event_action_rule` entity to support rule whitelist for business events
* Added `mail_template_id` support for `\Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriber::sendMail`
* Added `\Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriber::SKIP_MAILS` which allows to disable mails 
