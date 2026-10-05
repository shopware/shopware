<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Driver\PDO\Exception as PdoDriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ForeignKeyConstraintViolationExceptionHandler;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidForeignKeyReferenceException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ForeignKeyConstraintViolationExceptionHandler::class)]
class ForeignKeyConstraintViolationExceptionHandlerTest extends TestCase
{
    public function testForeignKeyViolationBecomesAFieldSpecificShopwareError(): void
    {
        $driverException = PdoDriverException::new(new \PDOException('SQLSTATE[23000]: Integrity constraint violation: 1452 foreign key constraint fails (`shopware`.`product_category`, CONSTRAINT `fk.product_category.category_id` FOREIGN KEY (`category_id`, `category_version_id`) REFERENCES `category` (`id`, `version_id`))'));

        $registry = new StaticDefinitionInstanceRegistry(
            [ProductCategoryDefinition::class, ProductDefinition::class, CategoryDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );
        $handler = new ForeignKeyConstraintViolationExceptionHandler($registry);

        $exception = $handler->matchException(new ForeignKeyConstraintViolationException($driverException, null));

        static::assertInstanceOf(InvalidForeignKeyReferenceException::class, $exception);
        $error = $exception->getErrors()->current();
        static::assertSame('FRAMEWORK__INVALID_FOREIGN_KEY_REFERENCE', $error['code']);
        static::assertSame('/categoryId', $error['source']['pointer']);
        static::assertStringContainsString('product_category', (string) $error['detail']);
        static::assertStringContainsString('category', (string) $error['detail']);
    }

    public function testUnrelatedExceptionIsNotHandled(): void
    {
        $handler = new ForeignKeyConstraintViolationExceptionHandler(new StaticDefinitionInstanceRegistry(
            [],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        ));

        static::assertSame(ExceptionHandlerInterface::PRIORITY_DEFAULT, $handler->getPriority());
        static::assertNull($handler->matchException(new \RuntimeException('Unrelated failure')));
    }
}
