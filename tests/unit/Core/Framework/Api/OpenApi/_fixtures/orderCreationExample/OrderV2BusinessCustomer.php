<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace Shopware\Core\Checkout\Order\SalesChannel\Order;

use Shopware\Core\Framework\Api\AbstractDto;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class OrderV2BusinessCustomer extends AbstractDto
{
    /**
     * @internal
     */
    public function __construct(
        /**
         * Identifier of the customer record. Guest checkouts also have a customer record.
         */
        #[Assert\NotNull]
        #[Assert\Regex(pattern: '~^[0-9a-f]{32}$~')]
        public string $id,
        /**
         * Shopware customer account type.
         */
        #[Assert\NotNull]
        #[Assert\IdenticalTo(value: 'business')]
        public string $accountType,
        /**
         * Whether the order was placed using a guest customer account.
         */
        #[Assert\NotNull]
        public bool $guest,
        /**
         * Email address captured for the order customer.
         */
        #[Assert\NotNull]
        #[Assert\Email]
        public string $email,
        /**
         * First name captured for the order customer.
         */
        #[Assert\NotNull]
        public string $firstName,
        /**
         * Last name captured for the order customer.
         */
        #[Assert\NotNull]
        public string $lastName,
        /**
         * Company name captured for the business customer.
         */
        #[Assert\NotNull]
        public string $company,
        /**
         * @var list<string> VAT registration identifiers captured for the business customer.
         */
        #[Assert\NotNull]
        #[Assert\Count(min: 1)]
        #[Assert\All(new Assert\Type('string'))]
        public array $vatIds,
    ) {
    }
}
