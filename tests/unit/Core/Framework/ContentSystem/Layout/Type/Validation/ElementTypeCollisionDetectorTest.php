<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\ElementTypeCollisionDetector;

/**
 * @internal
 */
#[CoversClass(ElementTypeCollisionDetector::class)]
class ElementTypeCollisionDetectorTest extends TestCase
{
    #[TestDox('passes without exception when proposed names do not conflict')]
    public function testNoCollision(): void
    {
        $detector = new ElementTypeCollisionDetector(
            $this->buildRegistry(['Sw:Existing' => 'core']),
        );

        $this->expectNotToPerformAssertions();

        $detector->validate(
            ['App:New' => 'app:MyApp'],
            null,
            [],
        );
    }

    #[TestDox('throws when a proposed name collides with an active registered type')]
    public function testActiveTypeCollision(): void
    {
        $detector = new ElementTypeCollisionDetector(
            $this->buildRegistry(['Sw:Hero' => 'core']),
        );

        $this->expectException(ContentSystemException::class);

        $detector->validate(
            ['Sw:Hero' => 'app:MyApp'],
            null,
            [],
        );
    }

    #[TestDox('skips collision when the registered type source matches excludeSource')]
    public function testExcludedSourceBypass(): void
    {
        $detector = new ElementTypeCollisionDetector(
            $this->buildRegistry(['App:Hero' => 'app:MyApp']),
        );

        $this->expectNotToPerformAssertions();

        $detector->validate(
            ['App:Hero' => 'app:MyApp'],
            'app:MyApp',
            [],
        );
    }

    #[TestDox('throws when a proposed name collides with an additional registered name')]
    public function testAdditionalRegisteredCollision(): void
    {
        $detector = new ElementTypeCollisionDetector(
            $this->buildRegistry([]),
        );

        $this->expectException(ContentSystemException::class);

        $detector->validate(
            ['App:Hero' => 'app:MyApp'],
            null,
            ['App:Hero' => 'app:OtherApp'],
        );
    }

    #[TestDox('excludeSource does not skip additional registered entries')]
    public function testExcludeSourceDoesNotAffectAdditionalRegistered(): void
    {
        $detector = new ElementTypeCollisionDetector(
            $this->buildRegistry([]),
        );

        $this->expectException(ContentSystemException::class);

        $detector->validate(
            ['App:Hero' => 'app:MyApp'],
            'app:MyApp',
            ['App:Hero' => 'app:MyApp'],
        );
    }

    /**
     * @param array<string, string> $types name => source
     */
    private function buildRegistry(array $types): AbstractContentSystemElementTypeRegistry
    {
        $specs = [];
        foreach ($types as $name => $source) {
            $specs[$name] = new ContentSystemElementTypeSpecification(
                $name,
                $name,
                'test',
                'test',
                null,
                null,
                new CopilotSpecification('test', []),
                [],
                [],
                $source,
            );
        }

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('all')->willReturn($specs);

        return $registry;
    }
}
