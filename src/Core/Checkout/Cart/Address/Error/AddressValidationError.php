<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\Core\Checkout\Cart\Address\Error;

use Shopware\Core\Checkout\Cart\Error\Error;
use Symfony\Component\Validator\ConstraintViolationList;

class AddressValidationError extends Error
{
    private const KEY = 'address-invalid';

    /**
     * @var bool
     */
    private $isBillingAddress;

    /**
     * @var ConstraintViolationList
     */
    private $violations;

    public function __construct(bool $isBillingAddress, ConstraintViolationList $violations)
    {
        $this->isBillingAddress = $isBillingAddress;
        $this->violations = $violations;
        $this->message = sprintf(
            'Please check your %s address for missing or invalid values.',
            $isBillingAddress ? 'billing' : 'shipping'
        );

        parent::__construct($this->message);
    }

    public function getId(): string
    {
        return $this->getMessageKey();
    }

    public function getMessageKey(): string
    {
        return sprintf('%s-%s', $this->isBillingAddress ? 'billing' : 'shipping', self::KEY);
    }

    public function getLevel(): int
    {
        return self::LEVEL_ERROR;
    }

    public function blockOrder(): bool
    {
        return true;
    }

    public function getParameters(): array
    {
        return ['isBillingAddress' => $this->isBillingAddress, 'violations' => $this->violations];
    }

    public function isBillingAddress(): bool
    {
        return $this->isBillingAddress;
    }

    public function getViolations(): ConstraintViolationList
    {
        return $this->violations;
    }
}
