<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Migration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;
use Shopware\Storefront\Migration\V6_3\Migration1595492054SeoUrlTemplateData;

/**
 * @internal
 *
 * @group skip-paratest
 */
class Migration1595492054SeoUrlTemplateDataTest extends TestCase
{
    use IntegrationTestBehaviour;

    public static function customTemplateProvider(): array
    {
        return [
            [
                [], // no custom templates
            ],
            [
                [[
                    'template' => '{{ product.productNumber }}',
                    'entity_name' => ProductDefinition::ENTITY_NAME,
                    'route_name' => ProductPageSeoUrlRoute::ROUTE_NAME,
                ]],
            ],
            [
                [[
                    'template' => '{{ category.translated.name }}',
                    'entity_name' => CategoryDefinition::ENTITY_NAME,
                    'route_name' => NavigationPageSeoUrlRoute::ROUTE_NAME,
                ]],
            ],
            [
                [
                    [
                        'template' => '{{ product.productNumber }}',
                        'entity_name' => ProductDefinition::ENTITY_NAME,
                        'route_name' => ProductPageSeoUrlRoute::ROUTE_NAME,
                    ],
                    [
                        'template' => '{{ category.translated.name }}',
                        'entity_name' => CategoryDefinition::ENTITY_NAME,
                        'route_name' => NavigationPageSeoUrlRoute::ROUTE_NAME,
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider customTemplateProvider
     */
    public function testMigrationHasNoEffectWithCustomProductTemplate(array $templates): void
    {
        $migration = new Migration1595492054SeoUrlTemplateData();

        $connection = $this->getContainer()->get(Connection::class);
        foreach ($templates as $templateData) {
            $affectedColumns = $connection->update(
                'seo_url_template',
                ['template' => $templateData['template']],
                [
                    'entity_name' => $templateData['entity_name'],
                    'route_name' => $templateData['route_name'],
                ]
            );
            static::assertSame(1, $affectedColumns);
        }

        $data = $connection->fetchAllAssociative('SELECT * FROM seo_url_template ORDER BY id');
        $expectedHash = md5(serialize($data));

        $migration->update($connection);

        $data = $connection->fetchAllAssociative('SELECT * FROM seo_url_template ORDER BY id');
        $actualHash = md5(serialize($data));

        static::assertSame($expectedHash, $actualHash, 'The data has changed');
    }

    public function testDefaultTemplates(): void
    {
        $stmt = $this->getContainer()->get(Connection::class)
            ->prepare('SELECT template FROM seo_url_template WHERE `entity_name` = ? AND `route_name` = ?');
        $res = $stmt->executeQuery([
            ProductDefinition::ENTITY_NAME,
            ProductPageSeoUrlRoute::ROUTE_NAME,
        ]);
        static::assertSame(ProductPageSeoUrlRoute::DEFAULT_TEMPLATE, $res->fetchOne());

        $res = $stmt->executeQuery([
            CategoryDefinition::ENTITY_NAME,
            NavigationPageSeoUrlRoute::ROUTE_NAME,
        ]);
        static::assertSame(NavigationPageSeoUrlRoute::DEFAULT_TEMPLATE, $res->fetchOne());
    }
}
