<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Api\Request\AbstractRequest;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The billing address
 *
 * @codeCoverageIgnore
 */
final class OrderBillingAddress extends AbstractRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $street,
        #[Assert\NotBlank]
        public string $city,
        #[Assert\NotBlank]
        public string $zipcode,
        /**
         * Country details
         */
        #[Assert\Valid]
        public ?OrderBillingAddressCountry $country = null,
    ) {
    }
}
