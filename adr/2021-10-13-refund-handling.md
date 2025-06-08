---
title: Refund handling
date: 2021-10-13
area: checkout
tags: [payment, refund, capture]
---

## Context
Shopware offers no way of unified refund handling. This results in every payment extension either implementing it themselves or not at all.

## Decision
We want to implement the following structure to offer a unified refund handling for all extension types.

## New refund data structure
A payment extension will need to persist its actual captures to use the refund handling. Those captures are bound to a specific `OrderTransaction`. The capture has an amount, allows saving an external reference.

### OrderTransactionCapture
A capture is directly associated with a transaction.
This relation is n `order_transaction_capture`s to 1 `order_transaction`.

#### Database table
| Type              | Field name         | References             |
| ----------------- | ------------------ | ---------------------- |
| BINARY(16)        | id                 |
| BINARY(16)        | transaction_id     | order_transaction.id   |
| BINARY(16)        | state_id           | state_machine_state.id |
| VARCHAR(255) NULL | external_reference |
| LONGTEXT          | amount             |
| LONGTEXT NULL     | custom_fields      |

#### Entity
| Type                                  | Property name     |
| ------------------------------------- | ----------------- |
| string                                | id                |
| string                                | transactionId     |
| string                                | stateId           |
| string/null                           | externalReference |
| float                                 | totalAmount       |
| CalculatedPrice                       | amount            |
| array/null                            | customFields      |
| OrderTransactionEntity/null           | transaction       |
| StateMachineStateEntity/null          | stateMachineState |
| OrderTransactionRefundCollection/null | refunds           |

### OrderTransactionCaptureRefund
A refund is directly associated with a capture.
This relation is n `order_transaction_capture_refund`s to 1 `order_transaction_capture`.

#### Database table
| Type              | Field name         | References                   |
| ----------------- | ------------------ | ---------------------------- |
| BINARY(16)        | id                 |
| BINARY(16)        | capture_id         | order_transaction_capture.id |
| BINARY(16)        | state_id           | state_machine_state.id       |
| VARCHAR(255) NULL | reason             |
| LONGTEXT          | amount             |
| LONGTEXT NULL     | custom_fields      |
| VARCHAR(255) NULL | external_reference |

#### Entity
| Type                                                 | Property name      |
| ---------------------------------------------------- | ------------------ |
| string                                               | id                 |
| string                                               | captureId          |
| string                                               | stateId            |
| string/null                                          | externalReference  |
| string/null                                          | reason             |
| float                                                | totalAmount        |
| CalculatedPrice                                      | amount             |
| array/null                                           | customFields       |
| StateMachineStateEntity/null                         | stateMachineState  |
| OrderTransactionCaptureEntity/null                   | transactionCapture |
| OrderTransactionCaptureRefundPositionCollection/null | positions          |

### OrderTransactionCaptureRefundPosition
Refund positions are optional and only there if a refund is position-specific.
They relate n `order_transaction_capture_refund_position`s to 1 `order_transaction_capture_refund`.

#### Database table
| Type              | Field name    | References                          |
| ----------------- | ------------- | ----------------------------------- |
| BINARY(16)        | id            |
| BINARY(16)        | refund_id     | order_transaction_capture_refund.id |
| BINARY(16)        | line_item_id  | order_line_item.id                  |
| INT(11)           | quantity      |
| VARCHAR(255) NULL | reason        |
| LONGTEXT          | refund_amount |
| LONGTEXT NULL     | custom_fields |

#### Entity
| Type                                     | Property name                 |
| ---------------------------------------- | ----------------------------- |
| string                                   | id                            |
| string                                   | refundId                      |
| string                                   | lineItemId                    |
| string/null                              | reason                        |
| int                                      | quantity                      |
| float                                    | refundPrice                   |
| CalculatedPrice                          | refundAmount                  |
| array/null                               | customFields                  |
| OrderLineItemEntity/null                 | lineItem                      |
| OrderTransactionCaptureRefundEntity/null | orderTransactionCaptureRefund |

## Changes to existing entities
### PaymentMethod
* Add `refundHandlingEnabled` computed field if payment method handler implements `RefundHandlerInterface`

#### OrderTransaction
* Add OneToManyAssociation OrderTransactionCaptureCollection captures

#### OrderLineItem
* Add OneToManyAssociation OrderTransactionCaptureRefundPositionCollection|null refundPositions

## State machine
Add 2 new state machines for OrderTransactionCapture and OrderTransactionCaptureRefund.

### OrderTransactionCapture
We want to add the following states to a new `order_transaction_capture.state` state machine:
* pending
* completed
* failed

### OrderTransactionCaptureRefund
We want to add the following states to a new `order_transaction_capture_refund.state` state machine:
* open
* in_progress
* cancelled
* failed
* completed

## PaymentRefundHandlerInterface
Add an interface as outlined below:

```php
public function refund(string $orderRefundId, Context $context): void;
```

## PaymentRefundProcessor
The PaymentRefundProcessor gets triggered via a corresponding Admin-API action and contains the method `processRefund` as outlined below:

```php
public function processRefund(string $refundId, Context $context): Response;
```

## Apps
The whole refund handling should be available for apps and plugins. The following changes are required to allow apps to handle refunds.

### \Shopware\Core\Framework\App\Manifest\Xml\PaymentMethod
Add `refundUrl` to the manifest `PaymentMethod`. Also change the xsd accordingly.

### AppRefundHandler
Add an `AppRefundHandler`, which assembles payloads and talks to the app refund endpoint.

### Captures and apps
Captures are written over the Admin-API endpoint.
