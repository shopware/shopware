<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata;

use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - will be internal in 6.5.0
 */
#[Package('core')]
interface DemodataGeneratorInterface
{
    public function getDefinition(): string;

    /**
     * @param array<string, mixed> $options
     */
    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void;
}
