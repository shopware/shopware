<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Loader\AbstractContentSystemStyleOptionLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Shopware\Core\Framework\Log\Package;

/**
 * Registers a single flat (breakpointAware=false) style option so the persistence tests can exercise the
 * flat write→DB→decode round-trip end-to-end — no shipped core option is flat. Wired only in the test
 * environment via the content_system.style_option_loader tag in services_test.php.
 *
 * @final
 */
#[Package('framework')]
class TestStyleOptionLoader extends AbstractContentSystemStyleOptionLoader
{
    public const FLAT_INTEGER = 'test-flat-span';

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
        ];
    }
}
