<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\User\UserDefinition;

#[Package('core')]
class AppLocaleProvider
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $userRepository,
        private readonly LanguageLocaleCodeProvider $languageLocaleProvider
    ) {
    }

    public function getLocaleFromContext(Context $context): string
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            return $this->languageLocaleProvider->getLocaleForLanguageId($context->getLanguageId());
        }

        /** @var AdminApiSource $source */
        $source = $context->getSource();

        if ($source->getUserId() === null) {
            return $this->languageLocaleProvider->getLocaleForLanguageId($context->getLanguageId());
        }

        $criteria = new Criteria([$source->getUserId()]);
        $criteria->addAssociation('locale');

        $user = $this->userRepository->search($criteria, $context)->first();

        if ($user === null) {
            throw new EntityNotFoundException(UserDefinition::ENTITY_NAME, $source->getUserId());
        }

        /** @var LocaleEntity $locale */
        $locale = $user->getLocale();

        return $locale->getCode();
    }
}
