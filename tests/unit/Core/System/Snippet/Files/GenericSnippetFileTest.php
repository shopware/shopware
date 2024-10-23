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

        static::assertEquals($ids->get('name'), $exception->getName());
        static::assertEquals($ids->get('author'), $exception->getAuthor());
        static::assertEquals($ids->get('iso'), $exception->getIso());
        static::assertEquals($isBase, $exception->isBase());
        static::assertEquals($ids->get('path'), $exception->getPath());
        static::assertEquals($ids->get('technicalName'), $exception->getTechnicalName());
    }
}
