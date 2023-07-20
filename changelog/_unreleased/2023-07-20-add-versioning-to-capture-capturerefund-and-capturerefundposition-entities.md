---
title: Add versioning to Refund related entities
issue: X
author: Mateusz Kowalski
author_email: matt@websolutionsnyc.com
author_github: @matkowalski
---
# Core
* Added `version_id` field to `OrderTransactionCapture`, `OrderTransactionCaptureRefund` and `OrderTransactionCaptureRefundPosition` entities
* Changed relations in `OrderTransactionCaptureRefund` and `OrderTransactionCaptureRefundPosition` to support versioned field
* Added new version related columns to `order_transaction_capture`, `order_transaction_capture_refund` and `order_transaction_capture_refund_position` tables
* Changed Primary Key for `order_transaction_capture`, `order_transaction_capture_refund` and `order_transaction_capture_refund_position` tables to rely on `id` and `version_id` fields
* Changed Foreign Key for `order_transaction_capture`, `order_transaction_capture_refund` and `order_transaction_capture_refund_position` tables to support versioned primary keys
