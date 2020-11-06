<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation\Constraint;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
class CustomerEmailUnique extends Constraint
{
    public const CUSTOMER_EMAIL_NOT_UNIQUE = '79d30fe0-febf-421e-ac9b-1bfd5c9007f7';

    public $message = 'The email address {{ email }} is already in use.';

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_10555) tag:v6.4.0 - `context` property can be removed, use `salesChannelContext` instead.
     *
     * @var Context
     */
    public $context;

    /**
     * @internal (flag:FEATURE_NEXT_10555)
     *
     * @var SalesChannelContext
     */
    public $salesChannelContext;

    protected static $errorNames = [
        self::CUSTOMER_EMAIL_NOT_UNIQUE => 'CUSTOMER_EMAIL_NOT_UNIQUE',
    ];

    public function __construct(array $options)
    {
        if (Feature::isActive('FEATURE_NEXT_10555')) {
            $options = array_merge(
                [
                    'context' => null,
                    'salesChannelContext' => null,
                ],
                $options
            );
        } else {
            $options = array_merge(
                ['context' => null],
                $options
            );
        }

        parent::__construct($options);

        if ($this->context === null) {
            throw new MissingOptionsException(sprintf('Option "context" must be given for constraint %s', self::class), ['context']);
        }

        if (Feature::isActive('FEATURE_NEXT_10555') && $this->salesChannelContext === null) {
            throw new MissingOptionsException(sprintf('Option "salesChannelContext" must be given for constraint %s', self::class), ['salesChannelContext']);
        }
    }

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_10555) tag:v6.4.0 - `getContext()` method can be removed, use `getSalesChannelContext()->getContext()` instead.
     */
    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @internal (flag:FEATURE_NEXT_10555)
     */
    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
