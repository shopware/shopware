<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Document\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\Document\DocumentTrait;

/**
 * @internal
 */
#[Package('after-sales')]
class DocumentConfigLoaderTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use DocumentTrait;

    private SalesChannelContext $salesChannelContext;

    private Context $context;

    private DocumentConfigLoader $documentConfigLoader;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Context::createDefaultContext();

        $customerId = $this->createCustomer();

        $this->salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $customerId,
            ]
        );

        $this->documentConfigLoader = static::getContainer()->get(DocumentConfigLoader::class);

        $this->connection = static::getContainer()->get(Connection::class);
    }

    protected function tearDown(): void
    {
        $this->documentConfigLoader->reset();
    }

    public function testLoadGlobalConfig(): void
    {
        $this->addLogoToDocument('invoice');

        $base = $this->getBaseConfig('invoice');
        $globalConfig = $base === null ? [] : $base->getConfig();
        $globalConfig['companyName'] = 'Test corp.';
        $globalConfig['displayCompanyAddress'] = true;
        $this->upsertBaseConfig($globalConfig, 'invoice');

        $salesChannelId = $this->salesChannelContext->getSalesChannelId();
        $config = $this->documentConfigLoader->load('invoice', $salesChannelId, $this->context);

        $config = $config->jsonSerialize();

        static::assertEquals('Test corp.', $config['companyName']);
        static::assertTrue($config['displayCompanyAddress']);
        static::assertNotNull($config['logo']);
        static::assertInstanceOf(MediaEntity::class, $config['logo']);
    }

    public function testLoadSalesChannelConfig(): void
    {
        $base = $this->getBaseConfig('invoice');

        $globalConfig = DocumentConfigurationFactory::createConfiguration([
            'companyName' => 'Test corp.',
            'displayCompanyAddress' => true,
        ], $base);

        $this->upsertBaseConfig($globalConfig->jsonSerialize(), InvoiceRenderer::TYPE);

        $salesChannelConfig = DocumentConfigurationFactory::mergeConfiguration($globalConfig, [
            'companyName' => 'Custom corp.',
            'displayCompanyAddress' => false,
            'pageSize' => 'a5',
        ]);

        $salesChannelId = $this->salesChannelContext->getSalesChannelId();
        $this->upsertBaseConfig($salesChannelConfig->jsonSerialize(), InvoiceRenderer::TYPE, $salesChannelId);

        $config = $this->documentConfigLoader->load(InvoiceRenderer::TYPE, $salesChannelId, $this->context);

        $config = $config->jsonSerialize();

        static::assertEquals('Custom corp.', $config['companyName']);
        static::assertFalse($config['displayCompanyAddress']);
        static::assertEquals('a5', $config['pageSize']);
    }

    private function addLogoToDocument(string $configName): void
    {
        $qb = $this->connection->createQueryBuilder();

        $mediaId = $qb->select('media.id AS ID')
            ->from('media')
            ->fetchOne();

        $qb->update('document_base_config')
            ->set('document_base_config.logo_id', ':id')
            ->where('document_base_config.name = :name')
            ->setParameter('id', $mediaId)
            ->setParameter('name', $configName)
            ->executeStatement();
    }
}
