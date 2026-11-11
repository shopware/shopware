<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @phpstan-type Listener = array{event: string, callback: string, parameter: string, file: string, priority: int, order: int}
 *
 * @internal
 */
#[Package('framework')]
final class RenderEventListeners
{
    /**
     * @return list<Listener>
     */
    public static function discover(string $containerXml): array
    {
        $xml = simplexml_load_string((new Filesystem())->readFile($containerXml), options: \LIBXML_NONET);
        if ($xml === false) {
            throw new \RuntimeException('Cannot read render listeners from the analysis container.');
        }

        $services = [];
        foreach ($xml->services->service as $service) {
            $services[(string) $service['id']] = $service;
        }
        $dispatcher = 'event_dispatcher';
        $visited = [];
        while (isset($services[$dispatcher]['alias'])) {
            if (isset($visited[$dispatcher])) {
                throw new \RuntimeException('Circular event dispatcher alias.');
            }
            $visited[$dispatcher] = true;
            $dispatcher = (string) $services[$dispatcher]['alias'];
        }
        if (!isset($services[$dispatcher])) {
            throw new \RuntimeException('The analysis container has no event dispatcher.');
        }

        $listeners = [];
        foreach ($services[$dispatcher]->call as $call) {
            if ((string) $call['method'] !== 'addListener') {
                continue;
            }
            $event = (string) $call->argument[0];
            if ($event !== StorefrontRenderEvent::class && !str_ends_with($event, '.render')) {
                continue;
            }
            $serviceId = (string) $call->argument[1]->argument[0]['id'];
            $visited = [];
            while (isset($services[$serviceId]['alias']) && !isset($visited[$serviceId])) {
                $visited[$serviceId] = true;
                $serviceId = (string) $services[$serviceId]['alias'];
            }
            $class = (string) ($services[$serviceId]['class'] ?? '');
            $method = (string) $call->argument[1]->argument[1];
            if ($class === '' || $method === '') {
                throw new \RuntimeException('Cannot resolve render listener ' . $serviceId . '.');
            }
            $reflection = new \ReflectionMethod($class, $method);
            $file = $reflection->getFileName();
            $parameter = $reflection->getParameters()[0] ?? null;
            if ($file === false || $parameter === null) {
                throw new \RuntimeException('Cannot analyze render listener ' . $class . '::' . $method . '.');
            }
            $listeners[] = [
                'event' => $event,
                'callback' => $reflection->getDeclaringClass()->getName() . '::' . $method,
                'parameter' => $parameter->getName(),
                'file' => $file,
                'priority' => (int) $call->argument[2],
                'order' => \count($listeners),
            ];
        }

        return $listeners;
    }
}
