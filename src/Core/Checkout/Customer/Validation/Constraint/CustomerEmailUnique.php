<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation\Constraint;

use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterRemoval;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Shopware\Core\Framework\Deprecation\BCChange\VisibilityChange;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

#[Package('checkout')]
class CustomerEmailUnique extends Constraint
{
    final public const CUSTOMER_EMAIL_NOT_UNIQUE = '79d30fe0-febf-421e-ac9b-1bfd5c9007f7';

    final public const CUSTOMER_EMAIL_NOT_UNIQUE_CODE = 'VIOLATION::CUSTOMER_EMAIL_NOT_UNIQUE';

    protected const ERROR_NAMES = [
        self::CUSTOMER_EMAIL_NOT_UNIQUE => 'CUSTOMER_EMAIL_NOT_UNIQUE',
    ];

    #[VisibilityChange(version: 'v6.8.0', newVisibility: 'protected', description: 'Use getMessage() instead.')]
    public string $message = 'The email address {{ email }} is already in use.';

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use $salesChannelContext instead
     */
    protected Context $context;

    protected SalesChannelContext $salesChannelContext;

    /**
     * @param array{salesChannelContext?: SalesChannelContext, context?: Context}|null $options
     *
     * @internal
     */
    #[HasNamedArguments]
    #[ParameterRemoval(version: 'v6.8.0', parameterName: 'options', description: 'Use the named arguments instead.')]
    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'salesChannelContext', newType: SalesChannelContext::class, description: 'The parameter loses its null default, becomes required and a promoted property.')]
    public function __construct(?array $options = null, ?SalesChannelContext $salesChannelContext = null, string $message = 'The email address {{ email }} is already in use.')
    {
        if ($options !== null || $salesChannelContext === null) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'Use $salesChannelContext argument instead of providing it in $options array')
            );
        }

        if ($options === null || Feature::isActive('v6.8.0.0')) {
            parent::__construct();

            if ($salesChannelContext === null) {
                throw CustomerException::missingOption('salesChannelContext', self::class);
            }

            $this->salesChannelContext = $salesChannelContext;
            $this->message = $message;
        } else {
            if (!($options['salesChannelContext'] ?? null) instanceof SalesChannelContext) {
                throw CustomerException::missingOption('salesChannelContext', self::class);
            }

            if (!isset($options['context'])) {
                $options['context'] = $options['salesChannelContext']->getContext();
            }

            if (!$options['context'] instanceof Context) {
                throw CustomerException::missingOption('context', self::class);
            }

            parent::__construct($options);
        }
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use getSalesChannelContext instead
     */
    public function getContext(): Context
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getSalesChannelContext->getContext()')
        );

        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
