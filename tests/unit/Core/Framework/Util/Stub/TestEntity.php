<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Util\Stub;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

/**
 * @internal
 */
class TestEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $afterId = null;

    protected string $name;

    public function getAfterId(): ?string
    {
        return $this->afterId;
    }

    public function setAfterId(string $afterId): void
    {
        $this->afterId = $afterId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
