<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Files;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\System\Snippet\Files\GenericSnippetFile;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[CoversClass(GenericSnippetFile::class)]
class GenericSnippetFileTest extends TestCase
{
    public function testInstantiate(): void
    {
        $ids = new IdsCollection();

        $isBase = Random::getBoolean();

        $exception = new GenericSnippetFile(
            $ids->get('name'),
            $ids->get('path'),
            $ids->get('iso'),
            $ids->get('author'),
            $isBase,
            $ids->get('technicalName'),
        );

        static::assertSame($ids->get('name'), $exception->getName());
        static::assertSame($ids->get('author'), $exception->getAuthor());
        static::assertSame($ids->get('iso'), $exception->getIso());
        static::assertSame($isBase, $exception->isBase());
        static::assertSame($ids->get('path'), $exception->getPath());
        static::assertSame($ids->get('technicalName'), $exception->getTechnicalName());
    }
}
