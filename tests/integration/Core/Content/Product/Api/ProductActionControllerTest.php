<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\MemorySizeCalculator;

/**
 * @internal
 */
#[Package('inventory')]
class ProductActionControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testDocumentUploadSettingsGiveTheShippedSettings(): void
    {
        $browser = $this->getBrowser();
        $browser->request('GET', '/api/_action/product/document-upload-settings');

        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $settings = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($settings);
        static::assertSame(['pdf', 'docx', 'jpg', 'jpeg', 'png'], $settings['allowedExtensions']);
        static::assertContains('application/pdf', $settings['allowedMimeTypesByExtension']['pdf']);
        static::assertSame(MemorySizeCalculator::getMaxUploadSize(20 * 1024 * 1024), $settings['maxFileSize']);
    }
}
