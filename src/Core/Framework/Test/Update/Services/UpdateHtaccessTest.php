<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Update\Services;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Update\Services\UpdateHtaccess;

class UpdateHtaccessTest extends TestCase
{
    /**
     * @dataProvider getCombinations
     */
    public function testCombination(string $currentEnv, ?string $newEnv, string $expected): void
    {
        $fs = sys_get_temp_dir() . '/' . uniqid(__METHOD__, true) . '/';
        mkdir($fs);

        file_put_contents($fs . '.env', $currentEnv);

        if ($newEnv) {
            file_put_contents($fs . '.env.dist', $newEnv);
        }

        $updater = new UpdateHtaccess($fs . '.env');
        $updater->update();

        static::assertSame($expected, file_get_contents($fs . '.env'));
    }

    public function getCombinations(): iterable
    {
        // Dist file missing
        yield [
            'Test',
            null,
            'Test',
        ];

        // User has removed marker
        yield [
            'Test',
            '# BEGIN Shopware
Test
# END Shopware',
            'Test',
        ];

        // Update marker
        yield [
            '# BEGIN Shopware
OLD
# END Shopware',
            '# BEGIN Shopware
NEW
# END Shopware',
            '# BEGIN Shopware
# The directives (lines) between "# BEGIN Shopware" and "# END Shopware" are dynamically generated. Any changes to the directives between these markers will be overwritten.
NEW
# END Shopware',
        ];

        // Update marker with pre and after lines
        yield [
            'BEFORE
# BEGIN Shopware
OLD
# END Shopware
AFTER',
            '# BEGIN Shopware
NEW
# END Shopware',
            'BEFORE
# BEGIN Shopware
# The directives (lines) between "# BEGIN Shopware" and "# END Shopware" are dynamically generated. Any changes to the directives between these markers will be overwritten.
NEW
# END Shopware
AFTER',
        ];

        // Update containg help text
        yield [
            'BEFORE
# BEGIN Shopware
# The directives (lines) between "# BEGIN Shopware" and "# END Shopware" are dynamically generated. Any changes to the directives between these markers will be overwritten.
OLD
# END Shopware
AFTER',
            '# BEGIN Shopware
# The directives (lines) between "# BEGIN Shopware" and "# END Shopware" are dynamically generated. Any changes to the directives between these markers will be overwritten.
NEW
# END Shopware',
            'BEFORE
# BEGIN Shopware
# The directives (lines) between "# BEGIN Shopware" and "# END Shopware" are dynamically generated. Any changes to the directives between these markers will be overwritten.
NEW
# END Shopware
AFTER',
        ];
    }
}
