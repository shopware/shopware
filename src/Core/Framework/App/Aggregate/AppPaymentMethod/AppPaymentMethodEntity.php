<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppPaymentMethod;

use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

/**
 * @internal only for use by the app-system
 */
class AppPaymentMethodEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $appId;

    /**
     * @var AppEntity|null
     */
    protected $app;

    /**
     * @var string
     */
    protected $paymentMethodId;

    /**
     * @var PaymentMethodEntity|null
     */
    protected $paymentMethod;

    /**
     * @var string
     */
    protected $appName;

    /**
     * @var string
     */
    protected $identifier;

    /**
     * @var string|null
     */
    protected $payUrl;

    /**
     * @var string|null
     */
    protected $finalizeUrl;

    /**
     * @var string|null
     */
    protected $originalMediaId;

    /**
     * @var MediaEntity|null
     */
    protected $originalMedia;

    public function getAppId(): string
    {
        return $this->appId;
    }

    public function setAppId(string $appId): void
    {
        $this->appId = $appId;
    }

    public function getApp(): ?AppEntity
    {
        return $this->app;
    }

    public function setApp(?AppEntity $app): void
    {
        $this->app = $app;
    }

    public function getPaymentMethodId(): string
    {
        return $this->paymentMethodId;
    }

    public function setPaymentMethodId(string $paymentMethodId): void
    {
        $this->paymentMethodId = $paymentMethodId;
    }

    public function getPaymentMethod(): ?PaymentMethodEntity
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?PaymentMethodEntity $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }

    public function getAppName(): string
    {
        return $this->appName;
    }

    public function setAppName(string $appName): void
    {
        $this->appName = $appName;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getPayUrl(): ?string
    {
        return $this->payUrl;
    }

    public function setPayUrl(?string $payUrl): void
    {
        $this->payUrl = $payUrl;
    }

    public function getFinalizeUrl(): ?string
    {
        return $this->finalizeUrl;
    }

    public function setFinalizeUrl(?string $finalizeUrl): void
    {
        $this->finalizeUrl = $finalizeUrl;
    }

    public function getOriginalMediaId(): ?string
    {
        return $this->originalMediaId;
    }

    public function setOriginalMediaId(?string $originalMediaId): void
    {
        $this->originalMediaId = $originalMediaId;
    }

    public function getOriginalMedia(): ?MediaEntity
    {
        return $this->originalMedia;
    }

    public function setOriginalMedia(?MediaEntity $originalMedia): void
    {
        $this->originalMedia = $originalMedia;
    }
}
