<?php declare(strict_types=1);

namespace src\Core\Content\Test\MailTemplate\Repository;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class MailTemplateRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour, MediaFixtures;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $this->repository = $this->getContainer()->get('mail_template.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
    }

    /**
     * Test single CREATE
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public function testMailTemplateSingleCreate(): void
    {
        $data = $this->prepareTemplateTestData();

        $id = array_key_first($data);

        $this->repository->create([$data[$id]], $this->context);

        $record = $this->connection->fetchAssoc(
            'SELECT * 
                        FROM mail_template mt
                        JOIN mail_template_translation mtt ON mt.id=mtt.mail_template_id
                        WHERE id = :id',
            ['id' => $id]
        );

        $expect = $data[$id];
        static::assertNotEmpty($record);
        static::assertEquals($id, $record['id']);
        static::assertEquals($expect['systemDefault'], (bool) $record['system_default']);
        static::assertEquals($expect['mailType'], $record['mail_type']);
        static::assertEquals($expect['description'], $record['description']);
        static::assertEquals($expect['senderName'], $record['sender_name']);
        static::assertEquals($expect['senderMail'], $record['sender_mail']);
        static::assertEquals($expect['subject'], $record['subject']);
        static::assertEquals($expect['contentHtml'], $record['content_html']);
        static::assertEquals($expect['contentPlain'], $record['content_plain']);
    }

    /**
     * Test multiple CREATE
     */
    public function testMailTemplateMultiCreate(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $data = [
            $id1 => [
                'id' => $id1,
                'systemDefault' => true,
                'mailType' => 'default',
                'description' => 'unit test description',
                'senderName' => 'foo Bar',
                'senderMail' => 'foo@bar.com',
                'subject' => 'unit test',
                'contentPlain' => 'unit test',
                'contentHtml' => 'unit test',
            ],
            $id2 => [
                'id' => $id2,
                'systemDefault' => false,
                'mailType' => 'notDefault',
                'description' => 'unit test description',
                'senderName' => 'foo Bar',
                'senderMail' => 'foo@bar.com',
                'subject' => 'unit test',
                'contentPlain' => 'unit test',
                'contentHtml' => 'unit test',
            ],
        ];

        $this->repository->create(array_values($data), $this->context);

        $ids = array_column($data, 'id');
        $records = $this->connection->fetchAll(
            'SELECT * 
                        FROM mail_template mt
                        JOIN mail_template_translation mtt ON mt.id=mtt.mail_template_id
                      '
        );

        $records = array_filter($records, function ($record) use ($ids) { return in_array(Uuid::fromBytesToHex($record['id']), $ids, true); });

        static::assertEquals(count($data), count($records));
        foreach ($records as $record) {
            $expect = $data[Uuid::fromBytesToHex($record['id'])];
            static::assertEquals($expect['systemDefault'], (bool) $record['system_default']);
            static::assertEquals($expect['mailType'], $record['mail_type']);
            static::assertEquals($expect['description'], $record['description']);
            static::assertEquals($expect['senderName'], $record['sender_name']);
            static::assertEquals($expect['senderMail'], $record['sender_mail']);
            static::assertEquals($expect['subject'], $record['subject']);
            static::assertEquals($expect['contentHtml'], $record['content_html']);
            static::assertEquals($expect['contentPlain'], $record['content_plain']);
            unset($data[$record['id']]);
        }
    }

    /**
     * Test READ
     *
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    public function testMailTemplateRead(): void
    {
        $num = 10;
        $data = $this->prepareTemplateTestData($num);

        $this->repository->create(array_values($data), $this->context);

        foreach ($data as $expect) {
            $id = $expect['id'];
            /** @var MailTemplateEntity $mailTemplate */
            $mailTemplate = $this->repository->search(new Criteria([$id]), $this->context)->get($id);
            static::assertEquals($expect['systemDefault'], $mailTemplate->getSystemDefault());
            static::assertEquals($expect['mailType'], $mailTemplate->getMailType());
            static::assertEquals($expect['description'], $mailTemplate->getDescription());
            static::assertEquals($expect['senderName'], $mailTemplate->getSenderName());
            static::assertEquals($expect['senderMail'], $mailTemplate->getSenderMail());
            static::assertEquals($expect['subject'], $mailTemplate->getSubject());
            static::assertEquals($expect['contentHtml'], $mailTemplate->getContentHtml());
            static::assertEquals($expect['contentPlain'], $mailTemplate->getContentPlain());
        }
    }

    /**
     * Test UPDATE
     */
    public function testMailTemplateUpdate(): void
    {
        $num = 10;
        $data = $this->prepareTemplateTestData($num);
        $ids = array_column($data, 'id');

        $this->repository->create(array_values($data), $this->context);

        $new_data = array_values($this->prepareTemplateTestData($num, 'xxx'));

        foreach ($data as $id => $value) {
            $new_value = array_pop($new_data);
            $new_value['id'] = $value['id'];
            $data[$id] = $new_value;
        }

        $this->repository->upsert(array_values($data), $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $ids));
        $records = $this->repository->search($criteria, Context::createDefaultContext())->getEntities();

        /** @var MailTemplateEntity $record */
        foreach ($records as $record) {
            $expect = $data[Uuid::fromHexToBytes($record->getId())];
            static::assertEquals($expect['systemDefault'], $record->getSystemDefault());
            static::assertEquals($expect['mailType'], $record->getMailType());
            static::assertEquals($expect['description'], $record->getDescription());
            static::assertEquals($expect['senderName'], $record->getSenderName());
            static::assertEquals($expect['senderMail'], $record->getSenderMail());
            static::assertEquals($expect['subject'], $record->getSubject());
            static::assertEquals($expect['contentHtml'], $record->getContentHtml());
            static::assertEquals($expect['contentPlain'], $record->getContentPlain());
            unset($data[Uuid::fromHexToBytes($record->getId())]);
        }
    }

    /**
     * Test DELETE
     */
    public function testMailTemplateDelete(): void
    {
        $num = 10;
        $data = $this->prepareTemplateTestData($num);

        $this->repository->create(array_values($data), $this->context);

        $ids = [];
        foreach (array_column($data, 'id') as $id) {
            $ids[] = ['id' => $id];
        }

        $this->repository->delete($ids, $this->context);

        $records = $this->connection->fetchAll(
            'SELECT * 
                        FROM mail_header_footer mhf
                        JOIN mail_header_footer_translation mhft ON mhf.id=mhft.mail_header_footer_id'
        );

        static::assertEquals(0, count($records));
    }

    /**
     * Prepare a defined number of test data.
     *
     * @param int    $num
     * @param string $add
     */
    protected function prepareTemplateTestData($num = 1, $add = ''): array
    {
        $data = [];
        for ($i = 1; $i <= $num; ++$i) {
            $uuid = Uuid::randomHex();

            $data[Uuid::fromHexToBytes($uuid)] = [
                'id' => $uuid,
                'systemDefault' => (($i % 2 === 0) ? false : true),
                'mailType' => sprintf('Type %d %s', $i, $add),
                'description' => sprintf('A small description text %d %s', $i, $add),
                'senderName' => sprintf('John Doe %d %s', $i, $add),
                'senderMail' => sprintf('joe.doe%d%s@shopware.com', $i, $add),
                'subject' => sprintf('Test Betreff %d %s', $i, $add),
                'contentPlain' => sprintf('Test 123 %d %s', $i, $add),
                'contentHtml' => sprintf('<h1>Test %d %s <h1>', $i, $add),
            ];
        }

        return $data;
    }
}
