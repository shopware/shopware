<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\User\UserDefinition;

class AppLocaleProvider
{
    private Connection $connection;

    private EntityRepositoryInterface $userRepository;

    public function __construct(Connection $connection, EntityRepositoryInterface $userRepository)
    {
        $this->connection = $connection;
        $this->userRepository = $userRepository;
    }

    public function getLocaleFromContext(Context $context): string
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            return $this->getLocaleCode($context->getLanguageId());
        }

        /** @var AdminApiSource $source */
        $source = $context->getSource();

        if ($source->getUserId() === null) {
            return $this->getLocaleCode($context->getLanguageId());
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

    private function getLocaleCode(string $languageId): string
    {
        $sql = 'SELECT locale.code FROM language INNER JOIN locale ON(locale.id = language.locale_id) WHERE language.id = ?';

        $localeCode = $this->connection->prepare($sql);
        $localeCode->execute([Uuid::fromHexToBytes($languageId)]);
        $localeCode = $localeCode->fetchColumn();

        if (!$localeCode) {
            throw new \RuntimeException('Could not resolve language ' . $languageId);
        }

        return (string) $localeCode;
    }
}
