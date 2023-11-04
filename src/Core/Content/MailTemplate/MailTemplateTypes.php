<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate;

use Shopware\Core\Framework\Log\Package;

#[Package('sales-channel')]
class MailTemplateTypes
{
    final public const MAILTYPE_NEWSLETTER = 'newsletter';

    final public const MAILTYPE_NEWSLETTER_DO_CONFIRM = 'newsletter_do_confirm'; // after subscription with confirm instructions

    final public const MAILTYPE_NEWSLETTER_CONFIRMED = 'newsletter_confirmed'; // after confirmation is done

    final public const MAILTYPE_DOCUMENT_DELIVERY_NOTE = 'delivery_mail';

    final public const MAILTYPE_DOCUMENT_INVOICE = 'invoice_mail';

    final public const MAILTYPE_DOCUMENT_CREDIT_NOTE = 'credit_note_mail';

    final public const MAILTYPE_DOCUMENT_CANCELLATION_INVOICE = 'cancellation_mail';

    final public const MAILTYPE_ORDER_CONFIRM = 'order_confirmation_mail';

    final public const MAILTYPE_PASSWORD_CHANGE = 'password_change';

    final public const MAILTYPE_STOCK_WARNING = 'product_stock_warning';

    final public const MAILTYPE_USER_RECOVERY_REQUEST = 'user.recovery.request';

    final public const MAILTYPE_CUSTOMER_RECOVERY_REQUEST = 'customer.recovery.request';

    final public const MAILTYPE_CUSTOMER_GROUP_CHANGE_ACCEPT = 'customer_group_change_accept';

    final public const MAILTYPE_CUSTOMER_GROUP_CHANGE_REJECT = 'customer_group_change_reject';

    final public const MAILTYPE_CUSTOMER_GROUP_REGISTRATION_ACCEPTED = 'customer.group.registration.accepted';

    final public const MAILTYPE_CUSTOMER_GROUP_REGISTRATION_DECLINED = 'customer.group.registration.declined';

    final public const MAILTYPE_GUEST_ORDER_DOUBLE_OPT_IN = 'guest_order.double_opt_in';

    final public const MAILTYPE_CUSTOMER_REGISTER = 'customer_register';

    final public const MAILTYPE_CUSTOMER_REGISTER_DOUBLE_OPT_IN = 'customer_register.double_opt_in';

    final public const MAILTYPE_DOWNLOADS_DELIVERY = 'downloads_delivery';

    final public const MAILTYPE_SEPA_CONFIRMATION = 'sepa_confirmation';

    final public const MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED_PARTIALLY = 'order_delivery.state.shipped_partially';

    final public const MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED_PARTIALLY = 'order_transaction.state.refunded_partially';

    final public const MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED = 'order_transaction.state.reminded';

    final public const MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_OPEN = 'order_transaction.state.open';

    final public const MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED_PARTIALLY = 'order_delivery.state.returned_partially';

    final public const MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID = 'order_transaction.state.paid';

    final public const MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED = 'order_delivery.state.returned';

    final public const MAILTYPE_STATE_ENTER_ORDER_STATE_CANCELLED = 'order.state.cancelled';

    final public const MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_CANCELLED = 'order_delivery.state.cancelled';

    final public const MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED = 'order_delivery.state.shipped';

    final public const MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED = 'order_transaction.state.cancelled';

    final public const MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED = 'order_transaction.state.refunded';

    final public const MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID_PARTIALLY = 'order_transaction.state.paid_partially';

    final public const MAILTYPE_STATE_ENTER_ORDER_STATE_OPEN = 'order.state.open';

    final public const MAILTYPE_STATE_ENTER_ORDER_STATE_IN_PROGRESS = 'order.state.in_progress';

    final public const MAILTYPE_STATE_ENTER_ORDER_STATE_COMPLETED = 'order.state.completed';

    final public const MAILTYPE_CONTACT_FORM = 'contact_form';

    final public const MAILTYPE_REVIEW_FORM = 'review_form';
}
