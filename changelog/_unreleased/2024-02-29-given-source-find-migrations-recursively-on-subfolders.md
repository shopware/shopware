---
title: Given source, find migrations recursively on subfolders #3584
issue: NEXT-34083
author: Raffaele Carelle
author_email: raffaele.carelle@gmail.com
author_github: @raffaelecarelle
---
# Core
* Add Shopware\Core\Framework\Migration\MigrationCollection::scanDirectory for find dirs recursively
* Add Shopware\Core\Framework\Migration\MigrationCollection::extractNamespace for extract, given filename, the namespace
___
# Next major version changes
## Core
### Change of `Shopware\Core\Framework\Migration\MigrationCollection`
#### Before
```
/**
     * @throws InvalidMigrationClassException
     *
     * @return array<class-string<MigrationStep>, MigrationStep>
     */
    private function loadMigrationSteps(): array
    {
        $migrations = [];

        foreach ($this->migrationSource->getSourceDirectories() as $directory => $namespace) {
            if (!is_readable($directory)) {
                if ($this->logger !== null) {
                    $this->logger->warning(
                        'Migration directory "{directory}" for namespace "{namespace}" does not exist or is not readable.',
                        [
                            'directory' => $directory,
                            'namespace' => $namespace,
                        ]
                    );
                }

                continue;
            }

            $classFiles = scandir($directory, \SCANDIR_SORT_ASCENDING);
            if (!$classFiles) {
                continue;
            }

            foreach ($classFiles as $classFileName) {
                $path = $directory . '/' . $classFileName;
                $className = $namespace . '\\' . pathinfo($classFileName, \PATHINFO_FILENAME);

                if (pathinfo($path, \PATHINFO_EXTENSION) !== 'php') {
                    continue;
                }

                if (!class_exists($className) && !trait_exists($className) && !interface_exists($className)) {
                    throw new InvalidMigrationClassException($className, $path);
                }

                if (!is_subclass_of($className, MigrationStep::class, true)) {
                    continue;
                }

                $migrations[$className] = new $className();
            }
        }

        return $migrations;
    }
```
#### After
```
/**
     * @throws InvalidMigrationClassException
     *
     * @return array<class-string<MigrationStep>, MigrationStep>
     */
    private function loadMigrationSteps(): array
    {
        $migrations = [];

        foreach ($this->migrationSource->getSourceDirectories() as $directory => $namespace) {
            if (!is_readable($directory)) {
                if ($this->logger !== null) {
                    $this->logger->warning(
                        'Migration directory "{directory}" for namespace "{namespace}" does not exist or is not readable.',
                        [
                            'directory' => $directory,
                            'namespace' => $namespace,
                        ]
                    );
                }

                continue;
            }

            $classFiles = $this->scanDirectory($directory);

            if (!$classFiles) {
                continue;
            }

            foreach ($classFiles as $filePath) {
                $namespace = $this->extractNamespace($filePath);
                $className = $namespace . '\\' . pathinfo($filePath, \PATHINFO_FILENAME);

                if (pathinfo($filePath, \PATHINFO_EXTENSION) !== 'php') {
                    continue;
                }

                if (!class_exists($className) && !trait_exists($className) && !interface_exists($className)) {
                    throw new InvalidMigrationClassException($className, $filePath);
                }

                if (!is_subclass_of($className, MigrationStep::class, true)) {
                    continue;
                }

                $migrations[$className] = new $className();
            }
        }

        return $migrations;
    }
```
