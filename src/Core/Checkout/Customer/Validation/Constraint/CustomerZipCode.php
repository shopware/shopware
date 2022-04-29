<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
class CustomerZipCode extends Constraint
{
    public const ZIP_CODE_INVALID = 'ZIP_CODE_INVALID';

    /**
     * @var string
     */
    public $message = 'This value is not a valid ZIP code for country {{ iso }}';

    /**
     * @var string
     */
    public $messageRequired = 'Postal code is required for that country';

    /**
     * @var string
     */
    public $countryId;

    /**
     * @var bool
     */
    public $caseSensitiveCheck = true;

    /**
     * @var array<string, string>
     */
    protected static $errorNames = [
        NotBlank::IS_BLANK_ERROR => 'IS_BLANK_ERROR',
        self::ZIP_CODE_INVALID => 'ZIP_CODE_INVALID',
    ];

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

        if ($this->countryId === null) {
            throw new MissingOptionsException(sprintf('The option "countryId" must be given for constraint %s', __CLASS__), ['countryId']);
        }
    }
}
