<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation\Constraint;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * @Annotation
 *
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
#[Package('customer-order')]
class CustomerEmailUnique extends Constraint
{
    final public const CUSTOMER_EMAIL_NOT_UNIQUE = '79d30fe0-febf-421e-ac9b-1bfd5c9007f7';

    public string $message = 'The email address {{ email }} is already in use.';

    protected Context $context;

    protected SalesChannelContext $salesChannelContext;

    /**
     * @var array<string, string>
     */
    protected static $errorNames = [
        self::CUSTOMER_EMAIL_NOT_UNIQUE => 'CUSTOMER_EMAIL_NOT_UNIQUE',
    ];

    /**
     * @internal
     */
    public function __construct(array $options)
    {
        if (!($options['context'] ?? null) instanceof Context) {
            throw new MissingOptionsException(sprintf('Option "context" must be given for constraint %s', self::class), ['context']);
        }

        if (!($options['salesChannelContext'] ?? null) instanceof SalesChannelContext) {
            throw new MissingOptionsException(sprintf('Option "salesChannelContext" must be given for constraint %s', self::class), ['salesChannelContext']);
        }

        parent::__construct($options);
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
