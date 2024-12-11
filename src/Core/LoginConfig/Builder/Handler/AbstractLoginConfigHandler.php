<?php declare(strict_types=1);

namespace Shopware\Core\LoginConfig\Builder\Handler;

use Shopware\Core\LoginConfig\Builder\LoginConfigItem;

/**
 * @internal
 */
abstract class AbstractLoginConfigHandler
{
    public function supports(string $type): bool
    {
        return $type === $this->getType();
    }

    abstract protected function getType(): string;

    /**
     * @return array<string, mixed>
     */
    abstract public function createTemplateData(LoginConfigItem $loginConfigItem): array;
}
