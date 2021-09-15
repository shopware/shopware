<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Api;

use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal (flag:FEATURE_NEXT_8225)
 */
class FlowActionDefinition extends Struct
{
    protected string $name;

    protected array $requirements;

    public function __construct(string $name, array $requirements)
    {
        $this->name = $name;
        $this->requirements = $requirements;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getRequirements(): array
    {
        return $this->requirements;
    }

    public function setRequirements(array $requirements): void
    {
        $this->requirements = $requirements;
    }
}
