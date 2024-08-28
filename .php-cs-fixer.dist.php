<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;
use Symfony\Component\Filesystem\Path;

return (new Config())
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'linebreak_after_opening_tag' => false,
        'blank_line_after_opening_tag' => false,
        'phpdoc_summary' => false,
        'phpdoc_annotation_without_dot' => false,
        'phpdoc_to_comment' => false,
        'yoda_style' => [
            'equal' => false,
            'identical' => false,
            'less_and_greater' => false,
        ],
        'single_line_throw' => false,
        'fopen_flags' => false,
        'self_accessor' => false,
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_order' => ['order' => ['param', 'throws', 'return']],
        'class_attributes_separation' => ['elements' => ['property' => 'one', 'method' => 'one']],
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
        'concat_space' => ['spacing' => 'one'],
        'native_function_invocation' => [
            'scope' => 'namespaced',
            'strict' => false,
            'exclude' => ['ini_get'],
        ],
        'general_phpdoc_annotation_remove' => ['annotations' => ['copyright', 'category']],
        'no_superfluous_phpdoc_tags' => ['allow_unused_params' => true, 'allow_mixed' => true],
        'php_unit_dedicate_assert' => ['target' => 'newest'],
        'single_quote' => ['strings_containing_single_quote_chars' => true],
    ])
    ->setUsingCache(true)
    ->setCacheFile(Path::join($_SERVER['SHOPWARE_TOOL_CACHE_ECS'] ?? 'var/cache/cs_fixer', 'cs_fixer.cache'))
    ->setFinder(
        (new Finder())
            ->in([__DIR__ . '/src', __DIR__ . '/tests'])
            ->exclude(['node_modules', '*/vendor/*'])
            ->notPath('WebInstaller/Tests/_fixtures/Options.php')
    );
