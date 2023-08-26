<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener;

use Psr\Container\ContainerInterface;
use ReflectionClass;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('core')]
class UuidReplacerSystaxListener implements EventSubscriberInterface
{
    private const REPLACER_PATTER = <<<'REGEX'
/(?<replace>\[%(?<entity>.+)\((?<query>.+)\)%\])/
REGEX;

    public function __construct(
        #[Autowire(service: 'service_container')]
        private ContainerInterface $container
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                'onRequest',
                KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_CONTEXT_RESOLVE_POST
            ],
        ];
    }

    public function onRequest(ControllerEvent $controllerEvent): void
    {
        $request = $controllerEvent->getRequest();
        $content = $request->getContent();
        $context = $request->attributes->get('sw-context');
        $count = preg_match_all(self::REPLACER_PATTER, $content, $matches);

        if ($count > 0) {
            $replaces = $matches['replace'];
            $entities = $matches['entity'];
            $queries = $matches['query'];
            foreach ($entities as $index => $entity) {
                $repository = $this->container->get(sprintf('%s.repository', $entity));
                if (!$repository instanceof EntityRepository) {
                    throw new BadRequestHttpException(sprintf('Api resolver could not resolve entity "%s"', $entity));
                }

                $queryJson = '{' . str_replace('\'', '"', $queries[$index]) . '}';
                $queryData = json_decode($queryJson, false, 512, JSON_THROW_ON_ERROR);
                $filters = [];
                foreach ($queryData as $field => $value) {
                    $filters[] = new EqualsFilter($field, $value);
                }
                $criteria = (new Criteria())->addFilter(new AndFilter($filters));
                $results = $repository->search($criteria, $context);

                if ($results->count() === 0) {
                    throw new BadRequestHttpException(sprintf('Could not find result for "%s"', $queryJson));
                }
                if ($results->count() > 1) {
                    throw new BadRequestHttpException(sprintf('Result was not unique for "%s"', $queryJson));
                }

                $id = $results->first()->getId();
                $replace = $replaces[$index];
                $content = str_replace($replace, $id, $content);
            }

            $reflectionClass = new ReflectionClass($request);
            $reflectionProperty = $reflectionClass->getProperty('content');
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($request, $content);
        }
    }
}
