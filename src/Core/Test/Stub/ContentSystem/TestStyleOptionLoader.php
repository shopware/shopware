<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Loader\AbstractContentSystemStyleOptionLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Shopware\Core\Framework\Log\Package;

/**
 * Registers flat and breakpoint-aware numeric options for style persistence tests. Wired only in the test
 * environment via the content_system.style_option_loader tag in services_test.php.
 *
 * @final
 */
#[Package('framework')]
class TestStyleOptionLoader extends AbstractContentSystemStyleOptionLoader
{
    public const FLAT_INTEGER = 'test-flat-span';

    public const RESPONSIVE_INTEGER = 'test-responsive-integer';

    public const SOURCE = 'test';

    public function load(): array
    {
        return [
            new StyleOptionSpecification(
                self::FLAT_INTEGER,
                new StyleOptionValueType('integer', null, null, null, null),
                false,
                null,
                self::SOURCE,
            ),
            new StyleOptionSpecification(
                self::RESPONSIVE_INTEGER,
                new StyleOptionValueType('integer', null, ['min' => 1, 'max' => 12], null, null),
                true,
                null,
                self::SOURCE,
            ),
        ];
    }
}
