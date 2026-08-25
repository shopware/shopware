<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentLayoutDefinition::class)]
class ContentLayoutDefinitionTest extends TestCase
{
    public function testDeclaresNoDefaultsWhichLayoutWriteContextsCommandToMemoFifoPairingDependsOn(): void
    {
        $definition = new ContentLayoutDefinition();

        $reason = 'LayoutWriteContext\'s command-to-memo pairing depends on '
            . 'WriteCommandExtractor::createDataStack() never re-normalizing a content_layout row. The '
            . 're-normalize happens on create only, because createDataStack() returns early for a row that '
            . 'already exists. See the LayoutWriteContext class docblock for the mechanism.';

        static::assertSame([], $definition->getDefaults(), $reason);
        static::assertSame([], $definition->getChildDefaults(), $reason);
    }
}
