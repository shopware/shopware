<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('framework')]
class AppPermissionsRequested extends Event implements ShopwareEvent
{
    final public const NAME = 'app.permissions.requested';

    /**
     * @param array<string> $permissions
     */
    public function __construct(
        public readonly string $appId,
        public readonly array $permissions,
        private readonly Context $context,
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
