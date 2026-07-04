<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AbstractContentDataLoader::class)]
class AbstractContentDataLoaderTest extends TestCase
{
    #[TestDox('configSpecification defaults to an empty specification when a loader does not override it')]
    public function testConfigSpecificationDefaultsToEmptySpecification(): void
    {
        $loader = new ConfigLessStubLoader();

        $specification = $loader->configSpecification();

        static::assertSame([], $specification->keys);
        static::assertSame([], $specification->requiredKeys());
        static::assertNull($specification->get('anything'));
    }
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<ArrayStruct>
 */
class ConfigLessStubLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'fixture_config_less';
    }

    public function load(ContentElement $element, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}
