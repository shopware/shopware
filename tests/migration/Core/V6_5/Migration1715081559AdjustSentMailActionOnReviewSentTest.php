<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_5\Migration1715081559AdjustSentMailActionOnReviewSent;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_5\Migration1715081559AdjustSentMailActionOnReviewSent
 */
class Migration1715081559AdjustSentMailActionOnReviewSentTest extends TestCase
{
    use KernelTestBehaviour;
    use MigrationTestTrait;

    private Migration1715081559AdjustSentMailActionOnReviewSent $migration;

    protected function setUp(): void
    {
        $this->migration = new Migration1715081559AdjustSentMailActionOnReviewSent();
    }

    /**
     * @dataProvider flowSequences
     *
     * @param array<string, mixed>|null $expectedConfig
     */
    public function testMigration(?string $actionName, ?string $config, ?array $expectedConfig): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $flowSequenceId = Uuid::randomBytes();
        $flow_id = $this->getFlowId($connection);

        $this->insertTestDataSets($connection, $flowSequenceId, $actionName, $config, $flow_id);

        $this->migration->update($connection);
        $this->migration->update($connection);

        $result = $connection->createQueryBuilder()
            ->select('*')
            ->from('flow_sequence', 'fs')
            ->where('fs.id = (:flowSequenceId)')
            ->setParameter(
                'flowSequenceId',
                $flowSequenceId,
            )
            ->fetchAssociative();

        static::assertSame(
            $expectedConfig,
            json_decode($result['config'] ?? '', true)
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function flowSequences(): array
    {
        $mailTemplateId = Uuid::fromBytesToHex(self::getMailTemplateId());
        $wrongMailTemplateId = Uuid::randomHex();

        return [
            'nullConfig' => [
                'actionName' => 'action.mail.send',
                'config' => null,
                'expectedConfig' => null,
            ],
            'missingActionName' => [
                'actionName' => null,
                'config' => json_encode(self::getConfig($mailTemplateId)),
                'expectedConfig' => [
                    'recipient' => [
                        'data' => [],
                        'type' => 'default',
                    ],
                    'mailTemplateId' => Uuid::fromBytesToHex(self::getMailTemplateId()),
                    'documentTypeIds' => [],
                ],
            ],
            'wrongMailTemplateId' => [
                'actionName' => 'action.mail.send',
                'config' => json_encode(self::getConfig($wrongMailTemplateId)),
                'expectedConfig' => [
                    'recipient' => [
                        'data' => [],
                        'type' => 'default',
                    ],
                    'mailTemplateId' => $wrongMailTemplateId,
                    'documentTypeIds' => [],
                ],
            ],
            'validEntry' => [
                'actionName' => 'action.mail.send',
                'config' => json_encode(self::getConfig($mailTemplateId)),
                'expectedConfig' => [
                    'recipient' => [
                        'data' => [],
                        'type' => 'admin',
                    ],
                    'mailTemplateId' => Uuid::fromBytesToHex(self::getMailTemplateId()),
                    'documentTypeIds' => [],
                ],
            ],
            'corruptedConfig' => [
                'actionName' => 'action.mail.send',
                'config' => json_encode(self::getCorruptedConfig()),
                'expectedConfig' => [
                    'recipient' => [
                        'data' => [],
                    ],
                    'documentTypeIds' => [],
                ],
            ],
        ];
    }

    private function insertTestDataSets(
        Connection $connection,
        string $flowSequenceId,
        ?string $actionName,
        ?string $config,
        string $flowId,
    ): void {
        $connection->createQueryBuilder()
            ->insert('flow_sequence')
            ->values(
                [
                    'id' => ':id',
                    'flow_id' => ':flowId',
                    'action_name' => ':actionName',
                    'config' => ':config',
                    'created_at' => ':createdAt',
                ]
            )
            ->setParameter('id', $flowSequenceId)
            ->setParameter('flowId', $flowId)
            ->setParameter('actionName', $actionName)
            ->setParameter('config', $config)
            ->setParameter('createdAt', (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT))
            ->executeStatement();
    }

    private static function getMailTemplateId(): string
    {
        return self::getContainer()->get(Connection::class)->createQueryBuilder()
            ->select('mt.id')
            ->from('mail_template', 'mt')
            ->innerJoin('mt', 'mail_template_type', 'mtt', 'mtt.id = mt.mail_template_type_id')
            ->where('mtt.technical_name = "review_form"')
            ->fetchOne();
    }

    /**
     * @return array<string, mixed>
     */
    private static function getConfig(string $mailTemplateId): array
    {
        return [
            'recipient' => [
                'data' => [],
                'type' => 'default',
            ],
            'mailTemplateId' => $mailTemplateId,
            'documentTypeIds' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getCorruptedConfig(): array
    {
        return [
            'recipient' => [
                'data' => [],
            ],
            'documentTypeIds' => [],
        ];
    }

    private function getFlowId(Connection $connection): string
    {
        return $connection->createQueryBuilder()
            ->select('f.id')
            ->from('flow', 'f')
            ->where('f.event_name = "review_form.send"')
            ->fetchOne();
    }
}
