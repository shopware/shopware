<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Theme\Extension;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('discovery')]
class MediaExtensionTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testMediaUsedByAThemeCannotBeDeleted(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();
        $logo = [
            'id' => $ids->create('logo'),
            'fileName' => 'theme-logo-' . $ids->get('logo'),
            'fileExtension' => 'png',
            'mimeType' => 'image/png',
        ];

        static::getContainer()->get('theme.repository')->create([[
            'id' => $ids->create('theme'),
            'name' => 'Theme with a logo',
            'author' => 'Shopware',
            'active' => false,
            'media' => [$logo],
        ]], $context);

        try {
            static::getContainer()->get('media.repository')->delete([['id' => $logo['id']]], $context);
            static::fail('A media that a theme uses must not be deleted');
        } catch (RestrictDeleteViolationException $exception) {
            static::assertSame(
                [
                    'entity' => 'media',
                    'usagesString' => 'theme_media (1)',
                    'usages' => [
                        [
                            'entityName' => 'theme_media',
                            'count' => 1,
                        ],
                    ],
                    'metaData' => [
                        'theme_media' => [
                            [
                                'themeId' => $ids->get('theme'),
                                'mediaId' => $logo['id'],
                                'media' => [
                                    'id' => $logo['id'],
                                    'fileExtension' => 'png',
                                    'fileName' => $logo['fileName'],
                                ],
                            ],
                        ],
                    ],
                ],
                $exception->getParameters()
            );
        }
    }
}
