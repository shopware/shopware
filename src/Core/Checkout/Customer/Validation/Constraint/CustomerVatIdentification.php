<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation\Constraint;

use Shopware\Core\Framework\Context;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
class CustomerVatIdentification extends Constraint
{
    public const VAT_ID_FORMAT_NOT_CORRECT = '463d3548-1caf-11eb-adc1-0242ac120002';

    /**
     * @var string
     */
    public $message = 'The format of vatId {{ vatId }} is not correct.';

    /**
     * @var bool
     */
    public $shouldCheck = false;

    /**
     * @var Context
     */
    public $context;

    /**
     * @var string
     */
    protected $countryId;

    /**
     * @var array
     */
    protected static $errorNames = [
        self::VAT_ID_FORMAT_NOT_CORRECT => 'VAT_ID_FORMAT_NOT_CORRECT',
    ];

    public function __construct($options = null)
    {
        parent::__construct($options);

        if ($this->countryId === null) {
            throw new MissingOptionsException(sprintf('Option "countryId" must be given for constraint %s', self::class), ['countryId']);
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
}
