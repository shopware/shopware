<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * Builds the context for every script service call, so a context changed by a script is never reused.
 *
 * @internal
 */
#[Package('framework')]
final readonly class AppContextFactory
{
    /**
     * @var array<string>
     */
    private array $ruleIds;

    /**
     * @var non-empty-list<string>
     */
    private array $languageIdChain;

    private string $currencyId;

    private string $versionId;

    private float $currencyFactor;

    private bool $considerInheritance;

    private string $taxState;

    private CashRoundingConfig $rounding;

    /**
     * @param list<string>|null $privileges null for scripts without an app, which run with the hook context itself
     */
    private function __construct(
        private Context $hookContext,
        private ?string $integrationId,
        private ?array $privileges,
    ) {
        // Snapshot once, so later changes to the hook context do not reach app contexts.
        $this->ruleIds = $hookContext->getRuleIds();
        $this->currencyId = $hookContext->getCurrencyId();
        $this->languageIdChain = $hookContext->getLanguageIdChain();
        $this->versionId = $hookContext->getVersionId();
        $this->currencyFactor = $hookContext->getCurrencyFactor();
        $this->considerInheritance = $hookContext->considerInheritance();
        $this->taxState = $hookContext->getTaxState();
        $this->rounding = clone $hookContext->getRounding();
    }

    public static function forHookContext(Context $hookContext): self
    {
        return new self($hookContext, null, null);
    }

    /**
     * @param list<string> $privileges
     */
    public static function forApp(Context $hookContext, string $integrationId, array $privileges): self
    {
        return new self($hookContext, $integrationId, $privileges);
    }

    public function create(): Context
    {
        if ($this->privileges === null) {
            return $this->hookContext;
        }

        $source = new AdminApiSource(null, $this->integrationId);
        $source->setIsAdmin(false);
        $source->setPermissions($this->privileges);

        return new Context(
            $source,
            $this->ruleIds,
            $this->currencyId,
            $this->languageIdChain,
            $this->versionId,
            $this->currencyFactor,
            $this->considerInheritance,
            $this->taxState,
            clone $this->rounding
        );
    }
}
