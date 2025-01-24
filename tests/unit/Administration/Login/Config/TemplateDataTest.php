<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Login\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Login\Config\TemplateData;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(TemplateData::class)]
class TemplateDataTest extends TestCase
{
    public function testTemplateData(): void
    {
        $templateData = new TemplateData(
            'newRandomValue',
            true,
            true,
            'https://shopware.com/',
        );

        $expected = [
            'show' => true,
            'useDefault' => true,
            'url' => 'https://shopware.com/',
        ];

        static::assertSame($expected, $templateData->jsonSerialize());
        static::assertSame(\json_encode($expected), \json_encode($templateData));
    }
}
