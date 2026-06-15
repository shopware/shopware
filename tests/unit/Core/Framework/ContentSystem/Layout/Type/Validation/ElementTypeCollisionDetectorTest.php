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
    #[TestDox('accepts proposed names when no collision exists')]
    public function testValidatePassesWhenNamesDoNotConflict(): void
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

    #[TestDox('skips collision when the registered type source matches excludeSource')]
    public function testValidatePassesWhenRegisteredSourceMatchesExcludeSource(): void
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

    #[TestDox('accepts empty proposed map without checking registry')]
    public function testValidatePassesWhenProposedMapIsEmpty(): void
    {
        $detector = new ElementTypeCollisionDetector(
            $this->buildRegistry(['Sw:Hero' => 'core']),
        );

        $this->expectNotToPerformAssertions();

        $detector->validate(
            [],
            null,
            ['App:Other' => 'app:OtherApp'],
        );
    }

    #[TestDox('throws when a proposed name collides with an active registered type')]
    public function testValidateThrowsWhenNameCollidesWithActiveType(): void
    {
        $detector = new ElementTypeCollisionDetector(
            $this->buildRegistry(['Sw:Hero' => 'core']),
        );

        $this->expectExceptionObject(
            ContentSystemException::elementTypeDuplicate('Sw:Hero', 'core', 'app:MyApp')
        );

        $detector->validate(
            ['Sw:Hero' => 'app:MyApp'],
            null,
            [],
        );
    }

    #[TestDox('throws when a proposed name collides with an additional registered name')]
    public function testValidateThrowsWhenNameCollidesWithAdditionalRegistered(): void
    {
        $detector = new ElementTypeCollisionDetector(
            $this->buildRegistry([]),
        );

        $this->expectExceptionObject(
            ContentSystemException::elementTypeDuplicate('App:Hero', 'app:OtherApp', 'app:MyApp')
        );

        $detector->validate(
            ['App:Hero' => 'app:MyApp'],
            null,
            ['App:Hero' => 'app:OtherApp'],
        );
    }

    #[TestDox('throws for additional registered collision even when excludeSource matches')]
    public function testValidateThrowsForAdditionalRegisteredEvenWithMatchingExcludeSource(): void
    {
        $detector = new ElementTypeCollisionDetector(
            $this->buildRegistry([]),
        );

        $this->expectExceptionObject(
            ContentSystemException::elementTypeDuplicate('App:Hero', 'app:MyApp', 'app:MyApp')
        );

        $detector->validate(
            ['App:Hero' => 'app:MyApp'],
            'app:MyApp',
            ['App:Hero' => 'app:MyApp'],
        );
    }

    #[TestDox('throws for colliding name even when first proposed name passes')]
    public function testValidateThrowsForSecondProposedNameWhenFirstPasses(): void
    {
        $detector = new ElementTypeCollisionDetector(
            $this->buildRegistry(['App:Hero' => 'core']),
        );

        $this->expectExceptionObject(
            ContentSystemException::elementTypeDuplicate('App:Hero', 'core', 'app:MyApp')
        );

        $detector->validate(
            ['App:Safe' => 'app:MyApp', 'App:Hero' => 'app:MyApp'],
            null,
            [],
        );
    }

    #[TestDox('throws when excludeSource does not match the colliding registered source')]
    public function testValidateThrowsWhenExcludeSourceDoesNotMatchExistingSource(): void
    {
        $detector = new ElementTypeCollisionDetector(
            $this->buildRegistry(['Sw:Hero' => 'core']),
        );

        $this->expectExceptionObject(
            ContentSystemException::elementTypeDuplicate('Sw:Hero', 'core', 'app:MyApp')
        );

        $detector->validate(
            ['Sw:Hero' => 'app:MyApp'],
            'app:MyApp',
            [],
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
