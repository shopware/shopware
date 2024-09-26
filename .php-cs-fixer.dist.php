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

        'blank_line_after_opening_tag' => false,
        'class_attributes_separation' => ['elements' => ['property' => 'one', 'method' => 'one']],
        'concat_space' => ['spacing' => 'one'],
        'declare_strict_types' => true,
        'fopen_flags' => false,
        'general_phpdoc_annotation_remove' => ['annotations' => ['copyright', 'category']],
        'linebreak_after_opening_tag' => false,
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
        'native_function_invocation' => [
            'scope' => 'namespaced',
            'strict' => false,
            'exclude' => ['ini_get'],
        ],
        'no_superfluous_phpdoc_tags' => ['allow_unused_params' => true, 'allow_mixed' => true],
        'no_useless_else' => true,
        'no_useless_return' => true,
        'ordered_class_elements' => true,
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_annotation_without_dot' => false,
        'phpdoc_line_span' => true,
        'phpdoc_order' => ['order' => ['param', 'throws', 'return']],
        'phpdoc_summary' => false,
        'phpdoc_to_comment' => false,
        'php_unit_dedicate_assert' => ['target' => 'newest'],
        'php_unit_dedicate_assert_internal_type' => true,
        'php_unit_mock' => true,
        'php_unit_test_case_static_method_calls' => ['call_type' => 'static'],
        'self_accessor' => false,
        'single_line_throw' => false,
        'single_quote' => ['strings_containing_single_quote_chars' => true],
        'strict_comparison' => true,
        'strict_param' => true,
        'void_return' => true,
        'yoda_style' => [
            'equal' => false,
            'identical' => false,
            'less_and_greater' => false,
        ],
    ])
    ->setUsingCache(true)
    ->setCacheFile(Path::join($_SERVER['SHOPWARE_TOOL_CACHE_ECS'] ?? 'var/cache/cs_fixer', 'cs_fixer.cache'))
    ->setFinder(
        (new Finder())
            ->in([__DIR__ . '/src', __DIR__ . '/tests'])
            ->exclude(['node_modules', '*/vendor/*'])
            ->notPath('WebInstaller/Tests/_fixtures/Options.php')
    );
