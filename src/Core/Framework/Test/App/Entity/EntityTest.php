<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Entity;

use Doctrine\DBAL\Connection;
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
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\CustomEntity\CustomEntityPersister;

class EntityTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testCreateFromXml(): void
    {
        $entities = CustomEntities::createFromXmlFile(__DIR__ . '/_fixtures/customEntities/Resources/entities.xml');

        $expected = new CustomEntities(
            __DIR__ . '/_fixtures/customEntities/Resources',
            new Entities([
                new Entity([
                    'name' => 'blog',
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
                        new OneToManyField(['name' => 'comments', 'storeApiAware' => true, 'reference' => 'blog_comment']),
                        new OneToOneField(['name' => 'author', 'storeApiAware' => false, 'reference' => 'user']),
                    ],
                ]),
                new Entity([
                    'name' => 'blog_comment',
                    'storeApiAware' => true,
                    'fields' => [
                        new StringField(['name' => 'title', 'storeApiAware' => true, 'required' => true, 'translatable' => true]),
                        new TextField(['name' => 'content', 'storeApiAware' => true, 'allowHtml' => true, 'translatable' => true]),
                        new EmailField(['name' => 'email', 'storeApiAware' => false]),
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
            ['name' => 'comments', 'type' => 'one-to-many', 'reference' => 'blog_comment', 'storeApiAware' => true],
            ['name' => 'author', 'type' => 'one-to-one', 'reference' => 'user', 'storeApiAware' => false],
        ];

        static::assertEquals('blog', $storage[0]['name']);
        static::assertEquals($fields, json_decode($storage[0]['fields'], true));

        $fields = [
            ['name' => 'title', 'type' => 'string', 'required' => true, 'translatable' => true, 'storeApiAware' => true],
            ['name' => 'content', 'type' => 'text', 'allowHtml' => true, 'translatable' => true, 'storeApiAware' => true],
            ['name' => 'email', 'type' => 'email', 'storeApiAware' => false],
        ];
        static::assertEquals('blog_comment', $storage[1]['name']);
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
}
