<?php

declare(strict_types=1);

namespace Roave\ApiCompareCli;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../patch/ComposerInstallationReflectorFactory.php';
require_once __DIR__ . '/../patch/LocateDependenciesViaComposer.php';
require_once __DIR__ . '/../patch/CompareClasses.php';
require_once __DIR__ . '/../vendor/roave/backward-compatibility-check/bin/roave-backward-compatibility-check.php';
