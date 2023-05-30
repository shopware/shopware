<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation\Constraint;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @Annotation
 *
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
#[Package('customer-order')]
class CustomerZipCode extends Constraint
{
    final public const ZIP_CODE_INVALID = 'ZIP_CODE_INVALID';

    public ?string $countryId = null;

    public bool $caseSensitiveCheck = true;

    /**
     * @var array<string, string>
     */
    protected static $errorNames = [
        NotBlank::IS_BLANK_ERROR => 'IS_BLANK_ERROR',
        self::ZIP_CODE_INVALID => 'ZIP_CODE_INVALID',
    ];

    private string $message = 'This value is not a valid ZIP code for country {{ iso }}';

    private string $messageRequired = 'Postal code is required for that country';

    /**
     * @param mixed $options
     */
    public function __construct($options = null)
    {
        if ($options !== null && !\is_array($options)) {
            $options = [
                'countryId' => $options,
            ];
        }

        parent::__construct($options);
    }

    public function getMessageRequired(): string
    {
        return $this->messageRequired;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
