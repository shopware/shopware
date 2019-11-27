<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation\Constraint;

use Shopware\Core\Framework\Context;
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
     * @var Context
     */
    public $context;

    protected static $errorNames = [
        self::CUSTOMER_EMAIL_NOT_UNIQUE => 'CUSTOMER_EMAIL_NOT_UNIQUE',
    ];

    public function __construct(array $options)
    {
        $options = array_merge(
            ['context' => null],
            $options
        );

        parent::__construct($options);

        if ($this->context === null) {
            throw new MissingOptionsException(sprintf('Option "context" must be given for constraint %s', self::class), ['context']);
        }
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
