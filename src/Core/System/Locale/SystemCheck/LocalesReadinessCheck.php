<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale\SystemCheck;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\BaseCheck;
use Shopware\Core\Framework\SystemCheck\Check\Category;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopware\Core\System\Locale\LocaleCollection;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\Locale\Util\LocaleHelper;

/**
 * @internal
 */
#[Package('discovery')]
class LocalesReadinessCheck extends BaseCheck
{
    /**
     * @param EntityRepository<LocaleCollection> $localeRepository
     */
    public function __construct(private readonly EntityRepository $localeRepository)
    {
    }

    public function run(): Result
    {
        $locales = $this->localeRepository
            ->search(new Criteria(), Context::createDefaultContext())
            ->map(static fn (LocaleEntity $locale) => $locale->getCode());

        $invalidLocales = array_filter(
            $locales,
            static fn (string $locale) => !LocaleHelper::isLocale($locale)
        );

        $status = \count($invalidLocales) === 0 ? Status::OK : Status::WARNING;

        return new Result(
            $this->name(),
            $status,
            $status === Status::OK ? 'All locales are OK' : 'Some locales are invalid',
            $status === Status::OK,
            $invalidLocales
        );
    }

    public function category(): Category
    {
        return Category::SYSTEM;
    }

    public function name(): string
    {
        return 'LocalesReadiness';
    }

    protected function allowedSystemCheckExecutionContexts(): array
    {
        return SystemCheckExecutionContext::longRunning();
    }
}
