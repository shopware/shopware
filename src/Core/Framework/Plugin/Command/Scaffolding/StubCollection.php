<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Scaffolding;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @internal
 *
 * @extends Collection<Stub>
 */
#[Package('core')]
class StubCollection extends Collection
{
    /**
     * @param Stub[] $stubs
     */
    public function __construct(array $stubs = [])
    {
        foreach ($stubs as $stub) {
            $this->set($stub->getPath(), $stub);
        }
    }

    /**
     * @param Stub $element
     */
    public function add($element): void
    {
        $this->set($element->getPath(), $element);
    }

    /**
     * If the stub already exists, the content will be appended.
     * If the stub does not exist, it will be created.
     */
    public function append(string $path, string $content): self
    {
        if ($this->has($path)) {
            /** @var Stub $existing */
            $existing = $this->get($path);

            $content = $existing->getContent() . $content;
        }

        $this->set($path, Stub::raw($path, $content));

        return $this;
    }
}
