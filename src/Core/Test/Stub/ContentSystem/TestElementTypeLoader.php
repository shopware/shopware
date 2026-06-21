<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\AbstractContentSystemElementTypeLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\Log\Package;

/**
 * Registers two deterministic element types for the resolvability-gate tests, independent of the shipped
 * type definitions: a property-free component that is resolvable against every binding, and a component
 * with a required reference to {@see UnresolvableContextTarget} that is resolvable against none. Wired only
 * in the test environment via the content_system.type_loader tag in services_test.xml.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class TestElementTypeLoader extends AbstractContentSystemElementTypeLoader
{
    public const RESOLVABLE = 'Sw:Test:Resolvable';

    public const UNRESOLVABLE = 'Sw:Test:RequiresEntity';

    public const SOURCE = 'test';

    public function load(): array
    {
        return [
            new ContentSystemElementTypeSpecification(
                self::RESOLVABLE,
                'Resolvable test element',
                '',
                null,
                null,
                new CopilotSpecification('', []),
                [],
                [],
                self::SOURCE,
            ),
            new ContentSystemElementTypeSpecification(
                self::UNRESOLVABLE,
                'Unresolvable test element',
                '',
                null,
                null,
                new CopilotSpecification('', []),
                [
                    'target' => new PropertySpecification(
                        'target',
                        new PropertyType(UnresolvableContextTarget::class, false, null, null),
                        true,
                        '',
                        '',
                        null,
                    ),
                ],
                [],
                self::SOURCE,
            ),
        ];
    }
}
