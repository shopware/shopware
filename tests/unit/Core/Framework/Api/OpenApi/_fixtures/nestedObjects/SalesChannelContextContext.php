<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07
 */

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Core context with general configuration values and state
 */
final readonly class SalesChannelContextContext
{
    public function __construct(
        public ?string $versionId = null,
        public ?string $currencyId = null,
        public ?int $currencyFactor = null,
        public ?int $currencyPrecision = null,
        /**
         * @var list<string>
         */
        #[Assert\All(new Assert\Type('string'))]
        public ?array $languageIdChain = null,
        public ?string $scope = null,
        #[Assert\Valid]
        public ?SalesChannelContextContextSource $source = null,
        public ?string $taxState = null,
        public ?bool $useCache = null,
    ) {
    }
}
