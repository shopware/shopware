<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\OAuthClient\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\JsonFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\OAuthClient\Field\RedirectUriListField;
use Shopware\Core\System\OAuthClient\Field\RedirectUriListFieldSerializer;
use Shopware\Core\System\OAuthClient\OAuthClientDefinition;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RedirectUriListField::class)]
class RedirectUriListFieldTest extends TestCase
{
    public function testOAuthClientDefinitionUsesTheCustomSerializer(): void
    {
        $container = new Container();
        $registry = new DefinitionInstanceRegistry($container, [], []);
        $serializer = new RedirectUriListFieldSerializer(Validation::createValidator(), $registry);
        $container->set(RedirectUriListFieldSerializer::class, $serializer);
        $container->set(JsonFieldAccessorBuilder::class, static::createStub(JsonFieldAccessorBuilder::class));

        $definition = new OAuthClientDefinition();
        $definition->compile($registry);
        $field = $definition->getFields()->get('redirectUris');

        static::assertInstanceOf(RedirectUriListField::class, $field);
        static::assertTrue($field->is(Required::class));
        static::assertSame('redirect_uris', $field->getStorageName());
        static::assertSame($serializer, $field->getSerializer());
    }
}
