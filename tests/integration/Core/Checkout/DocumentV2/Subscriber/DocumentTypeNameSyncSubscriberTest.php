<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\DocumentV2\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('after-sales')]
class DocumentTypeNameSyncSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testTypeNameIsWrittenFromDocumentTypeIdOnBaseConfigWrite(): void
    {
        $context = Context::createDefaultContext();
        $documentTypeId = $this->getDocumentTypeId('invoice', $context);

        $id = Uuid::randomHex();
        static::getContainer()->get('document_base_config.repository')->create([[
            'id' => $id,
            'name' => 'test config',
            'documentTypeId' => $documentTypeId,
            'global' => false,
        ]], $context);

        $config = static::getContainer()->get('document_base_config.repository')
            ->search(new Criteria([$id]), $context)->getEntities()->first();

        static::assertInstanceOf(DocumentBaseConfigEntity::class, $config);
        static::assertSame('invoice', $config->getTypeName());
    }

    public function testTypeNameIsWrittenForSalesChannelConfigWrite(): void
    {
        $context = Context::createDefaultContext();
        $documentTypeId = $this->getDocumentTypeId('invoice', $context);

        $salesChannelConfigId = Uuid::randomHex();
        static::getContainer()->get('document_base_config.repository')->create([[
            'id' => Uuid::randomHex(),
            'name' => 'test config',
            'documentTypeId' => $documentTypeId,
            'global' => false,
            'salesChannels' => [[
                'id' => $salesChannelConfigId,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'documentTypeId' => $documentTypeId,
            ]],
        ]], $context);

        $salesChannelConfig = static::getContainer()->get('document_base_config_sales_channel.repository')
            ->search(new Criteria([$salesChannelConfigId]), $context)->getEntities()->first();

        static::assertInstanceOf(DocumentBaseConfigSalesChannelEntity::class, $salesChannelConfig);
        static::assertSame('invoice', $salesChannelConfig->getTypeName());
    }

    private function getDocumentTypeId(string $technicalName, Context $context): string
    {
        $id = static::getContainer()->get('document_type.repository')
            ->searchIds((new Criteria())->addFilter(new EqualsFilter('technicalName', $technicalName)), $context)
            ->firstId();

        static::assertIsString($id);

        return $id;
    }
}
