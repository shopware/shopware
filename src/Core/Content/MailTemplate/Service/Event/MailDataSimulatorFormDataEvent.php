<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class MailDataSimulatorFormDataEvent extends Event
{
    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(
        public readonly string $variableName,
        public readonly string $flowEventName,
        public readonly Context $context,
        private ?array $data = null,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getData(): ?array
    {
        return $this->data;
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public function setData(?array $data): void
    {
        $this->data = $data;
    }
}
