<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Session;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

/**
 * FlashBag wrapper that keeps track whether any flash messages were displayed or not
 *
 * @internal
 */
#[Package('framework')]
class StatefulFlashBag implements FlashBagInterface
{
    private FlashBag $inner;

    private bool $displayedAnyFlashes = false;

    public function __construct()
    {
        $this->inner = new FlashBag();
    }

    public function add(string $type, mixed $message): void
    {
        $this->inner->add($type, $message);
    }

    public function set(string $type, array|string $messages): void
    {
        $this->inner->set($type, $messages);
    }

    public function peek(string $type, array $default = []): array
    {
        return $this->inner->peek($type, $default);
    }

    public function peekAll(): array
    {
        return $this->inner->peekAll();
    }

    public function get(string $type, array $default = []): array
    {
        if ($this->has($type)) {
            $this->displayedAnyFlashes = true;
        }

        return $this->inner->get($type, $default);
    }

    public function all(): array
    {
        $result = $this->inner->all();

        if ($result !== []) {
            $this->displayedAnyFlashes = true;
        }

        return $result;
    }

    public function setAll(array $messages): void
    {
        $this->inner->setAll($messages);
    }

    public function has(string $type): bool
    {
        return $this->inner->has($type);
    }

    public function keys(): array
    {
        return $this->inner->keys();
    }

    public function getName(): string
    {
        return $this->inner->getName();
    }

    public function initialize(array &$array): void
    {
        $this->inner->initialize($array);
    }

    public function getStorageKey(): string
    {
        return $this->inner->getStorageKey();
    }

    public function clear(): mixed
    {
        return $this->inner->clear();
    }

    public function hasAnyFlashes(): bool
    {
        return $this->keys() !== [];
    }

    public function displayedAnyFlashes(): bool
    {
        return $this->displayedAnyFlashes;
    }
}
