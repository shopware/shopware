<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Path;

return (new Config())
    // bigger chunks keep the workers busy with actual analysis instead of per-chunk overhead
    ->setParallelConfig(ParallelConfigFactory::detect(filesPerProcess: 100))
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,

        'attribute_empty_parentheses' => ['use_parentheses' => false],
        'blank_line_after_opening_tag' => false,
        'class_attributes_separation' => ['elements' => ['property' => 'one', 'method' => 'one']],
        'concat_space' => ['spacing' => 'one'],
        'declare_strict_types' => true,
        'fully_qualified_strict_types' => [
            'import_symbols' => true,
            // Keep the default set of processed PHPDoc tags, but exclude `see` so that
            // FQCN in `@see` references are not imported.
            'phpdoc_tags' => [
                'param',
                'phpstan-param',
                'phpstan-property',
                'phpstan-property-read',
                'phpstan-property-write',
                'phpstan-return',
                'phpstan-var',
                'property',
                'property-read',
                'property-write',
                'psalm-param',
                'psalm-property',
                'psalm-property-read',
                'psalm-property-write',
                'psalm-return',
                'psalm-var',
                'return',
                'throws',
                'var',
            ],
        ],
        'fopen_flags' => false,
        'general_phpdoc_annotation_remove' => ['annotations' => ['copyright', 'category']],
        'linebreak_after_opening_tag' => false,
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
        'modern_serialization_methods' => false, // TODO: enable again with https://github.com/shopware/shopware/issues/15465
        'native_function_invocation' => [
            'scope' => 'namespaced',
            'strict' => false,
            'exclude' => ['ini_get'],
        ],
        'no_superfluous_phpdoc_tags' => ['allow_unused_params' => true, 'allow_mixed' => true],
        'no_useless_else' => true,
        'no_useless_return' => true,
        'ordered_attributes' => ['order' => [Package::class], 'sort_algorithm' => 'custom'],
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
        'php_unit_test_case_static_method_calls' => ['methods' => [
            'any' => 'this',
            'never' => 'this',
            'atLeast' => 'this',
            'atLeastOnce' => 'this',
            'once' => 'this',
            'exactly' => 'this',
            'atMost' => 'this',
        ]],
        'self_accessor' => false,
        'single_line_throw' => false,
        'single_quote' => ['strings_containing_single_quote_chars' => true],
        'static_lambda' => false, // Would break places commented with `Do not declare closure as static`. If those are refactored, it could be enabled.
        'strict_comparison' => true,
        'strict_param' => true,
        'trailing_comma_in_multiline' => ['after_heredoc' => true, 'elements' => ['array_destructuring', 'arrays', 'match']],
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
    );
