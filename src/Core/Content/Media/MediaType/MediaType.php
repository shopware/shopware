<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\MediaType;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('content')]
abstract class MediaType extends Struct
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var array<string>
     */
    protected $flags = [];

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

    public function addFlags(array $flags): self
    {
        $this->flags = array_merge($this->flags, $flags);

        return $this;
    }

    public function is(string $input): bool
    {
        foreach ($this->flags as $flag) {
            if ($flag === $input) {
                return true;
            }
        }

        return false;
    }

    public function getFlags(): array
    {
        return $this->flags;
    }

    public function getApiAlias(): string
    {
        return 'media_type_' . $this->name;
    }
}
