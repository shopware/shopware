<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Defaults;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopware\Core\Content\MailTemplate\Defaults\MailTemplateDefaultsRegistry;
use Shopware\Core\Content\MailTemplate\Defaults\MailTemplateResolver;
use Shopware\Core\Content\MailTemplate\Defaults\ResolvedMailTemplate;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;

/**
 * @internal
 */
#[CoversClass(MailTemplateResolver::class)]
class MailTemplateResolverTest extends TestCase
{
    public function testUsesDefaultsWhenEntityFieldsAreNull(): void
    {
        $resolver = $this->makeResolver();

        $entity = $this->makeEntity('order_confirmation');

        $resolved = $resolver->resolve($entity, $this->makeContext());

        static::assertSame('Your order {{ order.orderNumber }}', $resolved->subject);
        static::assertSame('{{ salesChannel.name }}', $resolved->senderName);
        static::assertSame(ResolvedMailTemplate::SOURCE_DEFAULT, $resolved->source['subject']);
        static::assertSame(ResolvedMailTemplate::SOURCE_DEFAULT, $resolved->source['senderName']);
    }

    public function testEntityValuesOverrideDefaults(): void
    {
        $resolver = $this->makeResolver();

        $entity = $this->makeEntity('order_confirmation');
        $entity->setSubject('My custom subject');

        $resolved = $resolver->resolve($entity, $this->makeContext());

        static::assertSame('My custom subject', $resolved->subject);
        static::assertSame(ResolvedMailTemplate::SOURCE_USER, $resolved->source['subject']);
        // Other fields still come from the default
        static::assertSame('{{ salesChannel.name }}', $resolved->senderName);
        static::assertSame(ResolvedMailTemplate::SOURCE_DEFAULT, $resolved->source['senderName']);
    }

    public function testReturnsEmptyStringsWhenNoDefaultRegistered(): void
    {
        $resolver = $this->makeResolver();

        $entity = $this->makeEntity('unknown_template');

        $resolved = $resolver->resolve($entity, $this->makeContext());

        static::assertSame('', $resolved->subject);
        static::assertSame('', $resolved->contentHtml);
    }

    public function testResolveDefaultsBypassesEntity(): void
    {
        $resolver = $this->makeResolver();

        $entity = $this->makeEntity('order_confirmation');
        $entity->setSubject('overridden');

        $default = $resolver->resolveDefaults($entity, $this->makeContext());

        static::assertNotNull($default);
        static::assertSame('Your order {{ order.orderNumber }}', $default->subject);
    }

    private function makeResolver(): MailTemplateResolver
    {
        $registry = new MailTemplateDefaultsRegistry(__DIR__ . '/../_fixtures');

        $localeProvider = $this->createMock(LanguageLocaleCodeProvider::class);
        $localeProvider->method('getLocaleForLanguageId')->willReturn('en-GB');

        return new MailTemplateResolver($registry, $localeProvider);
    }

    private function makeEntity(string $technicalName): MailTemplateEntity
    {
        $type = new MailTemplateTypeEntity();
        $type->setTechnicalName($technicalName);

        $entity = new MailTemplateEntity();
        $entity->setMailTemplateType($type);

        return $entity;
    }

    private function makeContext(): Context
    {
        return Context::createDefaultContext();
    }
}
