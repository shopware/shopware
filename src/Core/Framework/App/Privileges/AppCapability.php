<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Privileges;

use Shopware\Core\Framework\Log\Package;

/**
 * Answers whether an app is allowed to perform a capability action, i.e. whether Shopware may
 * push checkout data to the app's tax provider, payment or checkout gateway handler. Reads the
 * granted privileges (acl_role.privileges); permissions that are only requested (pending
 * consent) do not count.
 *
 * @internal
 */
#[Package('framework')]
class AppCapability
{
    public function __construct(private readonly Privileges $privileges)
    {
    }

    public function can(string $appId, string $action): bool
    {
        $granted = $this->privileges->getPrivileges([$appId])[$appId] ?? [];

        return \in_array($action, $granted, true);
    }

    /**
     * Runs the callback only if the app has been granted the capability action, and returns its
     * result (or null when the permission is not granted).
     *
     * @template TReturn of mixed
     *
     * @param \Closure(): TReturn $callback
     *
     * @return TReturn|null the callback's return value, or null when the permission is not granted
     */
    public function whenGranted(string $appId, string $action, \Closure $callback): mixed
    {
        if (!$this->can($appId, $action)) {
            return null;
        }

        return $callback();
    }
}
