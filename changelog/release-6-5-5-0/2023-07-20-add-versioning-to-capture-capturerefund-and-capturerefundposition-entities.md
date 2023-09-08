---
title: Add versioning to Refund related entities
issue: NEXT-29869
author: Mateusz Kowalski
author_email: matt@websolutionsnyc.com
author_github: @matkowalski
---
# Core
* Added `VersionField` into `OrderTransactionCaptureDefinition`, `OrderTransactionCaptureRefundDefinition`, and `OrderTransactionCaptureRefundPositionDefinition`
* Added property `orderTransactionVersionId` into `OrderTransactionCaptureEntity`
* Added `ReferenceVersionField` to reference to `OrderTransactionDefinition` into `OrderTransactionCaptureRefundDefinition`
* Added property `captureVersionId` into `OrderTransactionCaptureRefundEntity`
* Added `ReferenceVersionField` to reference to `OrderTransactionCaptureRefundDefinition` into `OrderTransactionCaptureRefundPositionDefinition`
* Added property `refundVersionId` into `OrderTransactionCaptureRefundPositionEntity`
* Added migration `Migration1689856589AddVersioningForOrderTransactionCaptures` to:
    * Added new version related columns to `order_transaction_capture`, `order_transaction_capture_refund` and `order_transaction_capture_refund_position` tables
    * Changed Primary Key for `order_transaction_capture`, `order_transaction_capture_refund` and `order_transaction_capture_refund_position` tables to rely on `id` and `version_id` fields
    * Changed Foreign Key for `order_transaction_capture`, `order_transaction_capture_refund` and `order_transaction_capture_refund_position` tables to support versioned primary keys
