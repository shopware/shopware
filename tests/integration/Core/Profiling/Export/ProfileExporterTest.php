<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Profiling\Export;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Profiling\Export\CollectorDataConverter;
use Shopware\Core\Profiling\Export\ProfileExporter;
use Shopware\Core\Profiling\Export\SecretRedactor;
use Shopware\Core\Profiling\Export\Summarizer\ExceptionSummarizer;
use Shopware\Core\Profiling\Export\Summarizer\LoggerSummarizer;
use Shopware\Core\Profiling\Export\Summarizer\RequestSummarizer;
use Shopware\Core\Profiling\Export\Summarizer\TimeSummarizer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;

/**
 * @internal
 */
#[Package('framework')]
class ProfileExporterTest extends TestCase
{
    use KernelTestBehaviour;

    public function testExportsAStoredProfileWithoutCredentials(): void
    {
        $accessToken = bin2hex(random_bytes(24));
        $contextToken = bin2hex(random_bytes(16));
        $request = Request::create('/api/_info/version', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $accessToken,
            'HTTP_SW_CONTEXT_TOKEN' => $contextToken,
        ]);
        $request->attributes->set('_route', 'api.info.shopware.version');

        $profiler = static::getContainer()->get('profiler');
        static::assertInstanceOf(Profiler::class, $profiler);

        $profiler->enable();
        try {
            $collected = $profiler->collect($request, new JsonResponse(['version' => '6.7']));
            static::assertInstanceOf(Profile::class, $collected);
            static::assertTrue($profiler->saveProfile($collected));
        } finally {
            $profiler->disable();
        }

        $converter = new CollectorDataConverter();
        $exporter = new ProfileExporter($profiler, $converter, new SecretRedactor(), [
            new RequestSummarizer($converter),
            new TimeSummarizer(),
            new ExceptionSummarizer(),
            new LoggerSummarizer(),
        ]);

        $profile = $exporter->load($collected->getToken());
        static::assertInstanceOf(Profile::class, $profile);
        static::assertNotSame($collected, $profile);

        $summary = $exporter->export($profile);
        $full = $exporter->export($profile, full: true);
        $unredacted = $exporter->export($profile, ['request'], full: true, redact: false);

        static::assertSame($collected->getToken(), $summary['token']);
        static::assertSame('GET', $summary['panels']['request']['method']);
        static::assertSame('/api/_info/version', $summary['panels']['request']['path']);
        static::assertSame('api.info.shopware.version', $summary['panels']['request']['route']);
        static::assertSame(200, $summary['panels']['request']['status']);
        static::assertSame('[redacted]', $summary['panels']['request']['requestHeaders']['authorization']);
        static::assertSame('[redacted]', $summary['panels']['request']['requestHeaders']['sw-context-token']);
        static::assertArrayHasKey('durationMs', $summary['panels']['time']);
        static::assertNull($summary['panels']['exception']);
        static::assertArrayNotHasKey('db', $summary['panels']);
        static::assertSame(array_values($exporter->panels($profile)), array_keys($full['panels']));

        foreach ([$summary, $full] as $export) {
            $json = json_encode($export, \JSON_THROW_ON_ERROR);
            static::assertStringNotContainsString($accessToken, $json);
            static::assertStringNotContainsString($contextToken, $json);
        }

        $json = json_encode($unredacted, \JSON_THROW_ON_ERROR);
        static::assertStringContainsString($accessToken, $json);
        static::assertStringContainsString($contextToken, $json);
    }
}
