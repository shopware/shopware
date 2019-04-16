<?php declare(strict_types=1);

namespace src\Core\Content\Test\MailTemplate\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;

class MailTemplateApiTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

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
     * Test route api.mail_template.create
     */
    public function testMailTemplatesCreate(): void
    {
        // prepare test data
        $num = 5;
        $data = $this->prepareTemplateTestData($num);
        $ids = [];

        // do API calls
        foreach ($data as $entry) {
            $ids[] = $entry['id'];
            $this->getClient()->request('POST', $this->prepareRoute(), $entry);
            $response = $this->getClient()->getResponse();
            static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());
        }

        // read created data from db
        $records = $this->connection->fetchAll(
            'SELECT * FROM mail_template mt
                      JOIN mail_template_translation mtt ON mt.id=mtt.mail_template_id
                      WHERE mt.id IN (?)',
            [implode(',', $ids)]
        );

        // compare expected and resulting data
        foreach ($records as $record) {
            $expect = $data[$record['id']];
            static::assertEquals($expect['systemDefault'], (bool) $record['system_default']);
            static::assertEquals($expect['senderName'], $record['sender_name']);
            static::assertEquals($expect['mailType'], $record['mail_type']);
            static::assertEquals($expect['description'], $record['description']);
            static::assertEquals($expect['senderMail'], $record['sender_mail']);
            static::assertEquals($expect['subject'], $record['subject']);
            static::assertEquals($expect['contentHtml'], $record['content_html']);
            static::assertEquals($expect['contentPlain'], $record['content_plain']);
            unset($data[$record['id']]);
        }
    }

    /**
     * Test route api.mail_template.list
     */
    public function testMailTemplatesList(): void
    {
        // Create test data.
        $num = 10;
        $data = $this->prepareTemplateTestData($num);
        $this->repository->create(array_values($data), $this->context);

        $this->getClient()->request('GET', $this->prepareRoute(), [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response = $this->getClient()->getResponse();
        $content = json_decode($response->getContent());

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertCount($num, $content->data);
    }

    /**
     * api.mail_template.update
     */
    public function testMailTemplateUpdate(): void
    {
        $data = [
            'id' => Uuid::randomHex(),
            'systemDefault' => true,
            'mailType' => 'type unit test',
            'description' => 'A small description text to change',
            'senderName' => 'John Doe',
            'senderMail' => 'joe.doe@shopware.com',
            'subject' => 'Test Betreff',
            'contentPlain' => 'Unit test',
            'contentHtml' => '<h1>Unit test<h1>',
        ];

        $this->repository->create([$data], $this->context);

        $data['description'] = 'unit test';

        $this->getClient()->request('PATCH', $this->prepareRoute() . $data['id'], $data, [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $response = $this->getClient()->getResponse();
        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $this->getClient()->request('GET', $this->prepareRoute() . $data['id'], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $response = $this->getClient()->getResponse();
        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode($response->getContent());
        static::assertSame('unit test', $content->data->description);
    }

    /**
     * api.mail_template.detail
     */
    public function testMailTemplateDetail(): void
    {
        // create test data
        $num = 2;
        $data = $this->prepareTemplateTestData($num);
        $this->repository->create(array_values($data), $this->context);

        foreach (array_values($data) as $expect) {
            // Request details
            $this->getClient()->request('GET', $this->prepareRoute() . $expect['id'], [], [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $response = $this->getClient()->getResponse();
            static::assertEquals(Response::HTTP_OK, $response->getStatusCode());

            // compare deatils with expected
            $content = json_decode($response->getContent());
            static::assertEquals($expect['systemDefault'], $content->data->systemDefault);
            static::assertEquals($expect['mailType'], $content->data->mailType);
            static::assertEquals($expect['description'], $content->data->description);
            static::assertEquals($expect['senderName'], $content->data->senderName);
            static::assertEquals($expect['senderMail'], $content->data->senderMail);
            static::assertEquals($expect['subject'], $content->data->subject);
            static::assertEquals($expect['contentHtml'], $content->data->contentHtml);
            static::assertEquals($expect['contentPlain'], $content->data->contentPlain);
        }
    }

    /**
     * api.mail_template.search
     */
    public function testMailTemplateSearch(): void
    {
        // create test data
        $data = $this->prepareTemplateTestData(10);
        $this->repository->create(array_values($data), $this->context);

        // Use last entry for search filters.
        $searchData = array_pop($data);
        $filter = [];
        foreach ($searchData as $key => $value) {
            // Search call
            $filter['filter'][$key] = $value;
            $this->getClient()->request('POST', $this->prepareRoute(true), $filter, [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $response = $this->getClient()->getResponse();
            static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
            $content = json_decode($response->getContent());
            static ::assertEquals(1, $content->total);
        }
    }

    /**
     * api.mail_template.delete
     */
    public function testMailTemplateDelete(): void
    {
        // create test data
        $data = $this->prepareTemplateTestData();
        $this->repository->create(array_values($data), $this->context);
        $deleteId = array_column($data, 'id')[0];

        // Test request
        $this->getClient()->request('GET', $this->prepareRoute() . $deleteId, [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $response = $this->getClient()->getResponse();
        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        // Delete call
        $this->getClient()->request('DELETE', $this->prepareRoute() . $deleteId, [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $response = $this->getClient()->getResponse();
        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testCreatingTemplateWithInvalidSenderMailThrowsException(): void
    {
        // prepare test data
        $data = $this->prepareTemplateTestData(1);
        $template = array_shift($data);
        $template['senderMail'] = 'not-a-valid-email';

        // do API call
        $this->getClient()->request('POST', $this->prepareRoute(), $template);
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    /**
     * @param bool $search
     */
    protected function prepareRoute($search = false): string
    {
        $addPath = '';
        if ($search) {
            $addPath = '/search';
        }

        return '/api/v' . PlatformRequest::API_VERSION . $addPath . '/mail-template/';
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
