<?php

namespace Shopware\Core\Framework\MessageQueue\Stats;

use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
class StatsService
{

    public function __construct(
        private readonly ContainerInterface $transportLocator,
        private readonly array $transportNames,
        private readonly RedisCircularBuffer $circularBuffer,
    ) {}

    public function getTransportsInfo(): array
    {
        $transportsInfo = [];

        foreach ($this->transportNames as $name) {

            $info = [
                'transportName' => $name,
                'totalMessages' => null,
            ];
            if ($this->transportLocator->has($name)) {
                $transport = $this->transportLocator->get($name);
                if ($transport instanceof MessageCountAwareInterface) {
                    $info['totalMessages'] = $transport->getMessageCount();
                }
            }
            $info['recentMessageTypes'] = $this->getLastMessagesTypes($name);
            $transportsInfo[] = $info;
        }

        return $transportsInfo;
    }

    public function registerMessage(string $transportName, object $message): void
    {
        if (!$this->transportIsSupported($transportName)) {
            return;
        }

        $type = $message::class;
        $this->circularBuffer->add($transportName, \json_encode([
            'timestamp' => time(),
            'type' => $type,
        ]));
    }

    private function getLastMessagesTypes(string $transportName): array
    {
        $types = [];
        $messageStats = $this->circularBuffer->get($transportName);
        foreach ($messageStats as $stat) {
            $decoded = \json_decode($stat, true);
            $types[] = $decoded['type'];
        }
        return $types;
    }

    private function transportIsSupported(string $name): string
    {
        return in_array($name, $this->transportNames);
    }
}
