<?php

namespace Shopware\Core\Framework\MessageQueue\Stats;

use Psr\Container\ContainerInterface;
use Shopware\Core\Framework\Adapter\Messenger\Stamp\SentAtStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
class StatsService
{

    public function __construct(
        private readonly ContainerInterface $transportLocator,
        private readonly array $transportNames,
        private readonly RedisCircularBuffer $circularBuffer,
        private readonly MySQLStatsRepository $mySQLStatsRepository,
        private readonly int $timeToStore = 5 * 60,
        private readonly int $messageTypesLimit = 5,
    ) {}

    public function getStats(): array
    {
        return $this->mySQLStatsRepository->getStats($this->getStatsCutOff(), $this->messageTypesLimit);

        return [
            'handledCount' => 5,
            'handledSince' => (new \DateTime('now - 5minutes'))->format(\DateTime::ATOM),
            'averageTimeInQueue' => 5,
            'recentMessageTypes' => [
                [
                    'name' => 'Shopware\\Core\\Content\\Product\\DataAbstractionLayer\\ProductIndexingMessage',
                    'count' => 5,
                ],
                [
                    'name' => 'Shopware\\Core\\Content\\Product\\DataAbstractionLayer\\CategoryIndexingMessage',
                    'count' => 5,
                ],
            ],
        ];
    }

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

    public function registerMessage(string $transportName, Envelope $envelope): void
    {
        if (!$this->transportIsSupported($transportName)) {
            return;
        }

        $sentAtStamp = $envelope->last(SentAtStamp::class);
        if ($sentAtStamp === null) {
            return;
        }
        $now = new \DateTimeImmutable();

        $timeInQueue = $now->getTimestamp() - $sentAtStamp->getSentAt();
        $messageFqcn = \get_class($envelope->getMessage());
        $this->mySQLStatsRepository->insertMessageStats($transportName, $messageFqcn, $timeInQueue, $now);
        $this->mySQLStatsRepository->deleteStatsOlderThan($this->getStatsCutOff());

        $this->circularBuffer->add($transportName, \json_encode([
            'timestamp' => time(),
            'type' => $messageFqcn,
        ]));
    }

    private function getStatsCutOff(): \DateTimeInterface
    {
        return (new \DateTime('now - ' . $this->timeToStore . ' seconds'));
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
