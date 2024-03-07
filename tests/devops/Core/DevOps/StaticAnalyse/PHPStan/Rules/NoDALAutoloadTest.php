<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\NoDALAutoload;

/**
 * @internal
 *
 * @extends  RuleTestCase<NoDALAutoload>
 */
#[CoversClass(NoDALAutoload::class)]
class NoDALAutoloadTest extends RuleTestCase
{
    #[RunInSeparateProcess]
    public function testRule(): void
    {
        // not in a class, ignore
        $this->analyse([__DIR__ . '/data/NoDalAutoload/not-in-class.php'], []);

        // not in namespace, autoload is passed as true, error
        $this->analyse([__DIR__ . '/data/NoDalAutoload/not-in-namespace.php'], [
            [
                'my-entity.prop association has a configured autoload===true, this is forbidden for platform integrations',
                15,
            ],
            [
                'my-entity.prop2 association has a configured autoload===true, this is forbidden for platform integrations',
                16,
            ],
        ]);

        // in namespace, autoload is passed as true, error
        $this->analyse([__DIR__ . '/data/NoDalAutoload/in-core-namespace.php'], [
            [
                'my-entity.prop association has a configured autoload===true, this is forbidden for platform integrations',
                17,
            ],
            [
                'my-entity.prop2 association has a configured autoload===true, this is forbidden for platform integrations',
                18,
            ],
        ]);

        // if no autoload is passed, default value is false, all good
        $this->analyse([__DIR__ . '/data/NoDalAutoload/no-autoload-param.php'], []);

        // if autoload is specified as false, all good
        $this->analyse([__DIR__ . '/data/NoDalAutoload/autoload-false.php'], []);

        // if we are in a Test namespace, we ignore
        $this->analyse([__DIR__ . '/data/NoDalAutoload/in-test-namespace.php'], []);

        // if we can't find an ENTITY_NAME const we ignore
        $this->analyse([__DIR__ . '/data/NoDalAutoload/no-entity-name.php'], []);
    }

    /**
     * @return NoDALAutoload
     */
    protected function getRule(): Rule
    {
        return new NoDALAutoload($this->createReflectionProvider());
    }
}
