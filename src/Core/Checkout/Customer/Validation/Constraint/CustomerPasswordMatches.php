<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation\Constraint;

use Shopware\Core\Checkout\Customer\CustomerException;
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
     * @param ?array{salesChannelContext: SalesChannelContext} $options
     *
     * @deprecated tag:v6.8.0 - reason:new-optional-parameter - $options parameter will be removed, use $salesChannelContext instead
     * @deprecated tag:v6.8.0 - reason:new-optional-parameter - $salesChannelContext parameter will be required and natively typed as constructor property promotion
     *
     * @internal
     */
    #[HasNamedArguments]
    public function __construct(?array $options = null, ?SalesChannelContext $salesChannelContext = null)
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
}
