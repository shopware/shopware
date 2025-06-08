<?php declare(strict_types=1);

return [
    'checkout.customer.before.login' => <<<'EOD'
Triggers as soon as a customer logs in
EOD
    ,
    'checkout.customer.changed-payment-method' => <<<'EOD'
Triggers when a customer changes his payment method in the checkout process
EOD
    ,
    'checkout.customer.deleted' => <<<'EOD'
Triggers if a customer gets deleted
EOD
    ,
    'checkout.customer.double_opt_in_guest_order' => <<<'EOD'
Triggers as soon as double opt-in is accepted in a guest order
EOD
    ,
    'checkout.customer.double_opt_in_registration' => <<<'EOD'
Triggers when a customer commits to his registration via double opt in
EOD
    ,
    'checkout.customer.guest_register' => <<<'EOD'
__EMPTY__
EOD
    ,
    'checkout.customer.login' => <<<'EOD'
Triggers as soon as a customer logs in
EOD
    ,
    'checkout.customer.logout' => <<<'EOD'
Triggers when a customer logs out
EOD
    ,
    'checkout.customer.register' => <<<'EOD'
Triggers when a new customer was registered
EOD
    ,
    'checkout.order.placed' => <<<'EOD'
Triggers when an order is placed
EOD
    ,
    'contact_form.send' => <<<'EOD'
Triggers when a contact form is send
EOD
    ,
    'customer.group.registration.accepted' => <<<'EOD'
__EMPTY__
EOD
    ,
    'customer.group.registration.declined' => <<<'EOD'
__EMPTY__
EOD
    ,
    'customer.recovery.request' => <<<'EOD'
Triggers when a customer recovers his password
EOD
    ,
    'mail.after.create.message' => <<<'EOD'
__EMPTY__
EOD
    ,
    'mail.before.send' => <<<'EOD'
Triggers before a mail is send
EOD
    ,
    'mail.sent' => <<<'EOD'
Triggers when a mail is send from Shopware
EOD
    ,
    'newsletter.confirm' => <<<'EOD'
__EMPTY__
EOD
    ,
    'newsletter.register' => <<<'EOD'
__EMPTY__
EOD
    ,
    'newsletter.unsubscribe' => <<<'EOD'
__EMPTY__
EOD
    ,
    'newsletter.update' => <<<'EOD'
__EMPTY__
EOD
    ,
    'product_export.log' => <<<'EOD'
__EMPTY__
EOD
    ,
    'review_form.send' => <<<'EOD'
Triggers when a product review form is send
EOD
    ,
    'state_enter.order.state.cancelled' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_enter.order.state.completed' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_enter.order.state.in_progress' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_enter.order.state.open' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_enter.order_delivery.state.cancelled' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_enter.order_delivery.state.open' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_enter.order_delivery.state.returned' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_enter.order_delivery.state.returned_partially' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_enter.order_delivery.state.shipped' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_enter.order_delivery.state.shipped_partially' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_enter.order_transaction.state.authorized' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_enter.order_transaction.state.cancelled' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_enter.order_transaction.state.chargeback' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_enter.order_transaction.state.failed' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_enter.order_transaction.state.in_progress' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_enter.order_transaction.state.open' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_enter.order_transaction.state.paid' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_enter.order_transaction.state.paid_partially' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_enter.order_transaction.state.refunded' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_enter.order_transaction.state.refunded_partially' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_enter.order_transaction.state.reminded' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_leave.order.state.cancelled' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_leave.order.state.completed' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_leave.order.state.in_progress' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_leave.order.state.open' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_leave.order_delivery.state.cancelled' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_leave.order_delivery.state.open' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_leave.order_delivery.state.returned' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_leave.order_delivery.state.returned_partially' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_leave.order_delivery.state.shipped' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_leave.order_delivery.state.shipped_partially' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_leave.order_transaction.state.authorized' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_leave.order_transaction.state.cancelled' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_leave.order_transaction.state.chargeback' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_leave.order_transaction.state.failed' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_leave.order_transaction.state.in_progress' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_leave.order_transaction.state.open' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_leave.order_transaction.state.paid' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_leave.order_transaction.state.paid_partially' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_leave.order_transaction.state.refunded' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_leave.order_transaction.state.refunded_partially' => <<<'EOD'
__EMPTY__
EOD
    ,
    'state_leave.order_transaction.state.reminded' => <<<'EOD'
__EMPTY__
EOD
    ,
    'user.recovery.request' => <<<'EOD'
__EMPTY__
EOD
    ,
    'product.written' => <<<'EOD'
__EMPTY__
EOD
    ,
    'product.deleted' => <<<'EOD'
__EMPTY__
EOD
    ,
    'product_price.written' => <<<'EOD'
__EMPTY__
EOD
    ,
    'product_price.deleted' => <<<'EOD'
__EMPTY__
EOD
    ,
    'category.written' => <<<'EOD'
__EMPTY__
EOD
    ,
    'category.deleted' => <<<'EOD'
__EMPTY__
EOD
    ,
    'sales_channel.written' => <<<'EOD'
__EMPTY__
EOD
    ,
    'sales_channel.deleted' => <<<'EOD'
__EMPTY__
EOD
    ,
    'customer.written' => <<<'EOD'
__EMPTY__
EOD
    ,
    'customer.deleted' => <<<'EOD'
__EMPTY__
EOD
    ,
    'customer_address.written' => <<<'EOD'
__EMPTY__
EOD
    ,
    'customer_address.deleted' => <<<'EOD'
__EMPTY__
EOD
    ,
    'product' => <<<'EOD'
__EMPTY__
EOD
    ,
    'product_price' => <<<'EOD'
__EMPTY__
EOD
    ,
    'category' => <<<'EOD'
__EMPTY__
EOD
    ,
    'sales_channel' => <<<'EOD'
__EMPTY__
EOD
    ,
    'customer' => <<<'EOD'
__EMPTY__
EOD
    ,
    'customer_address' => <<<'EOD'
__EMPTY__
EOD
    ,
];
