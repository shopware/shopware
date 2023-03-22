<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
interface DemodataGeneratorInterface
{
    public function getDefinition(): string;

    /**
     * @param array<string, mixed> $options
     */
    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void;
}
