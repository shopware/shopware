<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Struct;

use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal API
 */
class Flow extends Struct
{
    protected string $id;

    protected array $sequences = [];

    public function __construct(string $id, array $sequences = [])
    {
        $this->id = $id;
        $this->sequences = $sequences;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSequences(): array
    {
        return $this->sequences;
    }
}
