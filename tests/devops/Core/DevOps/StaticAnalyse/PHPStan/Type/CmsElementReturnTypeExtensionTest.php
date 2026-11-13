<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Type;

use PHPStan\Testing\TypeInferenceTestCase;
use Shopware\Core\Framework\Log\Package;

require_once __DIR__ . '/data/CmsElements/CustomPage.php';

/**
 * @internal
 */
#[Package('framework')]
class CmsElementReturnTypeExtensionTest extends TypeInferenceTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/data/CmsElements/extension.php'];
    }

    public function testLookupTypesAndNullabilityFlowIntoDataGetters(): void
    {
        $assertions = self::gatherAssertTypes(__DIR__ . '/data/CmsElements/types.php');
        static::assertCount(10, $assertions);
        foreach ($assertions as $assertion) {
            $this->assertFileAsserts(...$assertion);
        }
    }
}
