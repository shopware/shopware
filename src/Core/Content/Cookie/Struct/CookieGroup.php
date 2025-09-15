<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Struct;

use Shopware\Core\Content\Cookie\CookieException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * A group can be also be a standalone cookie.
 * If a group is a cookie itself (the "cookie" property is set), it is not allowed to have "entries", and vice versa.
 * It would lead to unexpected UI behavior otherwise.
 *
 * TechnicalName should be a snippet key, @see \Shopware\Core\Content\Cookie\Service\CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED for an example.
 * TechnicalName is also used as name and will be translated. Description can be provided as snippet keys or directly translated text.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class CookieGroup extends Struct
{
    public bool $isRequired = false;

    public ?string $description;

    public ?string $value;

    public ?int $expiration;

    public string $name;

    protected ?string $cookie;

    protected ?CookieEntryCollection $entries;

    public function __construct(
        private readonly string $technicalName,
    ) {
        $this->name = $technicalName;
    }

    public function getApiAlias(): string
    {
        return 'cookie_group';
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function getCookie(): ?string
    {
        return $this->cookie ?? null;
    }

    public function setCookie(?string $cookie): void
    {
        if (isset($this->entries)) {
            throw CookieException::notAllowedPropertyAssignment('cookie', 'entries');
        }
        $this->cookie = $cookie;
    }

    public function getEntries(): ?CookieEntryCollection
    {
        return $this->entries ?? null;
    }

    public function setEntries(?CookieEntryCollection $entries): void
    {
        if (isset($this->cookie)) {
            throw CookieException::notAllowedPropertyAssignment('entries', 'cookie');
        }
        $this->entries = $entries;
    }
}
