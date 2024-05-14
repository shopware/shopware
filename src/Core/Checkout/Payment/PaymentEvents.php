<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class PaymentEvents
{
    final public const PAYMENT_METHOD_WRITTEN_EVENT = 'payment_method.written';

    final public const PAYMENT_METHOD_DELETED_EVENT = 'payment_method.deleted';

    final public const PAYMENT_METHOD_LOADED_EVENT = 'payment_method.loaded';

    final public const PAYMENT_METHOD_SEARCH_RESULT_LOADED_EVENT = 'payment_method.search.result.loaded';

    final public const PAYMENT_METHOD_AGGREGATION_LOADED_EVENT = 'payment_method.aggregation.result.loaded';

    final public const PAYMENT_METHOD_ID_SEARCH_RESULT_LOADED_EVENT = 'payment_method.id.search.result.loaded';

    final public const PAYMENT_METHOD_TRANSLATION_WRITTEN_EVENT = 'payment_method_translation.written';

    final public const PAYMENT_METHOD_TRANSLATION_DELETED_EVENT = 'payment_method_translation.deleted';

    final public const PAYMENT_METHOD_TRANSLATION_LOADED_EVENT = 'payment_method_translation.loaded';

    final public const PAYMENT_METHOD_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'payment_method_translation.search.result.loaded';

    final public const PAYMENT_METHOD_TRANSLATION_AGGREGATION_LOADED_EVENT = 'payment_method_translation.aggregation.result.loaded';

    final public const PAYMENT_METHOD_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'payment_method_translation.id.search.result.loaded';
}
