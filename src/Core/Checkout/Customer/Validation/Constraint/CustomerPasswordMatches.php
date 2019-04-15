<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation\Constraint;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * @copyright 2019 dasistweb GmbH (https://www.dasistweb.de)
 */
class CustomerPasswordMatches extends Constraint
{
    const CUSTOMER_PASSWORD_NOT_CORRECT = 'i dont know how to create this id';

    public $message = 'Your password is wrong';

    protected $context;

    protected static $errorNames = [
        self::CUSTOMER_PASSWORD_NOT_CORRECT => 'CUSTOMER_PASSWORD_NOT_CORRECT',
    ];

    public function __construct($options = null)
    {
        $options = array_merge(
            ['context' => null],
            $options
        );

        parent::__construct($options);

        if (!$this->context instanceof SalesChannelContext) {
            throw new MissingOptionsException(sprintf('Option "context" must be given for constraint %s', __CLASS__), ['context']);
        }
    }

    public function getContext(): SalesChannelContext
    {
        return $this->context;
    }
}
