---
title: Add ACL for refund action  
issue: NEXT-21229  
author: Stephano Vogel  
author_email: s.vogel@shopware.com
---
# Administration
* Added **Refunds** permissions under **Orders**
___
# API
* Changed ACL for `Shopware\Core\Checkout\Order\Api\OrderActionController::refundOrderTransactionCapture` to `order_refund.editor`
___
# Core
* Added `order_refund` ACL group
* Changed `Shopware\Core\Framework\Migration\MigrationStep::fixRolePrivileges` to return unique entries
* Changed `Shopware\Core\Framework\Migration\MigrationStep::addAdditionalPrivileges` to use transaction
