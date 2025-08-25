<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Sso\SsoUser;

use Shopware\Administration\Login\Config\LoginConfig;
use Shopware\Administration\Login\Config\LoginConfigService;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeCollection;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\SsoException;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Locale\LocaleCollection;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\System\User\UserEntity;

/**
 * @internal
 */
#[Package('framework')]
class SsoUserInvitationMailService
{
    /**
     * @param EntityRepository<MailTemplateCollection> $mailTemplateRepository
     * @param EntityRepository<MailTemplateTypeCollection> $mailTemplateTypeRepository
     * @param EntityRepository<UserCollection> $userRepository
     * @param EntityRepository<LanguageCollection> $languageRepository
     * @param EntityRepository<LocaleCollection> $localeRepository
     */
    public function __construct(
        private readonly AbstractMailService $mailService,
        private readonly SystemConfigService $systemConfigService,
        private readonly LoginConfigService $loginConfigService,
        private readonly EntityRepository $mailTemplateRepository,
        private readonly EntityRepository $mailTemplateTypeRepository,
        private readonly EntityRepository $userRepository,
        private readonly EntityRepository $languageRepository,
        private readonly EntityRepository $localeRepository,
    ) {
    }

    public function sendInvitationMailToUser(string $recipientEmail, string $localeId, Context $context): void
    {
        $apiSource = $context->getSource();
        if (!$apiSource instanceof AdminApiSource) {
            return;
        }

        $user = $this->getUserById($apiSource->getUserId(), $context);
        $shopName = $this->systemConfigService->get('core.basicInformation.shopName');
        $senderMail = $this->systemConfigService->get('core.basicInformation.email');
        $mailTemplate = $this->getMailTemplate($localeId, $context);

        $mailData = new DataBag();
        $mailData->set('templateId', $mailTemplate?->getId());
        $mailData->set('recipients', [$recipientEmail => $recipientEmail]);
        $mailData->set('senderName', $shopName);
        $mailData->set('senderEmail', $user?->getEmail() ?? $senderMail);
        $mailData->set('subject', $mailTemplate?->getTranslation('subject'));
        $mailData->set('contentPlain', $mailTemplate?->getTranslation('contentPlain'));
        $mailData->set('contentHtml', $mailTemplate?->getTranslation('contentHtml'));

        $templateVariables = new DataBag();
        $templateVariables->set('nameOfInviter', $this->createInviterName($user));
        $templateVariables->set('storeName', $shopName);
        $templateVariables->set('invitedEmailAddress', $recipientEmail);
        $templateVariables->set('signupUrl', $this->createSignupUrl($recipientEmail, $localeId, $context));

        $this->mailService->send($mailData->all(), $context, $templateVariables->all());
    }

    private function createSignupUrl(string $recipientEmail, string $localeId, Context $context): string
    {
        $locale = $this->localeRepository->search(new Criteria([$localeId]), $context)->first();

        $loginConfig = $this->loginConfigService->getConfig();
        if (!$loginConfig instanceof LoginConfig) {
            throw SsoException::noLoginConfig();
        }

        $lang = $locale?->getCode() === 'de-DE' ? 'de' : 'en';

        return $loginConfig->registerUrl . '?email=' . $recipientEmail . '&language=' . $lang;
    }

    private function getMailTemplate(string $localeId, Context $context): ?MailTemplateEntity
    {
        $languageId = $this->getLanguageIdForLocale($localeId, $context);
        if ($languageId) {
            $newContext = new Context(
                $context->getSource(),
                $context->getRuleIds(),
                $context->getCurrencyId(),
                [$languageId],
                $context->getVersionId(),
                $context->getCurrencyFactor(),
                $context->considerInheritance(),
                $context->getTaxState(),
                $context->getRounding()
            );
        } else {
            $newContext = $context;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', 'admin_sso_user_invite'));

        $result = $this->mailTemplateTypeRepository->search($criteria, $newContext)->first();
        if (!$result instanceof MailTemplateTypeEntity) {
            throw SsoException::mailTemplateNotFound();
        }

        $criteria = new Criteria();
        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_AND,
                [
                    new EqualsFilter('mailTemplateTypeId', $result->getId()),
                    new EqualsFilter('systemDefault', true),
                ]
            )
        );

        return $this->mailTemplateRepository->search($criteria, $newContext)->first();
    }

    private function createInviterName(?UserEntity $user): string
    {
        $firstName = $user?->getFirstName();
        $lastName = $user?->getLastName();
        $userName = $user?->getUsername();

        if (!empty($firstName) && !empty($lastName)) {
            return $firstName . ' ' . $lastName;
        }

        if (!empty($userName)) {
            return $userName;
        }

        return 'Administrator';
    }

    private function getUserById(?string $userId, Context $context): ?UserEntity
    {
        if ($userId === null) {
            return null;
        }

        return $this->userRepository->search(new Criteria([$userId]), $context)->first();
    }

    private function getLanguageIdForLocale(string $localeId, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('localeId', $localeId));

        return $this->languageRepository->search($criteria, $context)->first()?->getId();
    }
}
