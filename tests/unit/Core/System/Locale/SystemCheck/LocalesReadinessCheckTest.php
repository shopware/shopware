<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Locale\SystemCheck;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Category;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopware\Core\System\Locale\LocaleCollection;
use Shopware\Core\System\Locale\LocaleDefinition;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\Locale\SystemCheck\LocalesReadinessCheck;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(LocalesReadinessCheck::class)]
class LocalesReadinessCheckTest extends TestCase
{
    public function testItChecksLocales(): void
    {
        /** @var StaticEntityRepository<LocaleCollection> $localeRepository */
        $localeRepository = new StaticEntityRepository(
            [
                new LocaleCollection([
                    (new LocaleEntity())->assign([
                        'id' => 'locale-1',
                        'code' => 'de-DE',
                    ]),
                    (new LocaleEntity())->assign([
                        'id' => 'locale-2',
                        'code' => 'foo-BAR',
                    ]),
                ]),
            ],
            new LocaleDefinition()
        );

        $localesReadninessCheck = new LocalesReadinessCheck($localeRepository);

        static::assertSame(Category::SYSTEM, $localesReadninessCheck->category());
        static::assertTrue($localesReadninessCheck->allowedToRunIn(SystemCheckExecutionContext::CLI));
        $result = $localesReadninessCheck->run();
        static::assertSame(Status::WARNING, $result->status);
        static::assertSame('Some locales are invalid', $result->message);
        static::assertFalse($result->healthy);
        static::assertSame(['locale-2' => 'foo-BAR'], $result->extra);
    }
}
