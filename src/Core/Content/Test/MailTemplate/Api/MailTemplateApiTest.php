<?php declare(strict_types=1);

namespace src\Core\Content\Test\MailTemplate\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
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

    protected function tearDown(): void
    {
        try {
            $this->connection->executeUpdate('DELETE FROM mail_template');
        } catch (\Exception $e) {
            static::assertTrue(false . 'Failed to remove testdata: ' . $e->getMessage());
        }
    }

    /**
     * Test route api.mail_template.create
     */
    public function testMailTemplatesCreate(): void
    {
        // prepare test data
        $num = 5;
        $data = $this->prepareTemplateTestData($num);

        // do API calls
        foreach ($data as $entry) {
            $this->getClient()->request('POST', $this->prepareRoute(), $entry);
            $response = $this->getClient()->getResponse();
            static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());
        }

        // read created data from db
        $records = $this->connection->fetchAll(
            'SELECT * 
                        FROM mail_template mt
                        JOIN mail_template_translation mtt ON mt.id=mtt.mail_template_id'
        );

        // compare expected and resulting data
        static::assertEquals($num, count($records));
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
        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode($response->getContent());

        // Prepare expected data.
        $expextData = [];
        foreach (array_values($data) as $entry) {
            $expextData[$entry['id']] = $entry;
        }

        // compare expected and resulting data
        static::assertEquals($num, $content->total);
        for ($i = 0; $i < $num; ++$i) {
            $mailTemplate = $content->data[$i];
            $expect = $expextData[$mailTemplate->_uniqueIdentifier];
            static::assertEquals($expect['systemDefault'], $mailTemplate->systemDefault);
            static::assertEquals($expect['mailType'], $mailTemplate->mailType);
            static::assertEquals($expect['description'], $mailTemplate->description);
            static::assertEquals($expect['senderName'], $mailTemplate->senderName);
            static::assertEquals($expect['senderMail'], $mailTemplate->senderMail);
            static::assertEquals($expect['subject'], $mailTemplate->subject);
            static::assertEquals($expect['contentHtml'], $mailTemplate->contentHtml);
            static::assertEquals($expect['contentPlain'], $mailTemplate->contentPlain);
        }
    }

    /**
     * api.mail_template.update
     */
    public function testMailTemplateUpdate(): void
    {
        // create test data
        $num = 10;
        $data = $this->prepareTemplateTestData($num);
        $this->repository->create(array_values($data), $this->context);

        $ids = array_column($data, 'id');
        shuffle($data);

        $expextData = [];
        foreach ($ids as $idx => $id) {
            $expextData[$id] = $data[$idx];
            unset($data[$idx]['id']);

            $this->getClient()->request('PATCH', $this->prepareRoute() . $id, $data[$idx], [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $response = $this->getClient()->getResponse();
            static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        }

        $this->getClient()->request('GET', $this->prepareRoute(), [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $response = $this->getClient()->getResponse();
        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode($response->getContent());

        // Compare expected and received data.
        static::assertEquals($num, $content->total);
        for ($i = 0; $i < $num; ++$i) {
            $mailTemplate = $content->data[$i];
            $expect = $expextData[$mailTemplate->_uniqueIdentifier];
            static::assertEquals($expect['systemDefault'], $mailTemplate->systemDefault);
            static::assertEquals($expect['mailType'], $mailTemplate->mailType);
            static::assertEquals($expect['description'], $mailTemplate->description);
            static::assertEquals($expect['senderName'], $mailTemplate->senderName);
            static::assertEquals($expect['senderMail'], $mailTemplate->senderMail);
            static::assertEquals($expect['subject'], $mailTemplate->subject);
            static::assertEquals($expect['contentHtml'], $mailTemplate->contentHtml);
            static::assertEquals($expect['contentPlain'], $mailTemplate->contentPlain);
        }
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
            $uuid = Uuid::uuid4();

            $data[$uuid->getBytes()] = [
                'id' => $uuid->getHex(),
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
