<?php declare(strict_types=1);

// Prints the `FEATURE_ALL` lanes of the in-flight majors as a JSON array, for the workflows that
// build a matrix leg per major (integration-major.yml). See lib/feature-flags.php.
require_once __DIR__ . '/lib/feature-flags.php';

echo \json_encode(shopware_major_lanes(), \JSON_THROW_ON_ERROR);
