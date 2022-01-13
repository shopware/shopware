<?php declare(strict_types=1);

namespace Shopware\Docs\Command\Script;

use League\ConstructFinder\ConstructFinder;
use phpDocumentor\Reflection\DocBlock\Tags\Generic;
use phpDocumentor\Reflection\DocBlockFactory;
use Shopware\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Shopware\Core\Framework\Script\Execution\Hook;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
class HooksReferenceGenerator implements ScriptReferenceGenerator
{
    public const USE_CASE_DATA_LOADING = 'data_loading';
    public const USE_CASE_CART_MANIPULATION = 'cart_manipulation';

    public const ALLOWED_USE_CASES = [
        self::USE_CASE_CART_MANIPULATION,
        self::USE_CASE_DATA_LOADING,
    ];

    private const TEMPLATE_FILE = __DIR__ . '/../../Resources/templates/Scripts/hook-reference.md.twig';
    private const GENERATED_DOC_FILE = __DIR__ . '/../../Resources/current/47-app-system-guide/script-hooks-reference.md';

    private ContainerInterface $container;

    private DocBlockFactory $docFactory;

    private ServiceReferenceGenerator $serviceReferenceGenerator;

    /**
     * @psalm-suppress ContainerDependency
     */
    public function __construct(ContainerInterface $container, ServiceReferenceGenerator $serviceReferenceGenerator)
    {
        $this->container = $container;
        $this->docFactory = DocBlockFactory::createInstance([
            'hook-use-case' => Generic::class,
            'script-service' => Generic::class,
        ]);
        $this->serviceReferenceGenerator = $serviceReferenceGenerator;
    }

    public function generate(): array
    {
        $hookClassNames = $this->getHookClasses();

        $data = $this->getHookData($hookClassNames);

        /** @var Environment $twig */
        $twig = $this->container->get('twig');
        $originalLoader = $twig->getLoader();

        $twig->setLoader(new ArrayLoader([
            'hook-reference.md.twig' => file_get_contents(self::TEMPLATE_FILE),
        ]));

        try {
            $result = [
                self::GENERATED_DOC_FILE => $twig->render('hook-reference.md.twig', ['data' => $data]),
            ];
        } finally {
            $twig->setLoader($originalLoader);
        }

        return $result;
    }

    private function getHookClasses(): array
    {
        $hookClasses = [];

        $shopwareClasses = ConstructFinder::locatedIn(__DIR__ . '/../../..')
            ->exclude('*/Test/*', '*/vendor/*')
            ->findClassNames();

        foreach ($shopwareClasses as $class) {
            if (!class_exists($class)) {
                // skip not autoloadable test classes
                continue;
            }

            if (is_subclass_of($class, Hook::class) && !(new \ReflectionClass($class))->isAbstract()) {
                $hookClasses[] = $class;
            }
        }

        if (\count($hookClasses) === 0) {
            throw new \RuntimeException('No HookClasses found.');
        }

        sort($hookClasses);

        return $hookClasses;
    }

    private function getHookData(array $hookClassNames): array
    {
        $data = [
            self::USE_CASE_DATA_LOADING => [
                'title' => 'Data Loading',
                'description' => 'All available Hooks that can be used to load additional data.',
                'hooks' => [],
            ],
            self::USE_CASE_CART_MANIPULATION => [
                'title' => 'Cart Manipulation',
                'description' => 'All available Hooks that can be used to manipulate the cart.',
                'hooks' => [],
            ],
        ];

        foreach ($hookClassNames as $hook) {
            /** @var \ReflectionClass<Hook> $reflection */
            $reflection = new \ReflectionClass($hook);

            if (!$reflection->getDocComment()) {
                throw new \RuntimeException(sprintf('PhpDoc comment is missing on concrete HookClass `%s', $hook));
            }
            $docBlock = $this->docFactory->create($reflection);

            /** @var Generic[] $tags */
            $tags = $docBlock->getTagsByName('hook-use-case');
            if (\count($tags) !== 1 || !($description = $tags[0]->getDescription()) || !\in_array($description->render(), self::ALLOWED_USE_CASES, true)) {
                throw new \RuntimeException(sprintf(
                    'Hook use case description is missing for hook "%s". All HookClasses need to be tagged with the `@hook-use-case` tag and associated to one of the following use cases: "%s".',
                    $hook,
                    implode('", "', self::ALLOWED_USE_CASES),
                ));
            }

            $data[$description->render()]['hooks'][] = [
                'name' => $hook::HOOK_NAME,
                'class' => $hook,
                'trigger' => $docBlock->getSummary() . '<br>' . $docBlock->getDescription()->render(),
                'data' => $this->getAvailableData($reflection),
                'services' => $this->getAvailableServices($reflection),
            ];
        }

        return $data;
    }

    /**
     * @param \ReflectionClass<Hook> $reflection
     */
    private function getAvailableData(\ReflectionClass $reflection): array
    {
        $availableData = [];

        foreach ($reflection->getProperties() as $property) {
            $propertyType = $property->getType();
            if (!$propertyType instanceof \ReflectionNamedType) {
                throw new \RuntimeException(sprintf(
                    'Property "%s" in HookClass "%s" is not typed.',
                    $property->getName(),
                    $reflection->getName()
                ));
            }

            /** @var class-string<object> $type */
            $type = $propertyType->getName();

            $availableData[] = [
                'name' => $property->getName(),
                'type' => $type,
                'link' => $this->serviceReferenceGenerator->getLinkForClass($type),
            ];
        }

        return $availableData;
    }

    /**
     * @param \ReflectionClass<Hook> $reflection
     */
    private function getAvailableServices(\ReflectionClass $reflection): array
    {
        $serviceIds = $reflection->getMethod('getServiceIds')->invoke(null);
        $services = [];

        foreach ($serviceIds as $serviceId) {
            $reflection = new \ReflectionClass($serviceId);
            $method = $reflection->getMethod('factory');
            /** @var \ReflectionNamedType|null $returnType */
            $returnType = $method->getReturnType();
            if ($returnType === null) {
                throw new \RuntimeException(sprintf(
                    '`factory()` method in HookServiceFactory "%s" has no return type.',
                    $reflection->getName()
                ));
            }

            /** @var HookServiceFactory $service */
            $service = $this->container->get($serviceId);
            $name = $service->getName();

            /** @var class-string<object> $type */
            $type = $returnType->getName();

            $services[] = [
                'name' => $name,
                'returnType' => $type,
                'link' => $this->getServiceLink($type),
            ];
        }

        return $services;
    }

    /**
     * @param class-string<object> $serviceClassName
     */
    private function getServiceLink(string $serviceClassName): string
    {
        $reflection = new \ReflectionClass($serviceClassName);

        $group = $this->serviceReferenceGenerator->getGroupForService($reflection);

        return sprintf('./%s#%s', ServiceReferenceGenerator::GROUPS[$group], $reflection->getShortName());
    }
}
