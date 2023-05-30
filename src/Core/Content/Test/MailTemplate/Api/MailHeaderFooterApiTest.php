<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\MailTemplate\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class MailHeaderFooterApiTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private EntityRepository $repository;

    private Connection $connection;

    private Context $context;

    protected function setUp(): void
    {
        $this->repository = $this->getContainer()->get('mail_header_footer.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();

        try {
            $this->connection->executeStatement('DELETE FROM mail_header_footer');
        } catch (\Exception $e) {
            static::fail('Failed to remove testdata: ' . $e->getMessage());
        }
    }

    /**
     * api.mail_header_footer.create
     *
     * @group slow
     */
    public function testHeaderFooterCreate(): void
    {
        // prepare test data
        $num = 5;
        $data = $this->prepareHeaderFooterTestData($num);

        // do API calls
        foreach ($data as $entry) {
            $this->getBrowser()->request('POST', $this->prepareRoute(), [], [], [], json_encode($entry, \JSON_THROW_ON_ERROR));
            $response = $this->getBrowser()->getResponse();
            static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());
        }

        // read created data from db
        $records = $this->connection->fetchAllAssociative(
            'SELECT *
             FROM mail_header_footer mhf
             JOIN mail_header_footer_translation mhft
                 ON mhf.id=mhft.mail_header_footer_id'
        );

        // compare expected and resulting data
        static::assertCount($num, $records);
        foreach ($records as $record) {
            $expect = $data[$record['id']];
            static::assertEquals($expect['systemDefault'], (bool) $record['system_default']);
            static::assertEquals($expect['name'], $record['name']);
            static::assertEquals($expect['description'], $record['description']);
            static::assertEquals($expect['headerHtml'], $record['header_html']);
            static::assertEquals($expect['headerPlain'], $record['header_plain']);
            static::assertEquals($expect['footerHtml'], $record['footer_html']);
            static::assertEquals($expect['footerPlain'], $record['footer_plain']);
            unset($data[$record['id']]);
        }
    }

    /**
     * api.mail_header_footer.list
     *
     * @group slow
     */
    public function testHeaderFooterList(): void
    {
        // Create test data.
        $num = 10;
        $data = $this->prepareHeaderFooterTestData($num);
        $this->repository->create(array_values($data), $this->context);

        $this->getBrowser()->request('GET', $this->prepareRoute(), [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response = $this->getBrowser()->getResponse();
        static::assertEquals(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $content = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        // Prepare expected data.
        $expectData = [];
        foreach ($data as $entry) {
            $expectData[$entry['id']] = $entry;
        }

        // compare expected and resulting data
        static::assertEquals($num, $content['total']);
        for ($i = 0; $i < $num; ++$i) {
            $mailHeaderFooter = $content['data'][$i];
            $expect = $expectData[$mailHeaderFooter['_uniqueIdentifier']];
            static::assertEquals($expect['systemDefault'], $mailHeaderFooter['systemDefault']);
            static::assertEquals($expect['name'], $mailHeaderFooter['name']);
            static::assertEquals($expect['description'], $mailHeaderFooter['description']);
            static::assertEquals($expect['headerHtml'], $mailHeaderFooter['headerHtml']);
            static::assertEquals($expect['headerPlain'], $mailHeaderFooter['headerPlain']);
            static::assertEquals($expect['footerHtml'], $mailHeaderFooter['footerHtml']);
            static::assertEquals($expect['footerPlain'], $mailHeaderFooter['footerPlain']);
        }
    }

    /**
     * api.mail_header_footer.update
     */
    public function testHeaderFooterUpdate(): void
    {
        // create test data
        $num = 10;
        $data = $this->prepareHeaderFooterTestData($num);
        $this->repository->create(array_values($data), $this->context);

        $ids = array_column($data, 'id');
        shuffle($data);

        $expectData = [];
        foreach ($ids as $idx => $id) {
            $expectData[$id] = $data[$idx];
            unset($data[$idx]['id']);

            $this->getBrowser()->request('PATCH', $this->prepareRoute() . $id, [], [], [
                'HTTP_ACCEPT' => 'application/json',
            ], json_encode($data[$idx], \JSON_THROW_ON_ERROR));
            $response = $this->getBrowser()->getResponse();
            static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        }

        $this->getBrowser()->request('GET', $this->prepareRoute(), [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $response = $this->getBrowser()->getResponse();
        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        // Compare expected and received data.
        static::assertEquals($num, $content['total']);
        for ($i = 0; $i < $num; ++$i) {
            $mailHeaderFooter = $content['data'][$i];
            $expect = $expectData[$mailHeaderFooter['_uniqueIdentifier']];
            static::assertEquals($expect['systemDefault'], $mailHeaderFooter['systemDefault']);
            static::assertEquals($expect['name'], $mailHeaderFooter['name']);
            static::assertEquals($expect['description'], $mailHeaderFooter['description']);
            static::assertEquals($expect['headerHtml'], $mailHeaderFooter['headerHtml']);
            static::assertEquals($expect['headerPlain'], $mailHeaderFooter['headerPlain']);
            static::assertEquals($expect['footerHtml'], $mailHeaderFooter['footerHtml']);
            static::assertEquals($expect['footerPlain'], $mailHeaderFooter['footerPlain']);
        }
    }

    /**
     * api.mail_header_footer.detail
     */
    public function testHeaderFooterDetail(): void
    {
        // create test data
        $num = 2;
        $data = $this->prepareHeaderFooterTestData($num);
        $this->repository->create(array_values($data), $this->context);

        foreach ($data as $expect) {
            // Request details
            $this->getBrowser()->request('GET', $this->prepareRoute() . $expect['id'], [], [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $response = $this->getBrowser()->getResponse();
            static::assertEquals(Response::HTTP_OK, $response->getStatusCode());

            // compare deatils with expected
            $content = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            static::assertEquals($expect['systemDefault'], $content['data']['systemDefault']);
            static::assertEquals($expect['name'], $content['data']['name']);
            static::assertEquals($expect['description'], $content['data']['description']);
            static::assertEquals($expect['headerHtml'], $content['data']['headerHtml']);
            static::assertEquals($expect['headerPlain'], $content['data']['headerPlain']);
            static::assertEquals($expect['footerHtml'], $content['data']['footerHtml']);
            static::assertEquals($expect['footerPlain'], $content['data']['footerPlain']);
        }
    }

    /**
     * api.mail_header_footer.search
     */
    public function testHeaderFooterSearch(): void
    {
        // create test data
        $data = $this->prepareHeaderFooterTestData();
        $this->repository->create(array_values($data), $this->context);

        // Use last entry for search filters.
        $searchData = array_pop($data);
        static::assertIsArray($searchData);
        $filter = [];
        foreach ($searchData as $key => $value) {
            // Search call
            $filter['filter'][$key] = $value;
            $this->getBrowser()->request('POST', $this->prepareRoute(true), $filter, [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $response = $this->getBrowser()->getResponse();
            static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
            $content = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            static ::assertEquals(1, $content['total']);
        }
    }

    /**
     * api.mail_header_footer.delete
     */
    public function testHeaderFooterDelete(): void
    {
        // create test data
        $data = $this->prepareHeaderFooterTestData();
        $this->repository->create(array_values($data), $this->context);
        $deleteId = array_column($data, 'id')[0];

        // Test request
        $this->getBrowser()->request('GET', $this->prepareRoute() . $deleteId, [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $response = $this->getBrowser()->getResponse();
        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        // Delete call
        $this->getBrowser()->request('DELETE', $this->prepareRoute() . $deleteId, [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $response = $this->getBrowser()->getResponse();
        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    protected function prepareRoute(bool $search = false): string
    {
        $addPath = '';
        if ($search) {
            $addPath = '/search';
        }

        return '/api' . $addPath . '/mail-header-footer/';
    }

    /**
     * Prepare a defined number of test data.
     *
     * @return array<string, array<string, mixed>>
     */
    private function prepareHeaderFooterTestData(int $num = 1, string $add = ''): array
    {
        $data = [];
        for ($i = 1; $i <= $num; ++$i) {
            $uuid = Uuid::randomHex();

            $data[Uuid::fromHexToBytes($uuid)] = [
                'id' => $uuid,
                'systemDefault' => $i % 2 !== 0,
                'name' => sprintf('Test-Template %d %s', $i, $add),
                'description' => sprintf('John Doe %d %s', $i, $add),
                'headerPlain' => sprintf('Test header 123 %d %s', $i, $add),
                'headerHtml' => sprintf('<h1>Test header %d %s </h1>', $i, $add),
                'footerPlain' => sprintf('Test footer 123 %d %s', $i, $add),
                'footerHtml' => sprintf('<h1>Test footer %d %s </h1>', $i, $add),
            ];
        }

        return $data;
    }
}
