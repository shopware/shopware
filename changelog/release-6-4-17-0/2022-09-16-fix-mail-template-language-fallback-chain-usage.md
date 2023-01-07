---
title: Fix mail template language fallback chain usage
issue: NEXT-13730
author: Lars Kemper
author_email: l.kemper@shopware.com
author_github: LarsKemper
---
# Core
* Update and move `customer_group_change_accept` mail template to `src\Core\Migration\Fixtures\mails`
* Move `customer_group_change_reject` mail template to `src\Core\Migration\Fixtures\mails`
* Update and move `customer.group.registration.accepted` mail template to `src\Core\Migration\Fixtures\mails`
* Update and move `customer.group.registration.declined` mail template to `src\Core\Migration\Fixtures\mails`
* Update and move `guest_order.double_opt_in` mail template to `src\Core\Migration\Fixtures\mails`
* Update `MailTemplateTypes` in `src\Core\Content\MailTemplate`
* Create `Migration1663238480FixMailTemplateFallbackChainUsage` in `src\Core\Migration`
* Create `Migration1663238480FixMailTemplateFallbackChainUsageTest` in `tests\Migration\Core`
