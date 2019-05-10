<?php declare(strict_types=1);

namespace Shopware\Docs\Inspection;

use Composer\EventDispatcher\Event;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class ModuleInspector
{
    /**
     * @return ModuleTag[]
     */
    public function inspectModule(SplFileInfo $module): array
    {
        $inspectors = [
            'Data store' => function (ModuleTag $moduleTag, SplFileInfo $module): void {
                $moduleTag->addMarkers('Definitions', $this->containsSubclassesOf($module, EntityDefinition::class));
            },
            'Maintenance' => function (ModuleTag $moduleTag, SplFileInfo $module): void {
                $moduleTag->addMarkers('commands', $this->containsSubclassesOf($module, Command::class));
            },
            'Custom actions' => function (ModuleTag $moduleTag, SplFileInfo $module): void {
                $moduleTag->addMarkers('action controller', $this->findFiles($module, '*ActionController.php'));
            },
            'SalesChannel-API' => function (ModuleTag $moduleTag, SplFileInfo $module): void {
                $moduleTag->addMarkers('sales channel controller', $this->findFiles($module, 'Storefront*Controller.php'));
            },
            'Custom Extendable' => function (ModuleTag $moduleTag, SplFileInfo $module): void {
                $moduleTag->addMarkers('extendable classes', $this->findExtendableClasses($module))
                    ->addMarkers('custom events', $this->findCustomEvents($module));
            },
            'Rule Provider' => function (ModuleTag $moduleTag, SplFileInfo $module): void {
                $moduleTag->addMarkers('rules', $this->containsSubclassesOf($module, Rule::class));
            },
            'Business Event Dispatcher' => function (ModuleTag $moduleTag, SplFileInfo $module) {
                $moduleTag->addMarkers('business events', $this->containsSubclassesOf($module, BusinessEventInterface::class));
            },
            'Extension' => function (ModuleTag $moduleTag, SplFileInfo $module): void {
                $moduleTag
                    ->addMarkers('fields', $this->containsSubclassesOf($module, Field::class))
                    ->addMarkers('structs', $this->containsReflection($module, function (\ReflectionClass $reflectionClass) {
                        return $reflectionClass->isSubclassOf(Struct::class)
                            && !$reflectionClass->isSubclassOf(Entity::class);
                    }));
            },
            'Custom Rules' => function (ModuleTag $moduleTag, SplFileInfo $module): void {
                $moduleTag
                    ->addMarkers('rules', $this->containsSubclassesOf($module, Rule::class));
            },
        ];

        $moduleTags = [];

        foreach ($inspectors as $moduleTagName => $inspector) {
            $moduleTag = new ModuleTag($moduleTagName);

            $inspector($moduleTag, $module);

            if ($moduleTag->fits()) {
                $moduleTags[] = $moduleTag;
            }
        }

        return $moduleTags;
    }

    public function getClassName(SplFileInfo $file): string
    {
        $parts = explode('/', $file->getRealPath());

        $startIndex = array_search('Core', $parts, true);

        if ($startIndex === false) {
            throw new \Exception('Unable to parse ' . $file->getRealPath());
        }

        $namespaceRelevantParts = array_slice($parts, $startIndex, -1);
        $namespaceRelevantParts[] = $file->getBasename('.php');

        $className = 'Shopware\\' . implode('\\', $namespaceRelevantParts);

        try {
            class_exists($className);
        } catch (\Throwable $e) {
            throw new \RuntimeException('No class in file');
        }

        return $className;
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
        return $this->containsReflection($in, function (\ReflectionClass $reflectionClass) use ($searchedClass) {
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
