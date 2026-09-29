<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Validation;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class RestrictDeleteViolation
{
    /**
     * @param array<string, list<array<string, mixed>>> $restrictions
     */
    public function __construct(
        /**
         * Contains an array which indexed by entity name.
         * Each value represents a single restricted identity
         *
         * Example:
         * [
         *      "order_customer" => [
         *          ["id => "cace68bdbca140b6ac43a083fb19f82b"],
         *          ["id => "50330f5531ed485fbd72ba016b20ea2a"],
         *      ],
         *      "order_address" => [
         *          ["id => "29d6334b01e64be28c89a5f1757fd661"],
         *          ["id => "484ef1124595434fa9b14d6d2cc1e9f8"],
         *          ["id => "601133b1173f4ca3aeda5ef64ad38355"],
         *          ["id => "9fd6c61cf9844a8984a45f4e5b55a59c"],
         *      ]
         * ]
         */
        private readonly array $restrictions
    ) {
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function getRestrictions(): array
    {
        return $this->restrictions;
    }
}
