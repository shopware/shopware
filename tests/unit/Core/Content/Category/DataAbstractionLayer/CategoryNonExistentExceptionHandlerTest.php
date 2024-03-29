<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\DataAbstractionLayer;

use Doctrine\DBAL\Driver\PDO\PDOException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Query;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryException;
use Shopware\Core\Content\Category\DataAbstractionLayer\CategoryNonExistentExceptionHandler;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;

/**
 * @internal
 */
#[CoversClass(CategoryNonExistentExceptionHandler::class)]
class CategoryNonExistentExceptionHandlerTest extends TestCase
{
    public function testExceptionHandler(): void
    {
        $handler = new CategoryNonExistentExceptionHandler();

        static::assertEquals(ExceptionHandlerInterface::PRIORITY_DEFAULT, $handler->getPriority());

        $afterException = new ForeignKeyConstraintViolationException(
            new PDOException('SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails (`shopware`.`category`, CONSTRAINT `fk.category.after_category_id` FOREIGN KEY (`after_category_id`, `after_category_version_id`) REFERENCES `category` (`id`, `version_id`) ON DELETE SET NULL O)'),
            new Query('SOME QUERY', [], [])
        );

        $matched = $handler->matchException($afterException);

        static::assertInstanceOf(CategoryException::class, $matched);
        static::assertEquals('Category to insert after not found.', $matched->getMessage());

        static::assertNull($handler->matchException(new \Exception('Some other exception')));
    }
}
