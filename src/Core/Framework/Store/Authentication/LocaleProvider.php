<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Authentication;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\User\UserDefinition;

class LocaleProvider
{
    private EntityRepositoryInterface $userRepository;

    public function __construct(EntityRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function getLocaleFromContext(Context $context): string
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            return 'en-GB';
        }

        /** @var AdminApiSource $source */
        $source = $context->getSource();

        if ($source->getUserId() === null) {
            return 'en-GB';
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
