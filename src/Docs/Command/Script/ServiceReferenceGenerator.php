<?php declare(strict_types=1);

namespace Shopware\Docs\Command\Script;

use League\ConstructFinder\ConstructFinder;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlock\Tags\Deprecated;
use phpDocumentor\Reflection\DocBlock\Tags\Example;
use phpDocumentor\Reflection\DocBlock\Tags\Generic;
use phpDocumentor\Reflection\DocBlock\Tags\Method;
use phpDocumentor\Reflection\DocBlock\Tags\Param;
use phpDocumentor\Reflection\DocBlock\Tags\Return_;
use phpDocumentor\Reflection\DocBlock\Tags\TagWithType;
use phpDocumentor\Reflection\DocBlockFactory;
use Shopware\Core\Framework\Script\ServiceStubs;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
class ServiceReferenceGenerator implements ScriptReferenceGenerator
{
    public const GROUP_DATA_LOADING = 'data_loading';
    public const GROUP_CART_MANIPULATION = 'cart_manipulation';
    public const GROUP_CUSTOM_ENDPOINT = 'custom_endpoint';
    public const GROUP_MISCELLANEOUS = 'miscellaneous';

    public const GROUPS = [
        self::GROUP_DATA_LOADING => 'data-loading-script-services-reference.md',
        self::GROUP_CART_MANIPULATION => 'cart-manipulation-script-services-reference.md',
        self::GROUP_CUSTOM_ENDPOINT => 'custom-endpoint-script-services-reference.md',
        self::GROUP_MISCELLANEOUS => 'miscellaneous-script-services-reference.md',
    ];

    public const GITHUB_BASE_LINK = 'https://github.com/shopware/platform/blob/trunk';

    private const TEMPLATE_FILE = __DIR__ . '/../../Resources/templates/Scripts/service-reference.md.twig';
    private const GENERATED_DOC_FILE = __DIR__ . '/../../Resources/current/47-app-system-guide/';

    private DocBlockFactory $docFactory;

    private string $projectDir;

    private array $injectedServices = [];

    private Environment $twig;

    public function __construct(Environment $twig, string $projectDir)
    {
        $this->projectDir = $projectDir;
        $this->twig = $twig;

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

        $originalLoader = $this->twig->getLoader();
        $this->twig->setLoader(new ArrayLoader([
            'service-reference.md.twig' => file_get_contents(self::TEMPLATE_FILE),
        ]));

        $result = [];

        try {
            foreach ($data as $group) {
                $result[self::GENERATED_DOC_FILE . $group['fileName']] = $this->twig->render('service-reference.md.twig', $group);
            }
        } finally {
            $this->twig->setLoader($originalLoader);
        }

        return $result;
    }

    /**
     * @param \ReflectionClass<object> $reflection
     */
    public function getGroupForService(\ReflectionClass $reflection): string
    {
        $docBlock = $this->docFactory->create($reflection);

        /** @var Generic[] $tags */
        $tags = $docBlock->getTagsByName('script-service');

        $description = $tags[0]->getDescription();

        if (!$description || !\in_array($description->render(), array_keys(self::GROUPS), true)) {
            throw new \RuntimeException(sprintf(
                'Script Services "%s" is not correctly tagged to the group. Available groups are: "%s".',
                $reflection->getName(),
                implode('", "', array_keys(self::GROUPS)),
            ));
        }

        return $description->render();
    }

    public function getLinkForClass(string $className, array $scriptServices = []): ?string
    {
        if (!str_starts_with($className, 'Shopware\\') || !\class_exists($className)) {
            return null;
        }

        $reflection = new \ReflectionClass($className);

        if (\in_array($className, $scriptServices, true)) {
            return \sprintf('./%s#%s', self::GROUPS[$this->getGroupForService($reflection)], strtolower($reflection->getShortName()));
        }

        /** @var string $filename */
        $filename = $reflection->getFileName();

        $relativePath = str_replace($this->projectDir, '', $filename);

        return self::GITHUB_BASE_LINK . $relativePath;
    }

    private function findScriptServices(): array
    {
        $scriptServices = [];

        $shopwareClasses = ConstructFinder::locatedIn(__DIR__ . '/../../..')
            ->exclude('*/Test/*', '*/vendor/*')
            ->findClassNames();

        foreach ($shopwareClasses as $class) {
            if (!class_exists($class)) {
                // skip not autoloadable test classes
                continue;
            }

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
            throw new \RuntimeException('No ScriptServices found.');
        }
        sort($scriptServices);

        return $scriptServices;
    }

    private function getServicesData(array $scriptServices): array
    {
        $data = [
            self::GROUP_DATA_LOADING => [
                'title' => 'Data Loading',
                'fileName' => self::GROUPS[self::GROUP_DATA_LOADING],
                'description' => 'Here you find a complete reference of all script services that can be used to load additional data.',
                'services' => [],
            ],
            self::GROUP_CART_MANIPULATION => [
                'title' => 'Cart Manipulation',
                'fileName' => self::GROUPS[self::GROUP_CART_MANIPULATION],
                'description' => 'Here you find a complete reference of all script services that can be used to manipulate the cart.',
                'services' => [],
            ],
            self::GROUP_CUSTOM_ENDPOINT => [
                'title' => 'Custom Endpoint',
                'fileName' => self::GROUPS[self::GROUP_CUSTOM_ENDPOINT],
                'description' => 'Here you find a complete reference of all script services that can be used in your custom endpoints.',
                'services' => [],
            ],
            self::GROUP_MISCELLANEOUS => [
                'title' => 'Miscellaneous',
                'fileName' => self::GROUPS[self::GROUP_MISCELLANEOUS],
                'description' => 'Here you find a complete reference of all general script services that can be used in any script.',
                'services' => [],
            ],
        ];

        foreach ($scriptServices as $service) {
            $reflection = new \ReflectionClass($service);

            $docBlock = $this->docFactory->create($reflection);
            if ($docBlock->hasTag('internal')) {
                // skip @internal classes
                continue;
            }

            /** @var Deprecated|null $deprecated */
            $deprecated = $docBlock->getTagsByName('deprecated')[0] ?? null;

            $group = $this->getGroupForService($reflection);

            $data[$group]['services'][] = [
                'name' => $this->getName($service),
                'link' => $this->getLinkForClass($service),
                // add fragment-marker to easily link to specific classes, see https://stackoverflow.com/a/54335742/10064036
                // as `{#` indicates a twig comment, we can't add it inside the template
                'marker' => '{#' . strtolower($reflection->getShortName()) . '}',
                'deprecated' => $deprecated ? (string) $deprecated : null,
                'summary' => $docBlock->getSummary(),
                'description' => $docBlock->getDescription()->render(),
                'methods' => $this->getMethods($reflection, $scriptServices),
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
    private function getMethods(\ReflectionClass $reflection, array $scriptServices): array
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
            if ($docBlock->hasTag('internal')) {
                // skip @internal methods
                continue;
            }

            /** @var Deprecated|null $deprecated */
            $deprecated = $docBlock->getTagsByName('deprecated')[0] ?? null;

            $methods[] = [
                'title' => $method->getName() . '()',
                'summary' => $docBlock->getSummary(),
                'description' => $docBlock->getDescription()->render(),
                'deprecated' => $deprecated ? (string) $deprecated : null,
                'arguments' => $this->parseArguments($method, $docBlock, $scriptServices),
                'return' => $this->parseReturn($method, $docBlock, $scriptServices),
                'examples' => $this->parseExamples($method, $docBlock),
            ];
        }

        return $methods;
    }

    private function parseArguments(\ReflectionMethod $method, DocBlock $docBlock, array $scriptServices): array
    {
        $arguments = [];
        /** @var Param[] $paramDocs */
        $paramDocs = $docBlock->getTagsWithTypeByName('param');

        foreach ($method->getParameters() as $parameter) {
            $paramDoc = $this->findDocForParam($paramDocs, $parameter->getName(), $method);

            $typeInformation = $this->getTypeInformation($parameter->getType(), $paramDoc, $scriptServices);

            $arguments[] = array_merge(
                [
                    'name' => $parameter->getName(),
                    'default' => $parameter->isDefaultValueAvailable() ? mb_strtolower(var_export($parameter->getDefaultValue(), true)) : null,
                ],
                $typeInformation
            );
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

    private function parseReturn(\ReflectionMethod $method, DocBlock $docBlock, array $scriptServices): array
    {
        $type = $method->getReturnType();

        if ($type instanceof \ReflectionNamedType && $type->getName() === 'void') {
            return [];
        }

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

        return $this->getTypeInformation($type, $tag, $scriptServices);
    }

    private function getTypeInformation(?\ReflectionType $type, TagWithType $tag, array $scriptServices): array
    {
        /** @var class-string<object> $typeName */
        $typeName = (string) $tag->getType();
        if ($type instanceof \ReflectionNamedType) {
            //The docBlock probably don't use the FQCN, therefore we use the native return type if we have one
            /** @var class-string<object> $typeName */
            $typeName = $type->getName();
        }

        $link = $this->getLinkForClass($typeName, $scriptServices);
        if ($link) {
            $typeName = \sprintf('[`%s`](%s)', $typeName, $link);
        } else {
            $typeName = '`' . $typeName . '`';
        }

        if ($type instanceof \ReflectionType && $type->allowsNull()) {
            $typeName .= ' | `null`';
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
            $finder = new Finder();
            $finder->files()
                ->in(__DIR__ . '/../../../')
                // exclude js files including node_modules for performance reasons, filtering with `notPath`, etc. has no performance impact
                // note that excluded paths need to be relative to platform/src and that no wildcards are supported
                ->exclude([
                    'Administration/Resources',
                    'Storefront/Resources',
                    'Recovery',
                    'Docs',
                ])
                ->path($example->getFilePath())
                ->ignoreUnreadableDirs();

            $files = iterator_to_array($finder);

            if (\count($files) === 0) {
                throw new \RuntimeException(sprintf(
                    'Cannot find configured example file in `@example` annotation for method "%s()" in class "%s". File with pattern "%s" can not be found.',
                    $method->getName(),
                    $method->getDeclaringClass()->getName(),
                    $example->getFilePath()
                ));
            }

            if (\count($files) > 1) {
                throw new \RuntimeException(sprintf(
                    'Configured file pattern in `@example` annotation for method "%s()" in class "%s" is not unique. File pattern "%s" matched "%s".',
                    $method->getName(),
                    $method->getDeclaringClass()->getName(),
                    $example->getFilePath(),
                    implode('", "', array_keys($files))
                ));
            }

            $file = array_values($files)[0];

            $examples[] = [
                'description' => $example->getDescription(),
                'src' => $this->getExampleSource($file, $example),
                'extension' => $file->getExtension(),
            ];
        }

        return $examples;
    }

    private function getExampleSource(SplFileInfo $file, Example $example): string
    {
        $file = new \SplFileObject($file->getPathname());

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
