<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Api\AbstractDto;
use Symfony\Component\JsonStreamer\Attribute\JsonStreamable;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Core context with general configuration values and state
 *
 * @codeCoverageIgnore
 */
#[JsonStreamable]
final class SalesChannelContextContext extends AbstractDto
{
    /**
     * @internal
     */
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
