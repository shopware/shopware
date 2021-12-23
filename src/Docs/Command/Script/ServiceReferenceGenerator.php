<?php declare(strict_types=1);

namespace Shopware\Docs\Command\Script;

use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlock\Tags\Example;
use phpDocumentor\Reflection\DocBlock\Tags\Generic;
use phpDocumentor\Reflection\DocBlock\Tags\Method;
use phpDocumentor\Reflection\DocBlock\Tags\Param;
use phpDocumentor\Reflection\DocBlock\Tags\Return_;
use phpDocumentor\Reflection\DocBlockFactory;
use Shopware\Core\Framework\Script\ServiceStubs;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
class ServiceReferenceGenerator implements ScriptReferenceGenerator
{
    public const GROUP_DATA_LOADING = 'data_loading';
    public const GROUP_CART_MANIPULATION = 'cart_manipulation';
    public const GROUP_MISCELLANEOUS = 'miscellaneous';

    public const GROUPS = [
        self::GROUP_DATA_LOADING,
        self::GROUP_CART_MANIPULATION,
        self::GROUP_MISCELLANEOUS,
    ];

    private const TEMPLATE_FILE = __DIR__ . '/../../Resources/templates/Scripts/service-reference.md.twig';
    private const GENERATED_DOC_FILE = __DIR__ . '/../../Resources/current/47-app-system-guide/';

    private ContainerInterface $container;

    private DocBlockFactory $docFactory;

    private array $injectedServices = [];

    /**
     * @psalm-suppress ContainerDependency
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        $this->docFactory = DocBlockFactory::createInstance([
            'script-service' => Generic::class,
            'example' => Example::class,
        ]);

        /** @var Method[] $methodDocs */
        $methodDocs = $this->docFactory->create(
            new \ReflectionClass(ServiceStubs::class)
        )->getTagsByName('method');

        foreach ($methodDocs as $methodDoc) {
            $this->injectedServices[
                ltrim((string) $methodDoc->getReturnType(), '\\')
            ] = $methodDoc->getMethodName();
        }
    }

    public function generate(): array
    {
        $scriptServices = $this->findScriptServices();

        $data = $this->getServicesData($scriptServices);

        /** @var Environment $twig */
        $twig = $this->container->get('twig');
        $originalLoader = $twig->getLoader();
        $twig->setLoader(new ArrayLoader([
            'service-reference.md.twig' => file_get_contents(self::TEMPLATE_FILE),
        ]));

        $result = [];

        try {
            foreach ($data as $group) {
                $result[self::GENERATED_DOC_FILE . $group['fileName']] = $twig->render('service-reference.md.twig', $group);
            }
        } finally {
            $twig->setLoader($originalLoader);
        }

        return $result;
    }

    private function findScriptServices(): array
    {
        $scriptServices = [];

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
            $reflection = new \ReflectionClass($class);

            if (!$reflection->getDocComment()) {
                continue;
            }

            $doc = $this->docFactory->create($reflection);

            if (!$doc->hasTag('script-service')) {
                continue;
            }

            $scriptServices[] = $class;
        }

        if (\count($scriptServices) === 0) {
            throw new \RuntimeException('No ScriptServices found, please ensure the composer autoloader is optimized by running `composer:install -o`.');
        }
        sort($scriptServices);

        return $scriptServices;
    }

    private function getServicesData(array $scriptServices): array
    {
        $data = [
            self::GROUP_DATA_LOADING => [
                'title' => 'Data Loading',
                'fileName' => 'data-loading-script-services-reference.md',
                'description' => 'Here you find a complete reference of all scripting services that can be used to load additional data.',
                'services' => [],
            ],
            self::GROUP_CART_MANIPULATION => [
                'title' => 'Cart Manipulation',
                'fileName' => 'cart-manipulation-script-services-reference.md',
                'description' => 'Here you find a complete reference of all scripting services that can be used to manipulate the cart.',
                'services' => [],
            ],
            self::GROUP_MISCELLANEOUS => [
                'title' => 'Miscellaneous',
                'fileName' => 'miscellaneous-script-services-reference.md',
                'description' => 'Here you find a complete reference of all general scripting services that can be used in any script.',
                'services' => [],
            ],
        ];

        foreach ($scriptServices as $service) {
            $reflection = new \ReflectionClass($service);

            $docBlock = $this->docFactory->create($reflection);

            /** @var Generic[] $tags */
            $tags = $docBlock->getTagsByName('script-service');

            $description = $tags[0]->getDescription();

            if (!$description || !\in_array($description->render(), self::GROUPS, true)) {
                throw new \RuntimeException(sprintf(
                    'Script Services "%s" is not correctly tagged to the group. Available groups are: "%s".',
                    $service,
                    implode('", "', self::GROUPS),
                ));
            }

            $data[$description->render()]['services'][] = [
                'name' => $this->getName($service),
                'summary' => $docBlock->getSummary(),
                'description' => $docBlock->getDescription()->render(),
                'methods' => $this->getMethods($reflection),
            ];
        }

        return $data;
    }

    private function getName(string $service): string
    {
        if (\array_key_exists($service, $this->injectedServices)) {
            return 'services.' . $this->injectedServices[$service] . ' (`' . $service . '`)';
        }

        return '`' . $service . '`';
    }

    /**
     * @param \ReflectionClass<object> $reflection
     */
    private function getMethods(\ReflectionClass $reflection): array
    {
        $methods = [];

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (str_starts_with($method->getName(), '__')) {
                // skip `__construct()` and other magic methods
                continue;
            }

            if (!$method->getDocComment()) {
                throw new \RuntimeException(sprintf(
                    'DocBlock is missing for method "%s() in class "%s".',
                    $method->getName(),
                    $reflection->getName()
                ));
            }

            $docBlock = $this->docFactory->create($method);

            $methods[] = [
                'title' => $method->getName() . '()',
                'summary' => $docBlock->getSummary(),
                'description' => $docBlock->getDescription()->render(),
                'arguments' => $this->parseArguments($method, $docBlock),
                'return' => $this->parseReturn($method, $docBlock),
                'examples' => $this->parseExamples($method, $docBlock),
            ];
        }

        return $methods;
    }

    private function parseArguments(\ReflectionMethod $method, DocBlock $docBlock): array
    {
        $arguments = [];
        /** @var Param[] $paramDocs */
        $paramDocs = $docBlock->getTagsWithTypeByName('param');

        foreach ($method->getParameters() as $parameter) {
            $paramDoc = $this->findDocForParam($paramDocs, $parameter->getName(), $method);

            $type = $paramDoc->getType() ? (string) $paramDoc->getType() : ($parameter->getType() instanceof \ReflectionNamedType ? $parameter->getType()->getName() : null);
            if ($type === null) {
                throw new \RuntimeException(sprintf(
                    'Missing type for param "$%s" on method "%s()" in class "%s",',
                    $parameter->getName(),
                    $method->getName(),
                    $method->getDeclaringClass()->getName()
                ));
            }

            $arguments[] = [
                'name' => $parameter->getName(),
                'type' => $type,
                'default' => $parameter->isDefaultValueAvailable() ? mb_strtolower(var_export($parameter->getDefaultValue(), true)) : null,
                'description' => $paramDoc->getDescription() ? $paramDoc->getDescription()->render() : '',
            ];
        }

        return $arguments;
    }

    /**
     * @param Param[] $paramDocs
     */
    private function findDocForParam(array $paramDocs, string $name, \ReflectionMethod $method): Param
    {
        foreach ($paramDocs as $param) {
            if ($param->getVariableName() === $name) {
                return $param;
            }
        }

        throw new \RuntimeException(sprintf(
            'Missing doc block for param "$%s" on method "%s()" in class "%s",',
            $name,
            $method->getName(),
            $method->getDeclaringClass()->getName()
        ));
    }

    private function parseReturn(\ReflectionMethod $method, DocBlock $docBlock): array
    {
        $type = $method->getReturnType();

        /** @var Return_[] $tags */
        $tags = $docBlock->getTagsWithTypeByName('return');
        if (\count($tags) < 1) {
            throw new \RuntimeException(sprintf(
                'Missing @return annotation on method "%s()" in class "%s",',
                $method->getName(),
                $method->getDeclaringClass()->getName()
            ));
        }
        $tag = $tags[0];

        $typeName = (string) $tag->getType();
        if ($type instanceof \ReflectionNamedType) {
            //The docBlock probably don't use the FQCN, therefore we use the native return type if we have one
            $typeName = $type->getName();
        }

        if ($typeName === 'void') {
            return [];
        }

        return [
            'type' => $typeName,
            'description' => $tag->getDescription() ? $tag->getDescription()->render() : '',
        ];
    }

    private function parseExamples(\ReflectionMethod $method, DocBlock $docBlock): array
    {
        $examples = [];

        /** @var Example $example */
        foreach ($docBlock->getTagsByName('example') as $example) {
            /** @var string $classFile */
            $classFile = $method->getFileName();
            $filename = \dirname($classFile) . '/' . ltrim($example->getFilePath(), '/');

            if (!file_exists($filename) || !is_file($filename)) {
                throw new \RuntimeException(sprintf(
                    'Undefined filename configured in `@example` annotation for method "%s()" in class "%s". File "%s" can not be found',
                    $method->getName(),
                    $method->getDeclaringClass()->getName(),
                    $example->getFilePath()
                ));
            }

            $examples[] = [
                'description' => $example->getDescription(),
                'src' => $this->getExampleSource($filename, $example),
                'extension' => pathinfo($filename, \PATHINFO_EXTENSION),
            ];
        }

        return $examples;
    }

    private function getExampleSource(string $filename, Example $example): string
    {
        $file = new \SplFileObject($filename);

        // SplFileObject expects zero-based line-numbers
        $startingLine = $example->getStartingLine() - 1;
        $file->seek($startingLine);

        $content = '';
        $lineCount = $example->getLineCount() === 0 ? \PHP_INT_MAX : $example->getLineCount();

        while (($file->key() - $startingLine) < $lineCount && !$file->eof()) {
            $content .= $file->current();
            $file->next();
        }

        return trim($content);
    }
}
