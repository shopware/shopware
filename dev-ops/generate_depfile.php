<?php declare(strict_types=1);

$paths = [
    'architecture' => 'src',
    'api' => 'src/Api'
];

foreach ($paths as $depfileKey => $pathName) {

    $output = <<<EOF
paths:
  - ./$pathName

exclude_files:
  - .*test.*

layers:

EOF;


    $namespaceParts = array_filter(explode('/', $pathName));
    array_shift($namespaceParts);
    array_unshift($namespaceParts, 'Shopware');
    $namespace = implode('\\\\', $namespaceParts);

    $components = [];
    foreach(scandir(__DIR__ . '/../' . $pathName, SCANDIR_SORT_ASCENDING) as $file) {
        $path = __DIR__ . '/../'.$pathName.'/' . $file;

        if('.' === $file || '..' === $file || !is_dir($path)) {
            continue;
        }

        $components[] = $file;

        $output .= <<<EOD
      - name: $file
        collectors:
          - type: className
            regex: ^$namespace\\\\$file\\\\*

EOD;

    }

    $output .= PHP_EOL;
    $output .= "ruleset:".PHP_EOL;

    foreach($components as $component) {
        $output .= "    $component:\n";
    }

    $output .= PHP_EOL;

    file_put_contents(__DIR__ . '/../'.$depfileKey.'.depfile.yml', $output);
}
