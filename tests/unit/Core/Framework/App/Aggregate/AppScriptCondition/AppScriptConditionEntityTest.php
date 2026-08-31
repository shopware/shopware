<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Aggregate\AppScriptCondition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppScriptConditionEntity::class)]
class AppScriptConditionEntityTest extends TestCase
{
    public function testConstraintsRoundTrip(): void
    {
        $entity = new AppScriptConditionEntity();
        $entity->setConstraints(['operator' => []]);

        static::assertSame(['operator' => []], $entity->getConstraints());
    }
}
