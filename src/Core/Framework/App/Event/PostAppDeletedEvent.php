<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @final
 */
#[Package('framework')]
class PostAppDeletedEvent extends Event implements ShopwareEvent
{
    final public const NAME = 'app.deleted.post';

    public function __construct(
        public readonly string $appName,
        public readonly string $sourceType,
        private readonly Context $context,
        public readonly bool $keepUserData = false
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
