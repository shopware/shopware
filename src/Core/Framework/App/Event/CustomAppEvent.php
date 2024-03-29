<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Content\Flow\Dispatching\Aware\CustomAppAware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('core')]
class CustomAppEvent extends Event implements CustomAppAware, FlowEventAware
{
    /**
     * @param array<string, mixed>|null $appData
     */
    public function __construct(
        private readonly string $name,
        private readonly ?array $appData,
        private readonly Context $context
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCustomAppData(): ?array
    {
        return $this->appData;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
