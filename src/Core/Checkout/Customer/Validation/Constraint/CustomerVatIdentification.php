<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation\Constraint;

use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterRemoval;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Shopware\Core\Framework\Deprecation\BCChange\VisibilityChange;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

#[Package('checkout')]
class CustomerVatIdentification extends Constraint
{
    final public const VAT_ID_FORMAT_NOT_CORRECT = '463d3548-1caf-11eb-adc1-0242ac120002';

    protected const ERROR_NAMES = [
        self::VAT_ID_FORMAT_NOT_CORRECT => 'VAT_ID_FORMAT_NOT_CORRECT',
    ];

    #[VisibilityChange(version: 'v6.8.0', newVisibility: 'protected', description: 'Use getMessage() instead.')]
    public string $message = 'The format of vatId {{ vatId }} is not correct.';

    protected string $countryId;

    protected bool $shouldCheck = false;

    /**
     * Also accept a VAT ID that matches the pattern of any other EU member state
     */
    protected bool $matchesAnyEuVat = false;

    /**
     * @param array{countryId?: string, shouldCheck?: bool, matchesAnyEuVat?: bool}|null $options
     *
     * The `$shouldCheck`, `$matchesAnyEuVat` and `$message` properties will be natively typed via constructor property promotion in v6.8.0.
     *
     * @internal
     */
    #[HasNamedArguments]
    #[ParameterRemoval(version: 'v6.8.0', parameterName: 'options', description: 'Use the named arguments instead.')]
    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'countryId', newType: 'string', description: 'The parameter loses its null default, becomes required and a promoted property.')]
    public function __construct(?array $options = null, ?string $countryId = null, bool $shouldCheck = false, string $message = 'The format of vatId {{ vatId }} is not correct.', bool $matchesAnyEuVat = false)
    {
        if ($options !== null || $countryId === null) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'Use $countryId argument instead of providing it in $options array')
            );
        }

        if ($options === null || Feature::isActive('v6.8.0.0')) {
            if ($countryId === null) {
                throw CustomerException::missingOption('countryId', self::class);
            }

            parent::__construct();

            $this->countryId = $countryId;
            $this->shouldCheck = $shouldCheck;
            $this->message = $message;
            $this->matchesAnyEuVat = $matchesAnyEuVat;
        } else {
            if ($countryId === null) {
                if (!\is_string($options['countryId'] ?? null)) {
                    throw CustomerException::missingOption('countryId', self::class);
                }

                if (isset($options['shouldCheck']) && !\is_bool($options['shouldCheck'])) {
                    throw CustomerException::invalidOption('shouldCheck', 'bool', self::class);
                }

                if (isset($options['matchesAnyEuVat']) && !\is_bool($options['matchesAnyEuVat'])) {
                    throw CustomerException::invalidOption('matchesAnyEuVat', 'bool', self::class);
                }
            }

            parent::__construct($options);
        }
    }

    public function getCountryId(): string
    {
        return $this->countryId;
    }

    public function getShouldCheck(): bool
    {
        return $this->shouldCheck;
    }

    public function getMatchesAnyEuVat(): bool
    {
        return $this->matchesAnyEuVat;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
