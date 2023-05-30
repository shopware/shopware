<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Docs\Script;

use League\ConstructFinder\ConstructFinder;
use phpDocumentor\Reflection\DocBlock\Tags\Generic;
use phpDocumentor\Reflection\DocBlock\Tags\Since;
use phpDocumentor\Reflection\DocBlock\Tags\Var_;
use phpDocumentor\Reflection\DocBlockFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Shopware\Core\Framework\Script\Execution\Awareness\StoppableHook;
use Shopware\Core\Framework\Script\Execution\DeprecatedHook;
use Shopware\Core\Framework\Script\Execution\FunctionHook;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\InterfaceHook;
use Shopware\Core\Framework\Script\Execution\OptionalFunctionHook;
use Shopware\Core\Framework\Script\Execution\TraceHook;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('core')]
class HooksReferenceGenerator implements ScriptReferenceGenerator
{
    final public const USE_CASE_DATA_LOADING = 'data_loading';
    final public const USE_CASE_CART_MANIPULATION = 'cart_manipulation';
    final public const USE_CASE_CUSTOM_ENDPOINT = 'custom_endpoint';
    final public const USE_CASE_APP_LIFECYCLE = 'app_lifecycle';
    final public const USE_CASE_PRODUCT = 'product';

    final public const ALLOWED_USE_CASES = [
        self::USE_CASE_CART_MANIPULATION,
        self::USE_CASE_DATA_LOADING,
        self::USE_CASE_CUSTOM_ENDPOINT,
        self::USE_CASE_APP_LIFECYCLE,
        self::USE_CASE_PRODUCT,
    ];

    private const TEMPLATE_FILE = __DIR__ . '/../../Resources/templates/hook-reference.md.twig';
    private const GENERATED_DOC_FILE = __DIR__ . '/../../Resources/generated/script-hooks-reference.md';

    private readonly DocBlockFactory $docFactory;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly Environment $twig,
        private readonly ServiceReferenceGenerator $serviceReferenceGenerator
    ) {
        $this->docFactory = DocBlockFactory::createInstance([
            'hook-use-case' => Generic::class,
            'script-service' => Generic::class,
        ]);
    }

    public function generate(): array
    {
        $hookClassNames = $this->getHookClasses();

        $data = $this->getHookData($hookClassNames);

        $originalLoader = $this->twig->getLoader();

        $this->twig->setLoader(new ArrayLoader([
            'hook-reference.md.twig' => file_get_contents(self::TEMPLATE_FILE),
        ]));

        try {
            $result = [
                self::GENERATED_DOC_FILE => $this->twig->render('hook-reference.md.twig', ['data' => $data]),
            ];
        } finally {
            $this->twig->setLoader($originalLoader);
        }

        return $result;
    }

    /**
     * @return list<class-string<Hook>>
     */
    private function getHookClasses(): array
    {
        $hookClasses = [];

        $shopwareClasses = ConstructFinder::locatedIn(__DIR__ . '/../../../..')
            ->exclude('*/Test/*', '*/vendor/*', '*/DevOps/StaticAnalyze*')
            ->findClassNames();

        foreach ($shopwareClasses as $class) {
            if (!class_exists($class)) {
                // skip not autoloadable test classes
                continue;
            }

            if (is_subclass_of($class, FunctionHook::class) || is_subclass_of($class, TraceHook::class)) {
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

    /**
     * @param list<class-string<Hook>> $hookClassNames
     *
     * @return array<string, array<string, mixed>>
     */
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
            self::USE_CASE_CUSTOM_ENDPOINT => [
                'title' => 'Custom API endpoint',
                'description' => 'All available hooks within the Store-API and API',
                'hooks' => [],
            ],
            self::USE_CASE_APP_LIFECYCLE => [
                'title' => 'App Lifecycle',
                'description' => 'All available hooks that can be used to execute scripts during your app\'s lifecycle.',
                'hooks' => [],
            ],
        ];

        /** @var class-string<Hook> $hook */
        foreach ($hookClassNames as $hook) {
            $hookData = $this->getDataForHook($hook);

            if (is_subclass_of($hook, InterfaceHook::class)) {
                $hookData = $this->addHookFunctionData($hookData, $hook);
            }

            /** @var string $useCase */
            $useCase = $hookData['use-case'];

            $data[$useCase]['hooks'][] = $hookData;
        }

        return $data;
    }

    /**
     * @param \ReflectionClass<Hook> $reflection
     *
     * @return list<array<string, ?string>>
     */
    private function getAvailableData(\ReflectionClass $reflection): array
    {
        $availableData = [];

        foreach ($reflection->getProperties() as $property) {
            $propertyType = $property->getType();

            if (!$propertyType instanceof \ReflectionNamedType) {
                $propertyDoc = $this->docFactory->create($property);
                /** @var Var_[] $varDoc */
                $varDoc = $propertyDoc->getTagsByName('var');

                if (\count($varDoc) === 0) {
                    throw new \RuntimeException(sprintf(
                        'Property "%s" in HookClass "%s" is not typed and has no @var annotation.',
                        $property->getName(),
                        $reflection->getName()
                    ));
                }

                $varDoc = $varDoc[0];
                $type = (string) $varDoc->getType();
            } else {
                $type = $propertyType->getName();
            }

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
     *
     * @return list<array<string, ?string>>
     */
    private function getAvailableServices(\ReflectionClass $reflection): array
    {
        $serviceIds = $reflection->getMethod('getServiceIds')->invoke(null);
        $deprecatedServices = $reflection->getMethod('getDeprecatedServices')->invoke(null);
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
                'deprecated' => $deprecatedServices[$serviceId] ?? null,
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

    /**
     * @param class-string<Hook> $hook
     *
     * @return array<string, mixed>
     */
    private function getDataForHook(string $hook): array
    {
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

        /** @var Since[] $since */
        $since = $docBlock->getTagsByName('since');
        if (\count($since) !== 1) {
            throw new \RuntimeException(sprintf(
                '`@since` annotation is missing for hook "%s". All HookClasses need to be tagged with the `@since` annotation with the correct version, in which the hook was introduced.',
                $hook,
            ));
        }

        if ($reflection->hasConstant('FUNCTION_NAME')) {
            $name = $reflection->getConstant('FUNCTION_NAME');
        } else {
            $name = $reflection->getConstant('HOOK_NAME');
        }
        \assert(\is_string($name));

        $deprecationNotice = '';
        if ($reflection->implementsInterface(DeprecatedHook::class)) {
            $deprecationNotice .= '**Deprecated:** ' . $reflection->getMethod('getDeprecationNotice')->invoke(null);
        }

        if (is_subclass_of($hook, OptionalFunctionHook::class)) {
            $requiredInVersion = $hook::willBeRequiredInVersion();
            if ($requiredInVersion) {
                $deprecationNotice .= sprintf(
                    '**Attention:** Function "%s" will be required from %s onward.',
                    $name,
                    $requiredInVersion
                );
            }
        }

        return [
            'name' => $name,
            'use-case' => $description->render(),
            'class' => $hook,
            'trigger' => $docBlock->getSummary() . '<br>' . $docBlock->getDescription()->render(),
            'data' => $this->getAvailableData($reflection),
            'services' => $this->getAvailableServices($reflection),
            'since' => $since[0]->getVersion(),
            'stoppable' => mb_strtolower(var_export($reflection->implementsInterface(StoppableHook::class), true)),
            'optional' => mb_strtolower(var_export(is_subclass_of($hook, OptionalFunctionHook::class), true)),
            'deprecation' => $deprecationNotice,
        ];
    }

    /**
     * @param array<string, mixed> $hookData
     * @param class-string<InterfaceHook> $hook
     *
     * @return array<string, mixed>
     */
    private function addHookFunctionData(array $hookData, string $hook): array
    {
        $hookData['interfaceHook'] = true;
        $hookData['interfaceDescription'] = "**Interface Hook**\n\n" . $hookData['trigger'];

        foreach ($hook::FUNCTIONS as $functionName => $functionHook) { /* @phpstan-ignore-line  */
            $hookData['functions'][$functionName] = $this->getDataForHook($functionHook);
        }

        return $hookData;
    }
}
