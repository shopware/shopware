<?php declare(strict_types=1);

/**
 * @codeCoverageIgnore
 */
return [
    'checkout.customer.before.login' => <<<'EOD'
Triggers as soon as a customer logs in
EOD,
    'checkout.customer.deleted' => <<<'EOD'
Triggers if a customer gets deleted
EOD,
    'checkout.customer.double_opt_in_guest_order' => <<<'EOD'
Triggers as soon as double opt-in is accepted in a guest order
EOD,
    'checkout.customer.double_opt_in_registration' => <<<'EOD'
Triggers when a customer commits to his registration via double opt-in
EOD,
    'checkout.customer.guest_register' => <<<'EOD'
Triggers when a new guest customer was registered
EOD,
    'checkout.customer.login' => <<<'EOD'
Triggers as soon as a customer logs in
EOD,
    'checkout.customer.logout' => <<<'EOD'
Triggers when a customer logs out
EOD,
    'checkout.customer.register' => <<<'EOD'
Triggers when a new customer was registered
EOD,
    'checkout.order.payment_method.changed' => <<<'EOD'
Triggers when a user changed payment method during checkout process
EOD,
    'checkout.order.placed' => <<<'EOD'
Triggers when an order is placed
EOD,
    'contact_form.send' => <<<'EOD'
Triggers when a contact form is send
EOD,
    'customer.group.registration.accepted' => <<<'EOD'
Triggers when admin accepted a user who register to join a customer group
EOD,
    'customer.group.registration.declined' => <<<'EOD'
Triggers when admin declined a user who register to join a customer group
EOD,
    'customer.password.changed' => <<<'EOD'
Triggers when a customer changes the password
EOD,
    'customer.recovery.request' => <<<'EOD'
Triggers when a customer recovers his password
EOD,
    'mail.after.create.message' => <<<'EOD'
Triggers when a mail message/ content is created
EOD,
    'mail.before.send' => <<<'EOD'
Triggers before a mail is send
EOD,
    'mail.sent' => <<<'EOD'
Triggers when a mail is send from Shopware
EOD,
    'newsletter.confirm' => <<<'EOD'
Triggers when newsletter was confirmed by a user
EOD,
    'newsletter.register' => <<<'EOD'
Triggers when user registered to subscribe to a sales channel newsletter
EOD,
    'newsletter.unsubscribe' => <<<'EOD'
Triggers when user unsubscribe from a sales channel newsletter
EOD,
    'product_export.log' => <<<'EOD'
Triggers when product export is executed
EOD,
    'review_form.send' => <<<'EOD'
Triggers when a product review form is submitted by a customer
EOD,
    'state_enter.order.state.cancelled' => <<<'EOD'
Triggers when an order enters status "Cancelled"
EOD,
    'state_enter.order.state.completed' => <<<'EOD'
Triggers when an order enters status "Completed"
EOD,
    'state_enter.order.state.in_progress' => <<<'EOD'
Triggers when an order enters status "In progress"
EOD,
    'state_enter.order.state.open' => <<<'EOD'
Triggers when an order enters status "Open"
EOD,
    'state_enter.order_delivery.state.cancelled' => <<<'EOD'
Triggers when an order delivery enters status "Cancelled"
EOD,
    'state_enter.order_delivery.state.open' => <<<'EOD'
Triggers when an order delivery enters status "Open"
EOD,
    'state_enter.order_delivery.state.returned' => <<<'EOD'
Triggers when an order delivery enters status "Returned"
EOD,
    'state_enter.order_delivery.state.returned_partially' => <<<'EOD'
Triggers when an order delivery enters status "Return partially"
EOD,
    'state_enter.order_delivery.state.shipped' => <<<'EOD'
Triggers when an order delivery enters status "Shipped"
EOD,
    'state_enter.order_delivery.state.shipped_partially' => <<<'EOD'
Triggers when an order delivery enters status "Shipped partially"
EOD,
    'state_enter.order_transaction.state.authorized' => <<<'EOD'
Triggers when an order payment enters status "Authorized"
EOD,
    'state_enter.order_transaction.state.cancelled' => <<<'EOD'
Triggers when an order payment enters status "Cancelled"
EOD,
    'state_enter.order_transaction.state.chargeback' => <<<'EOD'
Triggers when an order payment enters status "Chargeback"
EOD,
    'state_enter.order_transaction.state.failed' => <<<'EOD'
Triggers when an order payment enters status "Failed"
EOD,
    'state_enter.order_transaction.state.in_progress' => <<<'EOD'
Triggers when an order payment enters status "In progress"
EOD,
    'state_enter.order_transaction.state.open' => <<<'EOD'
Triggers when an order payment enters status "Open"
EOD,
    'state_enter.order_transaction.state.paid' => <<<'EOD'
Triggers when an order payment enters status "Paid"
EOD,
    'state_enter.order_transaction.state.paid_partially' => <<<'EOD'
Triggers when an order payment enters status "Paid partially"
EOD,
    'state_enter.order_transaction.state.refunded' => <<<'EOD'
Triggers when an order payment enters status "Refunded"
EOD,
    'state_enter.order_transaction.state.refunded_partially' => <<<'EOD'
Triggers when an order payment enters status "Refunded partially"
EOD,
    'state_enter.order_transaction.state.reminded' => <<<'EOD'
Triggers when an order payment enters status "Reminded"
EOD,
    'state_enter.order_transaction.state.unconfirmed' => <<<'EOD'
Triggers when an order payment enters status "Unconfirmed"
EOD,
    'state_enter.order_transaction_capture.state.completed' => <<<'EOD'
Triggers when a payment capture is fully completed
EOD,
    'state_enter.order_transaction_capture.state.failed' => <<<'EOD'
Triggers when a payment capture attempt fails
EOD,
    'state_enter.order_transaction_capture.state.pending' => <<<'EOD'
Triggers when a payment capture is initiated and waiting for completion
EOD,
    'state_enter.order_transaction_capture_refund.state.cancelled' => <<<'EOD'
Triggers when a capture refund request is cancelled
EOD,
    'state_enter.order_transaction_capture_refund.state.completed' => <<<'EOD'
Triggers when a capture refund is completed
EOD,
    'state_enter.order_transaction_capture_refund.state.failed' => <<<'EOD'
Triggers when a capture refund fails
EOD,
    'state_enter.order_transaction_capture_refund.state.in_progress' => <<<'EOD'
Triggers when a capture refund is currently being processed
EOD,
    'state_enter.order_transaction_capture_refund.state.open' => <<<'EOD'
Triggers when a capture refund enters status "Open"
EOD,
    'state_leave.order.state.cancelled' => <<<'EOD'
Triggers when an order leaves status "Cancelled"
EOD,
    'state_leave.order.state.completed' => <<<'EOD'
Triggers when an order leaves status "Completed"
EOD,
    'state_leave.order.state.in_progress' => <<<'EOD'
Triggers when an order leaves status "In progress"
EOD,
    'state_leave.order.state.open' => <<<'EOD'
Triggers when an order leaves status "Open"
EOD,
    'state_leave.order_delivery.state.cancelled' => <<<'EOD'
Triggers when an order delivery leaves status "Cancelled"
EOD,
    'state_leave.order_delivery.state.open' => <<<'EOD'
Triggers when an order delivery leaves status "Open"
EOD,
    'state_leave.order_delivery.state.returned' => <<<'EOD'
Triggers when an order delivery leaves status "Returned"
EOD,
    'state_leave.order_delivery.state.returned_partially' => <<<'EOD'
Triggers when an order delivery leaves status "Return partially"
EOD,
    'state_leave.order_delivery.state.shipped' => <<<'EOD'
Triggers when an order delivery leaves status "Shipped"
EOD,
    'state_leave.order_delivery.state.shipped_partially' => <<<'EOD'
Triggers when an order delivery status is changed from “Shipped partially”
EOD,
    'state_leave.order_transaction.state.authorized' => <<<'EOD'
Triggers when an order payment leaves status "Authorized"
EOD,
    'state_leave.order_transaction.state.cancelled' => <<<'EOD'
Triggers when an order payment leaves status "Cancelled"
EOD,
    'state_leave.order_transaction.state.chargeback' => <<<'EOD'
Triggers when an order payment leaves status "Chargeback"
EOD,
    'state_leave.order_transaction.state.failed' => <<<'EOD'
Triggers when an order payment leaves status "Failed"
EOD,
    'state_leave.order_transaction.state.in_progress' => <<<'EOD'
Triggers when an order payment leaves status "In progress"
EOD,
    'state_leave.order_transaction.state.open' => <<<'EOD'
Triggers when an order payment leaves status "Open"
EOD,
    'state_leave.order_transaction.state.paid' => <<<'EOD'
Triggers when an order payment leaves status "Paid"
EOD,
    'state_leave.order_transaction.state.paid_partially' => <<<'EOD'
Triggers when an order payment leaves status "Paid partially"
EOD,
    'state_leave.order_transaction.state.refunded' => <<<'EOD'
Triggers when an order payment leaves status "Refunded"
EOD,
    'state_leave.order_transaction.state.refunded_partially' => <<<'EOD'
Triggers when an order payment leaves status "Refunded partially"
EOD,
    'state_leave.order_transaction.state.reminded' => <<<'EOD'
Triggers when an order payment leaves status "Reminded"
EOD,
    'state_leave.order_transaction.state.unconfirmed' => <<<'EOD'
Triggers when an order payment leaves status "Unconfirmed"
EOD,
    'state_leave.order_transaction_capture.state.completed' => <<<'EOD'
Triggers when a payment capture leaves status "Completed"
EOD,
    'state_leave.order_transaction_capture.state.failed' => <<<'EOD'
Triggers when a payment capture leaves status "Failed"
EOD,
    'state_leave.order_transaction_capture.state.pending' => <<<'EOD'
Triggers when a payment capture leaves "Pending" status
EOD,
    'state_leave.order_transaction_capture_refund.state.cancelled' => <<<'EOD'
Triggers when a capture refund leaves status "Cancelled"
EOD,
    'state_leave.order_transaction_capture_refund.state.completed' => <<<'EOD'
Triggers when a capture refund leaves status "Completed"
EOD,
    'state_leave.order_transaction_capture_refund.state.failed' => <<<'EOD'
Triggers when a capture refund leaves status "Failed"
EOD,
    'state_leave.order_transaction_capture_refund.state.in_progress' => <<<'EOD'
Triggers when a capture refund leaves "In progress" status
EOD,
    'state_leave.order_transaction_capture_refund.state.open' => <<<'EOD'
Triggers when a capture refund leaves status "Open"
EOD,
    'user.recovery.request' => <<<'EOD'
Triggers when a user created a password recovery request at admin
EOD,
];
