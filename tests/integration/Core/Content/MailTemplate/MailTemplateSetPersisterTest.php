<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\MailTemplate;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateTranslation\MailTemplateTranslationCollection;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeCollection;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateSetPersister;
use Shopware\Core\Content\MailTemplate\Xml\MailTemplate;
use Shopware\Core\Content\MailTemplate\Xml\MailTemplates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[Package('after-sales')]
class MailTemplateSetPersisterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const TECHNICAL_NAME = 'test_declarative_template';

    private MailTemplateSetPersister $persister;

    /**
     * @var EntityRepository<MailTemplateTypeCollection>
     */
    private EntityRepository $typeRepository;

    /**
     * @var EntityRepository<MailTemplateCollection>
     */
    private EntityRepository $templateRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->persister = static::getContainer()->get(MailTemplateSetPersister::class);
        $this->typeRepository = static::getContainer()->get('mail_template_type.repository');
        $this->templateRepository = static::getContainer()->get('mail_template.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testSyncCreatesTypeTemplateAndTranslations(): void
    {
        $this->persister->sync($this->mailTemplates(), $this->context);

        $type = $this->loadType();
        static::assertSame(['order' => 'order'], $type->getAvailableEntities());
        static::assertSame('Order confirmation', $type->getTranslation('name'));

        $template = $this->loadTemplate();
        static::assertTrue($template->getSystemDefault());
        static::assertFalse($template->wasModifiedByUser());
        static::assertSame('Your order', $template->getTranslation('subject'));
        static::assertSame('<p>Thank you</p>', $template->getTranslation('contentHtml'));
        static::assertSame('Thank you', $template->getTranslation('contentPlain'));
        static::assertSame('Shop', $template->getTranslation('senderName'));
    }

    public function testSyncIsIdempotentAndUpdatesContent(): void
    {
        $this->persister->sync($this->mailTemplates(), $this->context);
        $templateId = $this->loadTemplate()->getId();

        $changed = $this->mailTemplates(subjectEn: 'Updated subject', contentHtmlEn: '<p>Updated</p>');
        $this->persister->sync($changed, $this->context);

        $template = $this->loadTemplate();
        // same row is reused, content is refreshed
        static::assertSame($templateId, $template->getId());
        static::assertSame('Updated subject', $template->getTranslation('subject'));
        static::assertSame('<p>Updated</p>', $template->getTranslation('contentHtml'));
    }

    public function testSyncDoesNotOverwriteMerchantEditedTemplate(): void
    {
        $this->persister->sync($this->mailTemplates(), $this->context);
        $templateId = $this->loadTemplate()->getId();

        // simulate a merchant editing the template in the Administration (user scope flips was_modified_by_user)
        $this->context->scope(Context::USER_SCOPE, function (Context $userContext) use ($templateId): void {
            $this->templateRepository->update([[
                'id' => $templateId,
                'subject' => 'Merchant subject',
                'contentHtml' => '<p>Merchant content</p>',
            ]], $userContext);
        });

        static::assertTrue($this->loadTemplate()->wasModifiedByUser());

        // a plugin update tries to push new defaults
        $this->persister->sync(
            $this->mailTemplates(subjectEn: 'Plugin subject', contentHtmlEn: '<p>Plugin content</p>'),
            $this->context
        );

        $template = $this->loadTemplate();
        static::assertSame('Merchant subject', $template->getTranslation('subject'));
        static::assertSame('<p>Merchant content</p>', $template->getTranslation('contentHtml'));
    }

    public function testSyncWritesDefaultLanguageTranslationWhenOnlyOtherLocaleIsShipped(): void
    {
        // a plugin that ships only de-DE content
        $mailTemplate = MailTemplate::fromArray([
            'technicalName' => self::TECHNICAL_NAME,
            'name' => ['de-DE' => 'Benachrichtigung'],
            'subject' => ['de-DE' => 'Betreff'],
            'senderName' => ['de-DE' => 'Shop'],
            'description' => ['de-DE' => 'Beschreibung'],
            'contentHtml' => ['de-DE' => '<p>Hallo</p>'],
            'contentPlain' => ['de-DE' => 'Hallo'],
            'availableEntities' => [],
        ]);

        $this->persister->sync(MailTemplates::fromArray(['mailTemplates' => [$mailTemplate]]), $this->context);

        $template = $this->loadTemplate();

        $translations = $template->getTranslations();
        static::assertInstanceOf(MailTemplateTranslationCollection::class, $translations);
        // a system-default-language (en-GB) translation must exist alongside the shipped de-DE one
        static::assertCount(2, $translations);

        // the default-language translation falls back to the only provided locale's content
        static::assertSame('Betreff', $template->getTranslation('subject'));
        static::assertSame('<p>Hallo</p>', $template->getTranslation('contentHtml'));

        // the type name is also present in the default language
        static::assertSame('Benachrichtigung', $this->loadType()->getTranslation('name'));
    }

    public function testRemoveByTechnicalNamesDeletesTypeAndTemplate(): void
    {
        $this->persister->sync($this->mailTemplates(), $this->context);
        static::assertNotNull($this->findType());

        $this->persister->removeByTechnicalNames([self::TECHNICAL_NAME], $this->context);

        static::assertNull($this->findType());
        static::assertNull($this->findTemplate());
    }

    private function mailTemplates(string $subjectEn = 'Your order', string $contentHtmlEn = '<p>Thank you</p>'): MailTemplates
    {
        $mailTemplate = MailTemplate::fromArray([
            'technicalName' => self::TECHNICAL_NAME,
            'name' => ['en-GB' => 'Order confirmation', 'de-DE' => 'Bestellbestätigung'],
            'subject' => ['en-GB' => $subjectEn, 'de-DE' => 'Deine Bestellung'],
            'senderName' => ['en-GB' => 'Shop'],
            'description' => ['en-GB' => 'Sent after order placement'],
            'contentHtml' => ['en-GB' => $contentHtmlEn],
            'contentPlain' => ['en-GB' => 'Thank you'],
            'availableEntities' => ['order' => 'order'],
        ]);

        return MailTemplates::fromArray(['mailTemplates' => [$mailTemplate]]);
    }

    private function loadType(): MailTemplateTypeEntity
    {
        $type = $this->findType();
        static::assertInstanceOf(MailTemplateTypeEntity::class, $type);

        return $type;
    }

    private function findType(): ?MailTemplateTypeEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', self::TECHNICAL_NAME));

        return $this->typeRepository->search($criteria, $this->context)->getEntities()->first();
    }

    private function loadTemplate(): MailTemplateEntity
    {
        $template = $this->findTemplate();
        static::assertInstanceOf(MailTemplateEntity::class, $template);

        return $template;
    }

    private function findTemplate(): ?MailTemplateEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mailTemplateType.technicalName', self::TECHNICAL_NAME));
        $criteria->addAssociation('translations');

        return $this->templateRepository->search($criteria, $this->context)->getEntities()->first();
    }
}
