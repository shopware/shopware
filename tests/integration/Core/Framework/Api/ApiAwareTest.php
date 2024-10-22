<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Notification\NotificationDefinition;
use Shopware\Administration\Snippet\AppAdministrationSnippetDefinition;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Storefront\Theme\ThemeDefinition;

/**
 * @internal
 */
#[Group('skip-paratest')]
class ApiAwareTest extends TestCase
{
    use DataAbstractionLayerFieldTestBehaviour;
    use KernelTestBehaviour;

    public function testApiAware(): void
    {
        $cacheId = Hasher::hashFile(__DIR__ . '/fixtures/api-aware-fields.json');

        $kernel = KernelLifecycleManager::createKernel(
            null,
            true,
            $cacheId
        );
        $kernel->boot();
        $registry = $kernel->getContainer()->get(DefinitionInstanceRegistry::class);

        $mapping = [];

        foreach ($registry->getDefinitions() as $definition) {
            $entity = $definition->getEntityName();

            foreach ($definition->getFields() as $field) {
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
        if (!\is_string($expected)) {
            static::fail(__DIR__ . '/fixtures/api-aware-fields.json could not be read');
        }
        $expected = \json_decode($expected, true, \JSON_THROW_ON_ERROR, \JSON_THROW_ON_ERROR);

        if ($this->getContainer()->has(ThemeDefinition::class)) {
            $expected = array_merge(
                $expected,
                [
                    'theme.id',
                    'theme.technicalName',
                    'theme.name',
                    'theme.author',
                    'theme.description',
                    'theme.labels',
                    'theme.helpTexts',
                    'theme.customFields',
                    'theme.previewMediaId',
                    'theme.parentThemeId',
                    'theme.baseConfig',
                    'theme.configValues',
                    'theme.active',
                    'theme.media',
                    'theme.createdAt',
                    'theme.updatedAt',
                    'theme.translated',
                    'theme_translation.description',
                    'theme_translation.labels',
                    'theme_translation.helpTexts',
                    'theme_translation.customFields',
                    'theme_translation.createdAt',
                    'theme_translation.updatedAt',
                    'theme_translation.themeId',
                    'theme_translation.languageId',
                ]
            );
        }

        if ($this->getContainer()->has(NotificationDefinition::class)) {
            $expected = array_merge(
                $expected,
                [
                    'notification.createdAt',
                    'notification.updatedAt',
                ]
            );
        }

        if ($this->getContainer()->has(AppAdministrationSnippetDefinition::class)) {
            $expected = array_merge(
                $expected,
                [
                    'app_administration_snippet.value',
                    'app_administration_snippet.appId',
                    'app_administration_snippet.localeId',
                    'app_administration_snippet.createdAt',
                    'app_administration_snippet.updatedAt',
                ]
            );
        }

        if (!Feature::isActive('v6.7.0.0')) {
            $expected = array_merge(
                $expected,
                [
                    'customer.defaultPaymentMethodId',
                    'customer.defaultPaymentMethod',
                ]
            );
        }

        $message = 'One or more fields have been changed in their visibility for the Store Api.
        This change must be carefully controlled to ensure that no sensitive data is given out via the Store API.';

        $diff = array_diff($mapping, $expected);
        static::assertEquals([], $diff, $message);

        $diff = array_diff($expected, $mapping);
        static::assertEquals([], $diff, $message);
    }
}
