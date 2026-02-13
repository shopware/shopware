<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Session;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;

/**
 * @internal
 */
#[Package('framework')]
class StorefrontSessionStorageFactory implements SessionStorageFactoryInterface
{
    /**
     * @param SessionStorageFactoryInterface $decorated The original Symfony factory
     */
    public function __construct(private SessionStorageFactoryInterface $decorated)
    {
    }

    public function createStorage(?Request $request): SessionStorageInterface
    {
        $storage = $this->decorated->createStorage($request);

        if ($request === null) {
            return $storage;
        }

        if (!$storage instanceof NativeSessionStorage) {
            return $storage;
        }

        $storage->setOptions([
            'cookie_path' => $request->attributes->get('sw-sales-channel-base-url') ?: '/',
        ]);

        return $storage;
    }
}
