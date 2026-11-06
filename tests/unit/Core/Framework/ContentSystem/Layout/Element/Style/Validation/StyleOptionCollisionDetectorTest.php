<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Style\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Validation\StyleOptionCollisionDetector;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StyleOptionCollisionDetector::class)]
class StyleOptionCollisionDetectorTest extends TestCase
{
    #[TestDox('passes when no proposed name is already registered')]
    public function testPassesForNonCollidingNames(): void
    {
        $detector = $this->detector(['col-span' => 'core']);

        $this->expectNotToPerformAssertions();
        $detector->validate(['brand-accent' => 'app:Acme'], null, []);
    }

    #[TestDox('skips a collision the proposing source already owns when updating')]
    public function testSkipsSelfOwnedCollision(): void
    {
        $detector = $this->detector(['col-span' => 'app:Acme']);

        $this->expectNotToPerformAssertions();
        $detector->validate(['col-span' => 'app:Acme'], 'app:Acme', []);
    }

    #[TestDox('fails hard when a proposed name collides with a registered option')]
    public function testFailsForRegistryCollision(): void
    {
        $detector = $this->detector(['col-span' => 'core']);

        $this->expectExceptionObject(ContentSystemException::styleOptionDuplicate('col-span', 'core', 'app:Acme'));

        $detector->validate(['col-span' => 'app:Acme'], null, []);
    }

    #[TestDox('fails hard when a proposed name collides with an inactive app option')]
    public function testFailsForInactiveCollision(): void
    {
        $detector = $this->detector([]);

        $this->expectExceptionObject(ContentSystemException::styleOptionDuplicate('brand-accent', 'app:Dormant', 'app:Acme'));

        $detector->validate(['brand-accent' => 'app:Acme'], null, ['brand-accent' => 'app:Dormant']);
    }

    /**
     * @param array<string, string> $registered name => source
     */
    private function detector(array $registered): StyleOptionCollisionDetector
    {
        $options = [];
        foreach ($registered as $name => $source) {
            $options[$name] = new StyleOptionSpecification($name, new StyleOptionValueType('integer', null, null, null, null), true, null, $source);
        }

        $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('all')->willReturn($options);

        return new StyleOptionCollisionDetector($registry);
    }
}
