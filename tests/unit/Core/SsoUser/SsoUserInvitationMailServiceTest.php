<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\SsoUser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Login\Config\LoginConfigService;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeCollection;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateDefinition;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\SsoUser\SsoUserInvitationMailService;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleCollection;
use Shopware\Core\System\Locale\LocaleDefinition;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\System\User\UserEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SsoUserInvitationMailService::class)]
class SsoUserInvitationMailServiceTest extends TestCase
{
    public function testSendInvitationMailToUser(): void
    {
        $abstractMailService = $this->createMock(AbstractMailService::class);
        $abstractMailService->expects($this->once())->method('send');

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->exactly(2))->method('get')
            ->willReturnOnConsecutiveCalls('ShopName', 'sender@name.foo');

        $loginConfigService = new LoginConfigService(
            [
                'use_default' => false,
                'client_id' => 'c6a7ab8a-5c0c-4353-a38a-1b42479ef090',
                'client_secret' => '42fec3f9-a19b-4796-bce9-cb395a28da9f',
                'redirect_uri' => 'https://redirect.to',
                'base_url' => 'https://base.url',
                'authorize_path' => '/authorize',
                'token_path' => '/token',
                'jwks_path' => '/jwks.json',
                'scope' => 'scope',
                'register_url' => 'https://register.url',
            ],
            'local.host',
            '/admin'
        );

        $mailTemplateEntity = new MailTemplateEntity();
        $mailTemplateEntity->setUniqueIdentifier(Uuid::randomHex());
        $mailTemplateEntity->setId(Uuid::randomHex());
        /** @var StaticEntityRepository<MailTemplateCollection> $mailTemplateRepository */
        $mailTemplateRepository = new StaticEntityRepository([
            new MailTemplateCollection([$mailTemplateEntity]),
        ], new MailTemplateDefinition());

        $mailTemplateTypeEntity = new MailTemplateTypeEntity();
        $mailTemplateTypeEntity->setUniqueIdentifier(Uuid::randomHex());
        $mailTemplateTypeEntity->setId(Uuid::randomHex());
        /** @var StaticEntityRepository<MailTemplateTypeCollection> $mailTemplateTypeRepository */
        $mailTemplateTypeRepository = new StaticEntityRepository([
            new MailTemplateTypeCollection([$mailTemplateTypeEntity]),
        ], new MailTemplateDefinition());

        $userEntity = new UserEntity();
        $userEntity->setUniqueIdentifier(Uuid::randomHex());
        $userEntity->setEmail('test@example.foo');
        $userEntity->setFirstName('FirstName');
        $userEntity->setLastName('LastName');
        $userEntity->setUsername('UserName');
        /** @var StaticEntityRepository<UserCollection> $userRepository */
        $userRepository = new StaticEntityRepository([
            new UserCollection([$userEntity]),
        ], new UserDefinition());

        $languageEntity = new LanguageEntity();
        $languageEntity->setUniqueIdentifier(Uuid::randomHex());
        $languageEntity->setId(Uuid::randomHex());
        /** @var StaticEntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = new StaticEntityRepository([
            new LanguageCollection([$languageEntity]),
        ], new LanguageDefinition());

        $localeEntity = new LocaleEntity();
        $localeEntity->setUniqueIdentifier(Uuid::randomHex());
        $localeEntity->setCode('de-DE');
        /** @var StaticEntityRepository<LocaleCollection> $localeRepository */
        $localeRepository = new StaticEntityRepository([
            new LocaleCollection([$localeEntity]),
        ], new LocaleDefinition());

        $ssoUserInvitationMailService = new SsoUserInvitationMailService(
            $abstractMailService,
            $systemConfigService,
            $loginConfigService,
            $mailTemplateRepository,
            $mailTemplateTypeRepository,
            $userRepository,
            $languageRepository,
            $localeRepository
        );

        $context = Context::createDefaultContext(new AdminApiSource(Uuid::randomHex(), null));

        $ssoUserInvitationMailService->sendInvitationMailToUser('test@test.com', Uuid::randomHex(), $context);
    }
}
