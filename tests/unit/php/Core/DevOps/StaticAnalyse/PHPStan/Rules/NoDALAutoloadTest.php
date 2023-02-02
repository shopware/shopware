<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\NoDALAutoload;

/**
 * @internal
 *
 * @extends  RuleTestCase<NoDALAutoload>
 *
 * @covers \Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\NoDALAutoload
 */
class NoDALAutoloadTest extends RuleTestCase
{
    public function testRule(): void
    {
        //not in a class, ignore
        $this->analyse([__DIR__ . '/data/no-dal-autoload/not-in-class.php'], []);

        //not in namespace, autoload is passed as true, error
        $this->analyse([__DIR__ . '/data/no-dal-autoload/not-in-namespace.php'], [
            [
                'my-entity.prop association has a configured autoload===true, this is forbidden for platform integrations',
                12,
            ],
            [
                'my-entity.prop2 association has a configured autoload===true, this is forbidden for platform integrations',
                13,
            ],
        ]);

        //in namespace, autoload is passed as true, error
        $this->analyse([__DIR__ . '/data/no-dal-autoload/in-core-namespace.php'], [
            [
                'my-entity.prop association has a configured autoload===true, this is forbidden for platform integrations',
                14,
            ],
            [
                'my-entity.prop2 association has a configured autoload===true, this is forbidden for platform integrations',
                15,
            ],
        ]);

        //if no autoload is passed, default value is false, all good
        $this->analyse([__DIR__ . '/data/no-dal-autoload/no-autoload-param.php'], []);

        //if autoload is specified as false, all good
        $this->analyse([__DIR__ . '/data/no-dal-autoload/autoload-false.php'], []);

        //if we are in a Test namespace, we ignore
        $this->analyse([__DIR__ . '/data/no-dal-autoload/in-test-namespace.php'], []);

        //if we can't find an ENTITY_NAME const we ignore
        $this->analyse([__DIR__ . '/data/no-dal-autoload/no-entity-name.php'], []);
    }

    /**
     * @return NoDALAutoload
     */
    protected function getRule(): Rule
    {
        return new NoDALAutoload($this->createReflectionProvider());
    }
}
