<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SalesChannel\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('discovery')]
class AgeVerificationStruct extends Struct
{
    protected int $minimumAge = 18;

    protected ?string $title = null;

    protected ?string $content = null;

    protected ?string $confirmButtonText = null;

    protected ?string $declineButtonText = null;

    protected ?string $declineUrl = null;

    protected int $cookieLifetime = 30;

    public function getMinimumAge(): int
    {
        return $this->minimumAge;
    }

    public function setMinimumAge(int $minimumAge): void
    {
        $this->minimumAge = $minimumAge;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): void
    {
        $this->content = $content;
    }

    public function getConfirmButtonText(): ?string
    {
        return $this->confirmButtonText;
    }

    public function setConfirmButtonText(?string $confirmButtonText): void
    {
        $this->confirmButtonText = $confirmButtonText;
    }

    public function getDeclineButtonText(): ?string
    {
        return $this->declineButtonText;
    }

    public function setDeclineButtonText(?string $declineButtonText): void
    {
        $this->declineButtonText = $declineButtonText;
    }

    public function getDeclineUrl(): ?string
    {
        return $this->declineUrl;
    }

    public function setDeclineUrl(?string $declineUrl): void
    {
        $this->declineUrl = $declineUrl;
    }

    public function getCookieLifetime(): int
    {
        return $this->cookieLifetime;
    }

    public function setCookieLifetime(int $cookieLifetime): void
    {
        $this->cookieLifetime = $cookieLifetime;
    }

    public function getApiAlias(): string
    {
        return 'cms_age_verification';
    }
}
