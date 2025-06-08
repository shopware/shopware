<?php declare(strict_types=1);

$ignoredFilePath = $argv[1];
$area = $argv[2];

$projectPath = getcwd();
$ignoredErrors = file_get_contents($projectPath . '/' . $ignoredFilePath);
\assert(is_string($ignoredErrors));

$matches = [];
// https://regex101.com/r/BAWjGs/1
preg_match_all('/^\s*path\: (.*.php)$/m', $ignoredErrors, $matches);

$foundPaths = [];
foreach (array_unique($matches[1]) as $match) {
    $class = file_get_contents($projectPath . '/' . $match);
    \assert(is_string($class));

    $pattern = '/#\[Package\(\'' . $area . '\'\)]/';
    if (preg_match($pattern, $class) === 1) {
        $foundPaths[] = $match;
    }
}

sort($foundPaths);

echo implode("\n", $foundPaths);
echo \PHP_EOL;
echo \PHP_EOL;
echo 'Found ' . count($foundPaths) . ' ignored files in "' . $area . '" area' . \PHP_EOL;
