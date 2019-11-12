<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate;

class MailTemplateTypes
{
    public const MAILTYPE_NEWSLETTER = 'newsletter';

    public const MAILTYPE_NEWSLETTER_DO_CONFIRM = 'newsletter_do_confirm'; // after subscription with confirm instructions

    public const MAILTYPE_NEWSLETTER_CONFIRMED = 'newsletter_confirmed'; // after confirmation is done

    public const MAILTYPE_DOCUMENT_DELIVERY_NOTE = 'delivery_mail';

    public const MAILTYPE_DOCUMENT_INVOICE = 'invoice_mail';

    public const MAILTYPE_DOCUMENT_CREDIT_NOTE = 'credit_note_mail';

    public const MAILTYPE_DOCUMENT_STORNO = 'storno_mail';

    public const MAILTYPE_ORDER_CONFIRM = 'order_confirmation_mail';

    public const MAILTYPE_PASSWORD_CHANGE = 'password_change';

    public const MAILTYPE_STOCK_WARNING = 'product_stock_warning';

    public const MAILTYPE_CUSTOMER_GROUP_CHANGE_ACCEPT = 'customer_group_change_accept';

    public const MAILTYPE_CUSTOMER_GROUP_CHANGE_REJECT = 'customer_group_change_reject';

    public const MAILTYPE_CUSTOMER_REGISTER = 'customer_register';

    public const MAILTYPE_SEPA_CONFIRMATION = 'sepa_confirmation';
    public const MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED_PARTIALLY = 'order_delivery.state.shipped_partially';
    public const MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED_PARTIALLY = 'order_transaction.state.refunded_partially';
    public const MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED = 'order_transaction.state.reminded';
    public const MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_OPEN = 'order_transaction.state.open';
    public const MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED_PARTIALLY = 'order_delivery.state.returned_partially';
    public const MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID = 'order_transaction.state.paid';
    public const MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED = 'order_delivery.state.returned';
    public const MAILTYPE_STATE_ENTER_ORDER_STATE_CANCELLED = 'order.state.cancelled';
    public const MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_CANCELLED = 'order_delivery.state.cancelled';
    public const MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED = 'order_delivery.state.shipped';
    public const MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED = 'order_transaction.state.cancelled';
    public const MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED = 'order_transaction.state.refunded';
    public const MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID_PARTIALLY = 'order_transaction.state.paid_partially';
    public const MAILTYPE_STATE_ENTER_ORDER_STATE_OPEN = 'order.state.open';
    public const MAILTYPE_STATE_ENTER_ORDER_STATE_IN_PROGRESS = 'order.state.in_progress';
    public const MAILTYPE_STATE_ENTER_ORDER_STATE_COMPLETED = 'order.state.completed';

    public const MAILTYPE_CONTACT_FORM = 'contact_form';
}
