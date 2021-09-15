<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ProductExport\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductExport\Exception\DuplicateFileNameException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

class ProductExportExceptionHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testDuplicateInsert(): void
    {
        $ids = new IdsCollection();

        $this->getContainer()->get('product_stream.repository')
            ->create([['id' => $ids->get('stream'), 'name' => 'test']]);

        $domainId = $this->getContainer()->get('SELECT LOWER(HEX(id)) FROM sales_channel_domain LIMIT 1');

        $file = 'test-file_name.png';

        $config = [
            'productStreamId' => $ids->get('stream'),
            'storefrontSalesChannelId' => TestDefaults::SALES_CHANNEL,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'salesChannelDomainId' => $domainId,
            'currencyId' => Defaults::CURRENCY,
            'fileName' => $file,
            'accessKey' => Uuid::randomHex(),
            'encoding' => Uuid::randomHex(),
            'fileFormat' => 'test',
            'generateByCronjob' => false,
            'interval' => 1,
        ];

        static::expectException(DuplicateFileNameException::class);
        static::expectExceptionMessage('File name "' . $file . '" already exists.');

        $this->getContainer()->get('product_export.repository')
            ->create([$config, $config], Context::createDefaultContext());
    }
}
