<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\SalesChannel;

use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentAction;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentRecord;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * What a client reports about a cookie consent decision. Everything else is derived on the server.
 */
#[Package('framework')]
final readonly class CookieConsentLogPayload
{
    public const MAX_ACCEPTED_COOKIES = 500;

    public const MAX_COOKIE_NAME_LENGTH = 255;

    /**
     * @param list<string> $acceptedCookies only evaluated for `accept_selected`. An absent list is a valid
     *                                      decision: the visitor may have unticked everything.
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: CookieConsentRecord::CONSENT_ID_PATTERN)]
        public string $consentId,
        public CookieConsentAction $consentAction,
        #[Assert\Type('list')]
        #[Assert\Count(max: self::MAX_ACCEPTED_COOKIES)]
        #[Assert\All([
            new Assert\Type('string'),
            new Assert\NotBlank(),
            new Assert\Length(max: self::MAX_COOKIE_NAME_LENGTH),
        ])]
        public array $acceptedCookies = [],
    ) {
    }
}
