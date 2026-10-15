<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\CustomField\ContentSystem\Mapping;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Core\System\CustomField\ContentSystem\Mapping\CustomFieldMappingCandidateProvider;

/**
 * @internal
 */
#[Package('framework')]
class CustomFieldMappingCandidateProviderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testOffersOnlyFieldsAssignedToTheRequestedRootSource(): void
    {
        $name = 'content_system_material_' . Uuid::randomHex();
        $privateName = 'content_system_private_' . Uuid::randomHex();

        $this->customFieldSetRepository()->create([[
            'id' => Uuid::randomHex(),
            'name' => 'content_system_mapping_' . Uuid::randomHex(),
            'active' => true,
            'global' => false,
            'relations' => [[
                'id' => Uuid::randomHex(),
                'entityName' => 'product',
            ]],
            'customFields' => [[
                'id' => Uuid::randomHex(),
                'name' => $name,
                'type' => 'text',
                'active' => true,
                'storeApiAware' => true,
                'config' => [
                    'label' => ['en-GB' => 'Material'],
                    'helpText' => ['en-GB' => 'The product material'],
                ],
            ], [
                'id' => Uuid::randomHex(),
                'name' => $privateName,
                'type' => 'text',
                'active' => true,
                'storeApiAware' => false,
            ]],
        ]], Context::createDefaultContext());

        $provider = static::getContainer()->get(CustomFieldMappingCandidateProvider::class);
        static::assertInstanceOf(CustomFieldMappingCandidateProvider::class, $provider);

        $productCandidates = $provider->provide('product');
        $categoryCandidates = $provider->provide('category');

        static::assertContains('product.customFields.' . $name, array_column($productCandidates, 'path'));
        static::assertNotContains('product.customFields.' . $privateName, array_column($productCandidates, 'path'));
        static::assertNotContains('category.customFields.' . $name, array_column($categoryCandidates, 'path'));
    }

    /**
     * @return EntityRepository<CustomFieldSetCollection>
     */
    private function customFieldSetRepository(): EntityRepository
    {
        return static::getContainer()->get('custom_field_set.repository');
    }
}
