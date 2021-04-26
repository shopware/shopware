<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\Framework\Uuid\Uuid;

class CurrencyFormatter
{
    /**
     * @var string[]
     */
    protected $localeCache = [];

    /**
     * @var \NumberFormatter[]
     */
    private $formatter = [];

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws LanguageNotFoundException
     */
    public function formatCurrencyByLanguage(float $price, string $currency, string $languageId, Context $context, ?int $decimals = null): string
    {
        $decimals = $decimals ?? $context->getRounding()->getDecimals();

        $locale = $this->getLocale($languageId);
        $formatter = $this->getFormatter($locale, \NumberFormatter::CURRENCY);
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $decimals);

        if (Feature::isActive('FEATURE_NEXT_15053')) {
            return $formatter->formatCurrency($price, $currency);
        }

        if (!$context->hasState(DocumentService::GENERATING_PDF_STATE)) {
            return $formatter->formatCurrency($price, $currency);
        }

        $string = htmlentities($formatter->formatCurrency($price, $currency), \ENT_COMPAT, 'utf-8');
        $content = str_replace('&nbsp;', ' ', $string);

        return html_entity_decode($content);
    }

    private function getFormatter(string $locale, int $format): \NumberFormatter
    {
        $hash = md5(json_encode([$locale, $format]));

        if (isset($this->formatter[$hash])) {
            return $this->formatter[$hash];
        }

        return $this->formatter[$hash] = new \NumberFormatter($locale, $format);
    }

    private function getLocale(string $languageId): string
    {
        if (\array_key_exists($languageId, $this->localeCache)) {
            return $this->localeCache[$languageId];
        }

        $code = $this->connection->fetchColumn('
            SELECT `locale`.`code`
            FROM `locale`
            INNER JOIN `language` ON `language`.`locale_id` = `locale`.`id`
            WHERE `language`.`id` = :id
        ', ['id' => Uuid::fromHexToBytes($languageId)]);

        if ($code === null) {
            throw new LanguageNotFoundException($languageId);
        }

        return $this->localeCache[$languageId] = $code;
    }
}
