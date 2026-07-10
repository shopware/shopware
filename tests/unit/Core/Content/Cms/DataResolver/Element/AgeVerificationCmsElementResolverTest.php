<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\DataResolver\Element;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\AgeVerificationCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\AgeVerificationStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AgeVerificationCmsElementResolver::class)]
class AgeVerificationCmsElementResolverTest extends TestCase
{
    private AgeVerificationCmsElementResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new AgeVerificationCmsElementResolver();
    }

    public function testType(): void
    {
        static::assertSame('age-verification', $this->resolver->getType());
    }

    public function testCollectReturnsNull(): void
    {
        $slot = $this->createSlot();
        $slot->setFieldConfig(new FieldConfigCollection());

        static::assertNull($this->resolver->collect($slot, $this->createResolverContext()));
    }

    public function testEnrichWithEmptyConfigUsesDefaults(): void
    {
        $slot = $this->createSlot();
        $slot->setFieldConfig(new FieldConfigCollection());

        $this->resolver->enrich($slot, $this->createResolverContext(), new ElementDataCollection());

        $struct = $slot->getData();
        static::assertInstanceOf(AgeVerificationStruct::class, $struct);
        static::assertSame(18, $struct->getMinimumAge());
        static::assertSame(30, $struct->getCookieLifetime());
        static::assertNull($struct->getTitle());
        static::assertNull($struct->getContent());
        static::assertNull($struct->getConfirmButtonText());
        static::assertNull($struct->getDeclineButtonText());
        static::assertNull($struct->getDeclineUrl());
    }

    public function testEnrichWithStaticConfig(): void
    {
        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('minimumAge', FieldConfig::SOURCE_STATIC, 21));
        $fieldConfig->add(new FieldConfig('cookieLifetime', FieldConfig::SOURCE_STATIC, 90));
        $fieldConfig->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, 'Age check'));
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, 'You must be of legal drinking age.'));
        $fieldConfig->add(new FieldConfig('confirmButtonText', FieldConfig::SOURCE_STATIC, 'Yes'));
        $fieldConfig->add(new FieldConfig('declineButtonText', FieldConfig::SOURCE_STATIC, 'No'));
        $fieldConfig->add(new FieldConfig('declineUrl', FieldConfig::SOURCE_STATIC, 'https://www.google.com'));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->resolver->enrich($slot, $this->createResolverContext(), new ElementDataCollection());

        $struct = $slot->getData();
        static::assertInstanceOf(AgeVerificationStruct::class, $struct);
        static::assertSame(21, $struct->getMinimumAge());
        static::assertSame(90, $struct->getCookieLifetime());
        static::assertSame('Age check', $struct->getTitle());
        static::assertSame('You must be of legal drinking age.', $struct->getContent());
        static::assertSame('Yes', $struct->getConfirmButtonText());
        static::assertSame('No', $struct->getDeclineButtonText());
        static::assertSame('https://www.google.com', $struct->getDeclineUrl());
    }

    public function testEmptyStringsFallBackToNull(): void
    {
        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, ''));
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, ''));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->resolver->enrich($slot, $this->createResolverContext(), new ElementDataCollection());

        $struct = $slot->getData();
        static::assertInstanceOf(AgeVerificationStruct::class, $struct);
        static::assertNull($struct->getTitle());
        static::assertNull($struct->getContent());
    }

    public function testInvalidMinimumAgeKeepsDefault(): void
    {
        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('minimumAge', FieldConfig::SOURCE_STATIC, 0));
        $fieldConfig->add(new FieldConfig('cookieLifetime', FieldConfig::SOURCE_STATIC, null));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->resolver->enrich($slot, $this->createResolverContext(), new ElementDataCollection());

        $struct = $slot->getData();
        static::assertInstanceOf(AgeVerificationStruct::class, $struct);
        static::assertSame(18, $struct->getMinimumAge());
        static::assertSame(30, $struct->getCookieLifetime());
    }

    private function createSlot(): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('age-verification');
        $slot->setConfig([]);

        return $slot;
    }

    private function createResolverContext(): ResolverContext
    {
        return new ResolverContext(static::createStub(SalesChannelContext::class), new Request());
    }
}
