<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\System\Exception\InvalidLocaleCodeException;

class LocaleLanguageResolver implements LocaleLanguageResolverInterface
{
    /**
     * @var string[]|null
     */
    protected $mapping;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @throws InvalidLocaleCodeException
     */
    public function getLanguageByLocale(string $localeCode, Context $context): ?string
    {
        if ($this->mapping === null) {
            $this->getLanguagesFromDatabase($context);
        }

        if (!isset($this->mapping[$localeCode])) {
            throw new InvalidLocaleCodeException($localeCode);
        }

        return $this->mapping[$localeCode];
    }

    public function invalidate(): void
    {
        $this->mapping = null;
    }

    private function getLanguagesFromDatabase(Context $context): array
    {
        $data = $this->connection->createQueryBuilder()
            ->select(['locale.code', 'LOWER(HEX(language.id)) as language_id'])
            ->from('language')
            ->leftJoin('language', 'locale', 'locale', 'language.locale_id = locale.id AND language.tenant_id = locale.tenant_id')
            ->where('language.tenant_id = :tenantId')
            ->andWhere('locale.version_id = :versionId')
            ->setParameter('tenantId', Uuid::fromHexToBytes($context->getTenantId()))
            ->setParameter('versionId', Uuid::fromHexToBytes($context->getVersionId()))
            ->execute()
            ->fetchAll();

        return $this->mapping = FetchModeHelper::keyPair($data);
    }
}
