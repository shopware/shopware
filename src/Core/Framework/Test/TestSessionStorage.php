<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionBagProxy;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;

/**
 * @internal
 */
#[Package('core')]
class TestSessionStorage implements SessionStorageInterface
{
    private static array $data = [];

    private static string $id = 'test-id';

    private static string $name = 'session-';

    public function start(): bool
    {
        return true;
    }

    public function isStarted(): bool
    {
        return true;
    }

    public function getId(): string
    {
        return self::$id;
    }

    public function setId(string $id): void
    {
        self::$id = $id;
    }

    public function getName(): string
    {
        return self::$name;
    }

    public function setName(string $name): void
    {
        self::$name = $name;
    }

    public function regenerate(bool $destroy = false, ?int $lifetime = null): bool
    {
        if ($destroy) {
            $this->clear();
        }

        $this->setId(uniqid('sw', true));

        return true;
    }

    public function save(): void
    {
    }

    public function clear(): void
    {
        /** @var SessionBagInterface $bag */
        foreach (self::$data as $bag) {
            $bag->clear();
        }
    }

    public function getBag(string $name): SessionBagInterface
    {
        return self::$data[$name] ?? new FlashBag();
    }

    public function registerBag(SessionBagInterface $bag): void
    {
        if (isset(self::$data[$bag->getName()])) {
            /**
             * This early return protects multiple session objects to overwrite each others already filled bag
             *
             * But Symfony hacks on any session a array referenced to track did the user filled something in the session.
             * As the passed bag is not used here, it cannot track anything and says the session is empty and does not send any cookie
             *
             * To fix this behaviour we reference the new passed session bag data to the existing one in the sesion
             */
            $oldBag = self::$data[$bag->getName()];
            if ($oldBag instanceof SessionBagProxy) {
                $oldBagInner = $oldBag->getBag();
            } else {
                $oldBagInner = $oldBag;
            }

            if ($oldBagInner instanceof AttributeBag) {
                $f = \Closure::bind(static function (AttributeBag $attributeBag) use ($bag, $oldBag): void {
                    $bag->initialize($attributeBag->attributes);
                    $oldBag->initialize($attributeBag->attributes);
                }, null, AttributeBag::class);
                $f($oldBagInner);
            }

            if ($oldBagInner instanceof MetadataBag) {
                $f = \Closure::bind(static function (MetadataBag $attributeBag) use ($bag, $oldBag): void {
                    $bag->initialize($attributeBag->meta);
                    $oldBag->initialize($attributeBag->meta);
                }, null, MetadataBag::class);
                $f($oldBagInner);
            }

            if ($oldBagInner instanceof FlashBag) {
                $f = \Closure::bind(static function (FlashBag $attributeBag) use ($bag, $oldBag): void {
                    $bag->initialize($attributeBag->flashes);
                    $oldBag->initialize($attributeBag->flashes);
                }, null, FlashBag::class);
                $f($oldBagInner);
            }

            return;
        }

        self::$data[$bag->getName()] = $bag;
    }

    public function getMetadataBag(): MetadataBag
    {
        return new MetadataBag();
    }
}
