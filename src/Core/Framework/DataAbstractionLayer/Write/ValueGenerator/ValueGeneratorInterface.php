<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\ValueGenerator;

interface ValueGeneratorInterface
{
    public function generate($value = null): string;

    public function incrementBy($lastIncrement = null): int;

    public function getGeneratorId(): string;

    public function setConfiguration($configuration);
}
