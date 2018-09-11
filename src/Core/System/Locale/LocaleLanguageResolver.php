<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
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
        $query = $this->connection->prepare('
              SELECT locale.code, LOWER(HEX(language.id)) FROM language 
                  LEFT JOIN locale ON language.locale_id = locale.id 
                    AND language.tenant_id = locale.tenant_id 
                  WHERE  language.tenant_id = :tenant_id
                    AND locale.version_id = :version_id
            '
        );
        $query->execute([
            'tenant_id' => Uuid::fromHexToBytes($context->getTenantId()),
            'version_id' => Uuid::fromHexToBytes($context->getVersionId()),
        ]);

        return $this->mapping = $query->fetchAll(\PDO::FETCH_KEY_PAIR);
    }
}
