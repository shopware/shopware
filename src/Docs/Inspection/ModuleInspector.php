<?php declare(strict_types=1);

namespace Shopware\Docs\Inspection;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Contracts\EventDispatcher\Event;

class ModuleInspector
{
    public const TAG_DATA_STORE = 'Data store';
    public const TAG_MAINTENANCE = 'Maintenance';
    public const TAG_CUSTOM_ACTIONS = 'Custom actions';
    public const TAG_SALES_CHANNEL_API = 'SalesChannel-API';
    public const TAG_CUSTOM_EXTENDABLE = 'Custom Extendable';
    public const TAG_BUSINESS_EVENT_DISPATCHER = 'Business Event Dispatcher';
    public const TAG_EXTENSION = 'Extension';
    public const TAG_CUSTOM_RULES = 'Custom Rules';

    protected static string $defaultName = 'docs:convert';

    public function getAllTags(): array
    {
        return [
            self::TAG_DATA_STORE,
            self::TAG_MAINTENANCE,
            self::TAG_CUSTOM_ACTIONS,
            self::TAG_SALES_CHANNEL_API,
            self::TAG_CUSTOM_EXTENDABLE,
            self::TAG_BUSINESS_EVENT_DISPATCHER,
            self::TAG_EXTENSION,
            self::TAG_CUSTOM_RULES,
        ];
    }

    public function inspectModule(SplFileInfo $module): ModuleTagCollection
    {
        $inspectors = [
            self::TAG_DATA_STORE => function (ModuleTag $moduleTag, SplFileInfo $module): void {
                $moduleTag->addMarkers('Definitions', $this->containsSubclassesOf($module, EntityDefinition::class));
            },
            self::TAG_MAINTENANCE => function (ModuleTag $moduleTag, SplFileInfo $module): void {
                $moduleTag->addMarkers('commands', $this->containsSubclassesOf($module, Command::class));
            },
            self::TAG_CUSTOM_ACTIONS => function (ModuleTag $moduleTag, SplFileInfo $module): void {
                $moduleTag->addMarkers('action controller', $this->findFiles($module, '*ActionController.php'));
            },
            self::TAG_SALES_CHANNEL_API => function (ModuleTag $moduleTag, SplFileInfo $module): void {
                $moduleTag->addMarkers('sales channel controller', $this->findFiles($module, 'Storefront*Controller.php'));
            },
            self::TAG_CUSTOM_EXTENDABLE => function (ModuleTag $moduleTag, SplFileInfo $module): void {
                $moduleTag->addMarkers('extendable classes', $this->findExtendableClasses($module))
                    ->addMarkers('custom events', $this->findCustomEvents($module));
            },
            self::TAG_BUSINESS_EVENT_DISPATCHER => function (ModuleTag $moduleTag, SplFileInfo $module): void {
                $moduleTag->addMarkers('business events', $this->containsSubclassesOf($module, BusinessEventInterface::class));
            },
            self::TAG_EXTENSION => function (ModuleTag $moduleTag, SplFileInfo $module): void {
                $moduleTag
                    ->addMarkers('fields', $this->containsSubclassesOf($module, Field::class))
                    ->addMarkers('structs', $this->containsReflection($module, static function (\ReflectionClass $reflectionClass) {
                        return $reflectionClass->isSubclassOf(Struct::class)
                            && !$reflectionClass->isSubclassOf(Entity::class);
                    }));
            },
            self::TAG_CUSTOM_RULES => function (ModuleTag $moduleTag, SplFileInfo $module): void {
                $moduleTag
                    ->addMarkers('rules', $this->containsSubclassesOf($module, Rule::class));
            },
        ];

        $moduleTags = new ModuleTagCollection($module);

        foreach ($inspectors as $moduleTagName => $inspector) {
            $moduleTag = new ModuleTag($moduleTagName);

            $inspector($moduleTag, $module);

            if ($moduleTag->fits()) {
                $moduleTags->add($moduleTag);
            }
        }

        return $moduleTags;
    }

    public function getClassName(SplFileInfo $file): string
    {
        $filePath = $file->getRealPath();
        $parts = explode('/', $filePath);

        $startIndex = array_search('Core', $parts, true);

        if ($startIndex === false) {
            throw new \RuntimeException('Unable to parse ' . $file->getRealPath());
        }

        $namespaceRelevantParts = \array_slice($parts, $startIndex, -1);
        $namespaceRelevantParts[] = $file->getBasename('.php');

        $className = 'Shopware\\' . implode('\\', $namespaceRelevantParts);

        try {
            $classExists = class_exists($className);
        } catch (\Throwable $e) {
            throw new \RuntimeException(sprintf('No class in file %s', $filePath), 0, $e);
        }

        if ($classExists) {
            return $className;
        }

        throw new \RuntimeException(sprintf('No class in file %s', $filePath));
    }

    private function findFiles(SplFileInfo $in, string $pattern): Finder
    {
        return (new Finder())->in($in->getRealPath())->name($pattern);
    }

    private function findExtendableClasses(SplFileInfo $in): Finder
    {
        return (new Finder())
            ->files()
            ->in($in->getRealPath())
            ->filter(function (SplFileInfo $file) {
                if ($file->getExtension() !== 'php') {
                    return false;
                }

                try {
                    $className = $this->getClassName($file);
                } catch (\RuntimeException $e) {
                    return false;
                }

                $reflection = new \ReflectionClass($className);

                return $reflection->isInterface() || $reflection->isAbstract();
            });
    }

    private function findCustomEvents(SplFileInfo $in): Finder
    {
        return $this->containsSubclassesOf($in, Event::class);
    }

    private function containsSubclassesOf(SplFileInfo $in, string $searchedClass): Finder
    {
        return $this->containsReflection($in, static function (\ReflectionClass $reflectionClass) use ($searchedClass) {
            if ($reflectionClass->isAbstract()) {
                return false;
            }

            return $reflectionClass->isSubclassOf($searchedClass);
        });
    }

    private function containsReflection(SplFileInfo $in, callable $reflectionCheck): Finder
    {
        return (new Finder())
            ->files()
            ->in($in->getRealPath())
            ->filter(function (SplFileInfo $file) use ($reflectionCheck) {
                if ($file->getExtension() !== 'php') {
                    return false;
                }

                try {
                    $className = $this->getClassName($file);
                } catch (\RuntimeException $e) {
                    return false;
                }

                $reflection = new \ReflectionClass($className);

                return $reflectionCheck($reflection);
            });
    }
}
