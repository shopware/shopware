<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Api\AbstractDto;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Sales channel context
 *
 * @codeCoverageIgnore
 */
final class SalesChannelContext extends AbstractDto
{
    /**
     * Context token
     */
    public string $token;

    /**
     * Core context with general configuration values and state
     */
    #[Assert\Valid]
    public SalesChannelContextContext $context;

    /**
     * Customer group of the current user
     */
    #[Assert\Valid]
    public SalesChannelContextCurrentCustomerGroup $currentCustomerGroup;

    /**
     * @var list<SalesChannelContextTaxRules> Active tax rules
     */
    #[Assert\Valid]
    public array $taxRules;

    /**
     * @internal
     */
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Valid]
        public SalesChannel $salesChannel,
        #[Assert\NotNull]
        #[Assert\Valid]
        public SalesChannelContextItemRounding $itemRounding,
    ) {
    }
}
