<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata;

interface DemodataGeneratorInterface
{
    public function getDefinition(): string;

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void;
}
