<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\MediaType;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('discovery')]
abstract class MediaType extends Struct
{
    protected string $name;

    /**
     * @var array<string>
     */
    protected array $flags = [];

    public function getName(): string
    {
        return $this->name;
    }

    public function setFlags(string ...$flags): self
    {
        $this->flags = $flags;

        return $this;
    }

    public function addFlag(string $flag): self
    {
        $this->flags[] = $flag;

        return $this;
    }

    /**
     * @param array<string> $flags
     */
    public function addFlags(array $flags): self
    {
        $this->flags = array_merge($this->flags, $flags);

        return $this;
    }

    public function is(string $input): bool
    {
        return \in_array($input, $this->flags, true);
    }

    /**
     * @return array<string>
     */
    public function getFlags(): array
    {
        return $this->flags;
    }

    public function getApiAlias(): string
    {
        return 'media_type_' . $this->name;
    }
}
