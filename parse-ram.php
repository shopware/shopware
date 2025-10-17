<?php

declare(strict_types=1);

$log = new SplFileObject("phpstan.log");

$logs = [];
$file = null;
while (! $log->eof()) {
    $line = trim($log->fgets());
    if ($line === '') {
        continue;
    }
    if ($file === null) {
        $file = $line;
        continue;
    }
    preg_match('/consumed (?<memory>[\d.]+) MB/', $line, $matches);

    $logs[] = [(float) $matches['memory'], $file];
    $file = null;
}

usort($logs, fn(array $left, array $right) => $right[0] <=> $left[0]);
$logs = array_slice($logs, 0, 100);

echo "Most ram usage" . PHP_EOL;
foreach ($logs as $log) {
    echo sprintf("%.2fMB memory: %s", $log[0], $log[1]) . PHP_EOL;
}
