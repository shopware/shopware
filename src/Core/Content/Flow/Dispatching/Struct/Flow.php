<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Struct;

use Shopware\Core\Framework\Struct\Struct;

/**
 * @package business-ops
 *
 * @internal not intended for decoration or replacement
 */
class Flow extends Struct
{
    protected string $id;

    protected array $sequences = [];

    protected array $flat = [];

    public function __construct(string $id, array $sequences = [], array $flat = [])
    {
        $this->id = $id;
        $this->sequences = $sequences;
        $this->flat = $flat;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSequences(): array
    {
        return $this->sequences;
    }

    public function getFlat(): array
    {
        return $this->flat;
    }

    public function jump(string $id): void
    {
        $this->sequences = [$this->flat[$id] ?? []];
    }
}
