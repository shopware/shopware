<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LayoutReference::class)]
class LayoutReferenceTest extends TestCase
{
    public function testCreateDefaultsToEmptySettings(): void
    {
        $reference = LayoutReference::create('layout-1', 'Landing', '1.0.0');

        static::assertSame('layout-1', $reference->id);
        static::assertSame('Landing', $reference->name);
        static::assertSame('1.0.0', $reference->version);
        static::assertSame([], $reference->settings);
    }

    public function testFromEntityCarriesTheLayoutSettings(): void
    {
        $settings = ['scrollNavigation' => ['active' => true, 'position' => 'left']];

        $entity = new ContentLayoutEntity();
        $entity->setId('layout-1');
        $entity->setName('Landing');
        $entity->setVersion('1.0.0');
        $entity->setSettings($settings);

        $reference = LayoutReference::fromEntity($entity);

        static::assertSame($settings, $reference->settings);
    }

    public function testFromEntityTreatsMissingSettingsAsEmpty(): void
    {
        $entity = new ContentLayoutEntity();
        $entity->setId('layout-1');
        $entity->setName('Landing');
        $entity->setVersion('1.0.0');

        static::assertSame([], LayoutReference::fromEntity($entity)->settings);
    }
}
