<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Entity;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\FloatType;
use Doctrine\DBAL\Types\StringType;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Entity\CustomEntities;
use Shopware\Core\Framework\App\Entity\Xml\Entities;
use Shopware\Core\Framework\App\Entity\Xml\Entity;
use Shopware\Core\Framework\App\Entity\Xml\Field\BoolField;
use Shopware\Core\Framework\App\Entity\Xml\Field\EmailField;
use Shopware\Core\Framework\App\Entity\Xml\Field\FloatField;
use Shopware\Core\Framework\App\Entity\Xml\Field\IntField;
use Shopware\Core\Framework\App\Entity\Xml\Field\JsonField;
use Shopware\Core\Framework\App\Entity\Xml\Field\ManyToManyField;
use Shopware\Core\Framework\App\Entity\Xml\Field\ManyToOneField;
use Shopware\Core\Framework\App\Entity\Xml\Field\OneToManyField;
use Shopware\Core\Framework\App\Entity\Xml\Field\OneToOneField;
use Shopware\Core\Framework\App\Entity\Xml\Field\StringField;
use Shopware\Core\Framework\App\Entity\Xml\Field\TextField;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\CustomEntity\CustomEntityPersister;
use Shopware\Core\System\CustomEntity\CustomEntitySchemaUpdater;

class EntityTest extends TestCase
{
    use KernelTestBehaviour;

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cleanUp();
    }

    public function testCreateFromXml(): void
    {
        $entities = CustomEntities::createFromXmlFile(__DIR__ . '/_fixtures/customEntities/Resources/entities.xml');

        $expected = new CustomEntities(
            __DIR__ . '/_fixtures/customEntities/Resources',
            new Entities([
                new Entity([
                    'name' => 'custom_blog',
                    'storeApiAware' => true,
                    'fields' => [
                        new IntField(['name' => 'position', 'storeApiAware' => true]),
                        new FloatField(['name' => 'rating', 'storeApiAware' => true]),
                        new StringField(['name' => 'title', 'storeApiAware' => true, 'required' => true, 'translatable' => true]),
                        new TextField(['name' => 'content', 'storeApiAware' => true, 'allowHtml' => true, 'translatable' => true]),
                        new BoolField(['name' => 'display', 'storeApiAware' => true, 'translatable' => true]),
                        new JsonField(['name' => 'payload', 'storeApiAware' => false]),
                        new EmailField(['name' => 'email', 'storeApiAware' => false]),
                        new ManyToManyField(['name' => 'products', 'storeApiAware' => true, 'reference' => 'product']),
                        new ManyToOneField(['name' => 'top_seller', 'storeApiAware' => true, 'reference' => 'product', 'required' => true]),
                        new OneToManyField(['name' => 'comments', 'storeApiAware' => true, 'reference' => 'custom_blog_comment']),
                        new OneToOneField(['name' => 'author', 'storeApiAware' => false, 'reference' => 'user']),
                    ],
                ]),
                new Entity([
                    'name' => 'custom_blog_comment',
                    'storeApiAware' => true,
                    'fields' => [
                        new StringField(['name' => 'title', 'storeApiAware' => true, 'required' => true, 'translatable' => true]),
                        new TextField(['name' => 'content', 'storeApiAware' => true, 'allowHtml' => true, 'translatable' => true]),
                        new EmailField(['name' => 'email', 'storeApiAware' => false]),
                        new OneToManyField(['name' => 'products', 'reference' => 'product', 'storeApiAware' => true]),
                    ],
                ]),
            ])
        );

        static::assertEquals($expected, $entities);
    }

    public function testPersist(): void
    {
        $entities = CustomEntities::createFromXmlFile(__DIR__ . '/_fixtures/customEntities/Resources/entities.xml');

        $this->getContainer()
            ->get(CustomEntityPersister::class)
            ->update($entities->toStorage(), null);

        $storage = $this->getContainer()->get(Connection::class)
            ->fetchAllAssociative('SELECT * FROM custom_entity ORDER BY name');

        static::assertCount(2, $storage);

        $fields = [
            ['name' => 'position', 'type' => 'int', 'storeApiAware' => true],
            ['name' => 'rating', 'type' => 'float', 'storeApiAware' => true],
            ['name' => 'title', 'type' => 'string', 'required' => true, 'translatable' => true, 'storeApiAware' => true],
            ['name' => 'content', 'type' => 'text', 'allowHtml' => true, 'translatable' => true, 'storeApiAware' => true],
            ['name' => 'display', 'type' => 'bool', 'translatable' => true, 'storeApiAware' => true],
            ['name' => 'payload', 'type' => 'json', 'storeApiAware' => false],
            ['name' => 'email', 'type' => 'email', 'storeApiAware' => false],
            ['name' => 'products', 'type' => 'many-to-many', 'reference' => 'product', 'storeApiAware' => true],
            ['name' => 'top_seller', 'type' => 'many-to-one', 'required' => true, 'reference' => 'product', 'storeApiAware' => true],
            ['name' => 'comments', 'type' => 'one-to-many', 'reference' => 'custom_blog_comment', 'storeApiAware' => true],
            ['name' => 'author', 'type' => 'one-to-one', 'reference' => 'user', 'storeApiAware' => false],
        ];

        static::assertEquals('custom_blog', $storage[0]['name']);
        static::assertEquals($fields, json_decode($storage[0]['fields'], true));

        $fields = [
            ['name' => 'title', 'type' => 'string', 'required' => true, 'translatable' => true, 'storeApiAware' => true],
            ['name' => 'content', 'type' => 'text', 'allowHtml' => true, 'translatable' => true, 'storeApiAware' => true],
            ['name' => 'email', 'type' => 'email', 'storeApiAware' => false],
            ['name' => 'products', 'type' => 'one-to-many', 'reference' => 'product', 'storeApiAware' => true],
        ];
        static::assertEquals('custom_blog_comment', $storage[1]['name']);
        static::assertEquals($fields, json_decode($storage[1]['fields'], true));

        static::assertNotNull($storage[0]['created_at']);
        static::assertNotNull($storage[1]['created_at']);

        $entities = CustomEntities::createFromXmlFile(__DIR__ . '/_fixtures/customEntities/Resources/entities.xml');

        $this->getContainer()
            ->get(CustomEntityPersister::class)
            ->update($entities->toStorage(), null);

        $storage = $this->getContainer()->get(Connection::class)
            ->fetchAllAssociative('SELECT * FROM custom_entity ORDER BY name');

        static::assertCount(2, $storage);
        static::assertNotNull($storage[0]['updated_at']);
        static::assertNotNull($storage[1]['updated_at']);
    }

    public function testSchemaCreate(): void
    {
        $entities = CustomEntities::createFromXmlFile(__DIR__ . '/_fixtures/customEntities/Resources/entities.xml');

        $this->cleanUp();

        $this->getContainer()
            ->get(CustomEntityPersister::class)
            ->update($entities->toStorage(), null);

        $this->getContainer()
            ->get(CustomEntitySchemaUpdater::class)
            ->update();

        $schema = $this->getContainer()
            ->get(Connection::class)
            ->getSchemaManager()
            ->createSchema();

        static::assertTrue($schema->hasTable('custom_blog'));
        static::assertTrue($schema->hasTable('custom_blog_comment'));
        static::assertTrue($schema->hasTable('custom_blog_product'));

        $table = $schema->getTable('custom_blog');
        static::assertTrue($table->hasColumn('id'));
        static::assertTrue($table->hasColumn('position'));
        static::assertTrue($table->hasColumn('rating'));
        static::assertTrue($table->hasColumn('title'));
        static::assertTrue($table->hasColumn('content'));
        static::assertTrue($table->hasColumn('display'));
        static::assertTrue($table->hasColumn('payload'));
        static::assertTrue($table->hasColumn('email'));
        static::assertTrue($table->hasColumn('top_seller_id'));

        $table = $schema->getTable('custom_blog_comment');
        static::assertTrue($table->hasColumn('id'));
        static::assertTrue($table->hasColumn('title'));
        static::assertTrue($table->hasColumn('content'));
        static::assertTrue($table->hasColumn('email'));
        static::assertTrue($table->hasColumn('custom_blog_id'));

        $table = $schema->getTable('custom_blog_product');
        static::assertTrue($table->hasColumn('custom_blog_id'));
        static::assertTrue($table->hasColumn('product_id'));

        $table = $schema->getTable('product');
        static::assertTrue($table->hasColumn('custom_blog_comment_id'));
    }

    public function testSchemaUpdate(): void
    {
        $entities = CustomEntities::createFromXmlFile(__DIR__ . '/_fixtures/customEntities/Resources/install.xml');
        $this->getContainer()
            ->get(CustomEntityPersister::class)
            ->update($entities->toStorage(), null);

        $this->getContainer()
            ->get(CustomEntitySchemaUpdater::class)
            ->update();

        $schema = $this->getSchema();
        static::assertTrue($schema->hasTable('custom_blog'));
        static::assertTrue($schema->getTable('custom_blog')->hasColumn('position'));
        static::assertTrue($schema->getTable('custom_blog')->hasColumn('top_seller_id'));
        static::assertTrue($schema->getTable('custom_blog')->hasColumn('author_id'));

        static::assertTrue($schema->hasTable('custom_blog_comment'));
        static::assertTrue($schema->getTable('custom_blog_comment')->hasColumn('custom_blog_id'));
        static::assertTrue($schema->getTable('product')->hasColumn('custom_blog_comment_id'));

        $entities = CustomEntities::createFromXmlFile(__DIR__ . '/_fixtures/customEntities/Resources/update.xml');
        $this->getContainer()
            ->get(CustomEntityPersister::class)
            ->update($entities->toStorage(), null);

        $this->getContainer()
            ->get(CustomEntitySchemaUpdater::class)
            ->update();

        $schema = $this->getSchema();

        static::assertTrue($schema->hasTable('custom_blog'));
        static::assertFalse($schema->getTable('custom_blog')->hasColumn('position'));
        static::assertFalse($schema->getTable('custom_blog')->hasColumn('top_seller_id'));
        static::assertFalse($schema->getTable('custom_blog')->hasColumn('author_id'));
        static::assertFalse($schema->getTable('product')->hasColumn('custom_blog_comment_id'));

        static::assertInstanceOf(StringType::class, $schema->getTable('custom_blog')->getColumn('rating')->getType());
        static::assertInstanceOf(FloatType::class, $schema->getTable('custom_blog')->getColumn('title')->getType());

        //many-to-many association removed
        static::assertTrue($schema->hasTable('custom_blog_comment'));
        static::assertFalse($schema->hasTable('custom_blog_product'));
        static::assertFalse($schema->hasTable('custom_to_remove'));
    }

    private function getSchema(): Schema
    {
        return $this->getContainer()
            ->get(Connection::class)
            ->getSchemaManager()
            ->createSchema();
    }

    private function cleanUp(): void
    {
        try {
            $this->getContainer()->get(Connection::class)
                ->executeStatement('ALTER TABLE product DROP COLUMN `custom_blog_comment_id`');
        } catch (Exception $e) {
        }

        $this->getContainer()->get(Connection::class)
            ->executeStatement('DROP TABLE IF EXISTS blog_product');

        $this->getContainer()->get(Connection::class)
            ->executeStatement('DROP TABLE IF EXISTS blog_comment');

        $this->getContainer()->get(Connection::class)
            ->executeStatement('DROP TABLE IF EXISTS blog');

        $this->getContainer()->get(Connection::class)
            ->executeStatement('DELETE FROM custom_entity');
    }
}
