<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\AbstractContentSystemElementTypeLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\Log\Package;

/**
 * Registers five deterministic element types for the resolvability-gate and default-materialization tests,
 * independent of the shipped type definitions: a property-free component that is resolvable against every binding,
 * a component with a required reference to {@see UnresolvableContextTarget} that is resolvable against none, a
 * component with a required primitive carrying a type default (used to prove the write-boundary default seeding),
 * and a component with a required translatable primitive carrying no default, which no shipped type declares —
 * every shipped translatable property is optional, so the anchor-entry satisfaction rule has no shipped subject.
 * The fifth is that same required translatable primitive with a type default, the corner where a reseeded default
 * is stored as the language map {@see PropertyType::storedDefault()} keys under the anchor language rather than
 * as a bare scalar.
 * Wired only in the test environment via the content_system.type_loader tag in services_test.php.
 *
 * @final
 */
#[Package('framework')]
class TestElementTypeLoader extends AbstractContentSystemElementTypeLoader
{
    public const RESOLVABLE = 'Sw:Test:Resolvable';

    public const UNRESOLVABLE = 'Sw:Test:RequiresEntity';

    public const DEFAULTED_PRIMITIVE = 'Sw:Test:DefaultedPrimitive';

    public const TRANSLATABLE_REQUIRED = 'Sw:Test:TranslatableRequired';

    public const DEFAULTED_TRANSLATABLE = 'Sw:Test:DefaultedTranslatable';

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
            new ContentSystemElementTypeSpecification(
                self::DEFAULTED_PRIMITIVE,
                'Defaulted primitive test element',
                '',
                null,
                null,
                new CopilotSpecification('', []),
                [
                    'headline' => new PropertySpecification(
                        'headline',
                        new PropertyType('string', false, null, 'Seeded headline'),
                        true,
                        '',
                        '',
                        null,
                    ),
                ],
                [],
                self::SOURCE,
            ),
            new ContentSystemElementTypeSpecification(
                self::TRANSLATABLE_REQUIRED,
                'Required translatable test element',
                '',
                null,
                null,
                new CopilotSpecification('', []),
                [
                    'label' => new PropertySpecification(
                        'label',
                        new PropertyType('string', true, null, null),
                        true,
                        '',
                        '',
                        null,
                    ),
                ],
                [],
                self::SOURCE,
            ),
            new ContentSystemElementTypeSpecification(
                self::DEFAULTED_TRANSLATABLE,
                'Defaulted translatable test element',
                '',
                null,
                null,
                new CopilotSpecification('', []),
                [
                    'tagline' => new PropertySpecification(
                        'tagline',
                        new PropertyType('string', true, null, 'Seeded tagline'),
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
