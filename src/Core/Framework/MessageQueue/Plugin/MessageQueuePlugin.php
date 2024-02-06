<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Plugin;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Shopware\Core\Framework\HealthCheck\Event\HealthCheckEvent;
use Shopware\Core\Framework\HealthCheck\Plugin\HealthCheckPluginInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;

class MessageQueuePlugin implements HealthCheckPluginInterface
{
    private const SERVICE_NAME_KEY = 'message_queue';
    private const TRANSPORT_NAME_KEY = 'transport';
    private const MESSAGE_COUNT_KEY = 'message_count';

    private array $transportNameList = [];

    public function __construct(
        private readonly ServiceLocator $receiverLocator
    )
    {
        $this->transportNameList = $this->receiverLocator->getProvidedServices();
    }

    /**
     * @return string
     */
    public function getServiceName(): string
    {
        return self::SERVICE_NAME_KEY;
    }

    /**
     * @return bool
     */
    public function isExecutable(): bool
    {
        return $this->transportNameList !== [];
    }

    /**
     * @param HealthCheckEvent $event
     *
     * @return HealthCheckEvent
     *
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function execute(HealthCheckEvent $event): HealthCheckEvent
    {
        if (!$this->isExecutable()) {
            return $event;
        }

        $transportDataList = $this->getTransportDataList();

        if ($transportDataList === []) {
            return $event;
        }

        $event->addServiceData(
            $this->getServiceName(),
            $transportDataList
        );

        return $event;
    }

    /**
     * @return array
     *
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    private function getTransportDataList(): array
    {
        $transportDataList = [];

        foreach ($this->transportNameList as $transportName => $transportData) {
            $transport = $this->receiverLocator->get($transportName);

            if (!$transport instanceof MessageCountAwareInterface) {
                continue;
            }

            $transportDataList[] = [
                self::TRANSPORT_NAME_KEY => $transportName,
                self::MESSAGE_COUNT_KEY => $transport->getMessageCount(),
            ];
        }

        return $transportDataList;
    }
}
