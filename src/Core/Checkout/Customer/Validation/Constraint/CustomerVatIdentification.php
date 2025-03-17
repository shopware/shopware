<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation\Constraint;

use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;

#[Package('checkout')]
class CustomerVatIdentification extends Constraint
{
    final public const VAT_ID_FORMAT_NOT_CORRECT = '463d3548-1caf-11eb-adc1-0242ac120002';

    protected const ERROR_NAMES = [
        self::VAT_ID_FORMAT_NOT_CORRECT => 'VAT_ID_FORMAT_NOT_CORRECT',
    ];

    public string $message = 'The format of vatId {{ vatId }} is not correct.';

    protected bool $shouldCheck = false;

    protected string $countryId;

    /**
     * @internal
     */
    public function __construct($options = null)
    {
        if (!\is_string($options['countryId'] ?? null)) {
            throw CustomerException::missingOption('countryId', self::class);
        }

        parent::__construct($options);
    }

    public function getCountryId(): string
    {
        return $this->countryId;
    }

    public function getShouldCheck(): bool
    {
        return $this->shouldCheck;
    }
}
