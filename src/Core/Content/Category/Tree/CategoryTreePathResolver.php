<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Tree;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class CategoryTreePathResolver
{
    /**
     * Returns a list of paths to load so the whole tree branch for the active category is loaded
     * It skips the paths that will be automatically loaded because they are in the defined depths of the root category
     *
     * @return list<string>
     */
    public function getAdditionalPathsToLoad(string $activeId, ?string $activePath, string $rootId, ?string $rootPath, int $depth): array
    {
        $pathToLoad = !empty($activePath) ? $activePath . $activeId . '|' : '|' . $activeId . '|';
        $rootPath = !empty($rootPath) ? $rootPath . $rootId . '|' : '|' . $rootId . '|';
        $ids = array_filter(explode('|', $pathToLoad));

        $currentPath = '|';
        $pathsToLoad = [];

        foreach ($ids as $id) {
            $currentPath .= $id . '|';

            if (str_contains($rootPath, $currentPath)) {
                // we don't need to fetch the category level above the root
                continue;
            }

            // when the current path starts with the root path,
            // we need to figure out how many levels below the root we are
            // if we are below the depth, we can skip this path
            if (\strlen($rootPath) > 1 && str_starts_with($currentPath, $rootPath)) {
                $subPath = substr($currentPath, \strlen($rootPath));
                $levelsBelow = substr_count($subPath, '|');

                if ($levelsBelow <= $depth) {
                    continue;
                }
            }

            $pathsToLoad[] = $currentPath;
        }

        return $pathsToLoad;
    }
}
