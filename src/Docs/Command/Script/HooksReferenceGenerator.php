<?php declare(strict_types=1);

namespace Shopware\Docs\Command\Script;

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

    /**
     * @psalm-suppress ContainerDependency
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->docFactory = DocBlockFactory::createInstance([
            'hook-use-case' => Generic::class,
        ]);
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

        $classLoader = require __DIR__ . '/../../../../vendor/autoload.php';
        foreach ($classLoader->getClassMap() as $namespace => $path) {
            try {
                if (str_starts_with($namespace, 'Shopware\\')) {
                    require_once $path;
                }
            } catch (\Throwable $e) {
                // nth, continue
            }
        }

        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, Hook::class) && !(new \ReflectionClass($class))->isAbstract() && !str_contains($class, '\\Test\\')) {
                $hookClasses[] = $class;
            }
        }

        if (\count($hookClasses) === 0) {
            throw new \RuntimeException('No HookClasses found, please ensure the composer autoloader is optimized by running `composer:install -o`.');
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
    private function getAvailableData(\ReflectionClass $reflection): string
    {
        $availableData = '';

        foreach ($reflection->getProperties() as $property) {
            $propertyType = $property->getType();
            if (!$propertyType instanceof \ReflectionNamedType) {
                throw new \RuntimeException(sprintf(
                    'Property "%s" in HookClass "%s" is not typed.',
                    $property->getName(),
                    $reflection->getName()
                ));
            }

            $availableData .= $property->getName() . ': `' . $propertyType->getName() . '`<br>';
        }

        return $availableData;
    }

    /**
     * @param \ReflectionClass<Hook> $reflection
     */
    private function getAvailableServices(\ReflectionClass $reflection): string
    {
        $serviceIds = $reflection->getMethod('getServiceIds')->invoke(null);
        $services = '';

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

            $services .= $name . ': `' . $returnType->getName() . '`<br>';
        }

        return $services;
    }
}
