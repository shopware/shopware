<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @group skip-paratest
 */
class ApiAwareTest extends TestCase
{
    use KernelTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    public function testApiAware(): void
    {
        $kernel = KernelLifecycleManager::createKernel(null, true, hash_file('md5', __DIR__ . '/fixtures/api-aware-fields.json'));
        $kernel->boot();
        $registry = $kernel->getContainer()->get(DefinitionInstanceRegistry::class);

        $mapping = [];

        foreach ($registry->getDefinitions() as $definition) {
            $entity = $definition->getEntityName();

            foreach ($definition->getFields() as $field) {
                /** @var ApiAware|null $flag */
                $flag = $field->getFlag(ApiAware::class);
                if ($flag === null) {
                    continue;
                }

                if ($flag->isSourceAllowed(SalesChannelApiSource::class)) {
                    $mapping[] = $entity . '.' . $field->getPropertyName();
                }
            }
        }

//        file_put_contents(__DIR__ . '/fixtures/api-aware-fields.json', json_encode($mapping, JSON_PRETTY_PRINT));

        // To update the mapping you can simply comment the following line and run the test once. The mapping will then be updated.
        // The line to update the mapping must of course be commented out again afterwards.
        $expected = file_get_contents(__DIR__ . '/fixtures/api-aware-fields.json');

        $expected = json_decode($expected, true);

        if (Feature::isActive('FEATURE_NEXT_14114')) {
            $expected[] = 'country.vatIdRequired';
            $expected[] = 'country.customerTax';
            $expected[] = 'country.companyTax';
            $expected[] = 'currency.taxFreeFrom';
        }

        if (Feature::isActive('FEATURE_NEXT_14408')) {
            $expected[] = 'app_cms_block.createdAt';
            $expected[] = 'app_cms_block.updatedAt';
            $expected[] = 'app_cms_block.translated';
            $expected[] = 'app_cms_block_translation.createdAt';
            $expected[] = 'app_cms_block_translation.updatedAt';
            $expected[] = 'app_cms_block_translation.appCmsBlockId';
            $expected[] = 'app_cms_block_translation.languageId';
        }

        $message = 'One or more fields have been changed in their visibility for the Store Api.
        This change must be carefully controlled to ensure that no sensitive data is given out via the Store API.';

        $diff = array_diff($mapping, $expected);
        static::assertEquals([], $diff, $message);

        $diff = array_diff($expected, $mapping);
        static::assertEquals([], $diff, $message);
    }
}
