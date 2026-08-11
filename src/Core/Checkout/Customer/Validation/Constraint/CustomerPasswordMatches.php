<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation\Constraint;

use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterRemoval;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

#[Package('checkout')]
class CustomerPasswordMatches extends Constraint
{
    final public const CUSTOMER_PASSWORD_NOT_CORRECT = 'fe2faa88-34d9-4c3b-99b3-8158b1ed8dc7';

    protected const ERROR_NAMES = [
        self::CUSTOMER_PASSWORD_NOT_CORRECT => 'CUSTOMER_PASSWORD_NOT_CORRECT',
    ];

    /**
     * @deprecated tag:v6.8.0 - $message property access modifier will be changed to protected and is injectable via constructor
     */
    public string $message = 'Your password is wrong';

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use $salesChannelContext instead
     */
    protected SalesChannelContext $context;

    /**
     * @deprecated tag:v6.8.0 - Will be changed to natively typed in constructor injection
     */
    protected SalesChannelContext $salesChannelContext;

    /**
     * @param array{salesChannelContext?: SalesChannelContext, context?: SalesChannelContext}|null $options
     *
     * The `$message` property will be natively typed via constructor property promotion in v6.8.0.
     *
     * @internal
     */
    #[HasNamedArguments]
    #[ParameterRemoval(version: 'v6.8.0', parameterName: 'options', description: 'Use the $salesChannelContext argument instead.')]
    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'salesChannelContext', newType: SalesChannelContext::class, description: 'The parameter loses its null default, becomes required and a promoted property.')]
    public function __construct(?array $options = null, ?SalesChannelContext $salesChannelContext = null, string $message = 'Your password is wrong')
    {
        if ($options !== null || $salesChannelContext === null) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'Use $salesChannelContext argument instead of providing it in $options array')
            );
        }

        if ($options === null || Feature::isActive('v6.8.0.0')) {
            if ($salesChannelContext === null) {
                throw CustomerException::missingOption('salesChannelContext', self::class);
            }

            parent::__construct();

            $this->salesChannelContext = $salesChannelContext;
            $this->message = $message;
        } else {
            if (isset($options['context'])) {
                $options['salesChannelContext'] = $options['context'];
            }

            if (!($options['salesChannelContext'] ?? null) instanceof SalesChannelContext) {
                throw CustomerException::missingOption('salesChannelContext', self::class);
            }

            parent::__construct($options);
        }
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use getSalesChannelContext instead
     */
    public function getContext(): SalesChannelContext
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getSalesChannelContext')
        );

        return $this->salesChannelContext;
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
