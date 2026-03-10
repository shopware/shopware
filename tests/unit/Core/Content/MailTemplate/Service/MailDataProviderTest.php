<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\Service\Event\MailErrorEvent;
use Shopware\Core\Content\MailTemplate\Service\MailDataProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(MailDataProvider::class)]
#[Package('after-sales')]
class MailDataProviderTest extends TestCase
{
    public function testTemplateDataWithoutFlowEventAwareClass(): void
    {
        $definitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);

        $context = Context::createDefaultContext();

        $languageEntity = $this->getLanguageEntity('en-US', $context);

        /** @var StaticEntityRepository<LanguageCollection> $languageCollection */
        $languageCollection = new StaticEntityRepository([new LanguageCollection([$languageEntity])]);

        $mailDataProvider = new MailDataProvider([], $definitionInstanceRegistry, $languageCollection);

        // @phpstan-ignore-next-line
        $result = $mailDataProvider->getTemplateData(self::class, $context);

        static::assertSame([], $result);
    }

    public function testTemplateDataMissingMailAware(): void
    {
        $definitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);

        $context = Context::createDefaultContext();

        $languageEntity = $this->getLanguageEntity('en-US', $context);

        /** @var StaticEntityRepository<LanguageCollection> $languageCollection */
        $languageCollection = new StaticEntityRepository([new LanguageCollection([$languageEntity])]);

        $mailDataProvider = new MailDataProvider([], $definitionInstanceRegistry, $languageCollection);

        $result = $mailDataProvider->getTemplateData(MailErrorEvent::class, $context);

        static::assertSame([], $result);
    }

    private function getLanguageEntity(string $localeCode, Context $context): LanguageEntity
    {
        $languageEntity = new LanguageEntity();
        $languageEntity->setId($context->getLanguageId());

        $localeEntity = new LocaleEntity();
        $localeEntity->setId(Uuid::randomHex());
        $localeEntity->setCode($localeCode);
        $languageEntity->setLocale($localeEntity);

        return $languageEntity;
    }
}
