<?php declare(strict_types=1);

use PHP_CodeSniffer\Standards\Generic\Sniffs\CodeAnalysis\AssignmentInConditionSniff;
use PhpCsFixer\Fixer\Basic\NonPrintableCharacterFixer;
use PhpCsFixer\Fixer\CastNotation\ModernizeTypesCastingFixer;
use PhpCsFixer\Fixer\ClassNotation\ClassAttributesSeparationFixer;
use PhpCsFixer\Fixer\ClassNotation\SelfAccessorFixer;
use PhpCsFixer\Fixer\ConstantNotation\NativeConstantInvocationFixer;
use PhpCsFixer\Fixer\FunctionNotation\FopenFlagsFixer;
use PhpCsFixer\Fixer\FunctionNotation\MethodArgumentSpaceFixer;
use PhpCsFixer\Fixer\FunctionNotation\NativeFunctionInvocationFixer;
use PhpCsFixer\Fixer\FunctionNotation\NullableTypeDeclarationForDefaultNullValueFixer;
use PhpCsFixer\Fixer\FunctionNotation\SingleLineThrowFixer;
use PhpCsFixer\Fixer\FunctionNotation\VoidReturnFixer;
use PhpCsFixer\Fixer\LanguageConstruct\ExplicitIndirectVariableFixer;
use PhpCsFixer\Fixer\Operator\BinaryOperatorSpacesFixer;
use PhpCsFixer\Fixer\Operator\ConcatSpaceFixer;
use PhpCsFixer\Fixer\Operator\OperatorLinebreakFixer;
use PhpCsFixer\Fixer\Phpdoc\GeneralPhpdocAnnotationRemoveFixer;
use PhpCsFixer\Fixer\Phpdoc\NoSuperfluousPhpdocTagsFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocAlignFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocAnnotationWithoutDotFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocIndentFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocLineSpanFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocNoPackageFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocOrderFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocSummaryFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocToCommentFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocTrimConsecutiveBlankLineSeparationFixer;
use PhpCsFixer\Fixer\PhpTag\BlankLineAfterOpeningTagFixer;
use PhpCsFixer\Fixer\PhpTag\LinebreakAfterOpeningTagFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitConstructFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitDedicateAssertFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitDedicateAssertInternalTypeFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitMockFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitMockShortWillReturnFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitTestCaseStaticMethodCallsFixer;
use PhpCsFixer\Fixer\ReturnNotation\NoUselessReturnFixer;
use PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer;
use PhpCsFixer\Fixer\StringNotation\ExplicitStringVariableFixer;
use PhpCsFixer\Fixer\StringNotation\SingleQuoteFixer;
use PhpCsFixer\Fixer\Whitespace\BlankLineBeforeStatementFixer;
use PhpCsFixer\Fixer\Whitespace\CompactNullableTypehintFixer;
use PhpCsFixerCustomFixers\Fixer\NoImportFromGlobalNamespaceFixer;
use PhpCsFixerCustomFixers\Fixer\NoSuperfluousConcatenationFixer;
use PhpCsFixerCustomFixers\Fixer\NoUselessCommentFixer;
use PhpCsFixerCustomFixers\Fixer\NoUselessParenthesisFixer;
use PhpCsFixerCustomFixers\Fixer\NoUselessStrlenFixer;
use PhpCsFixerCustomFixers\Fixer\PhpdocTypesCommaSpacesFixer;
use PhpCsFixerCustomFixers\Fixer\SingleSpaceAfterStatementFixer;
use Symplify\CodingStandard\Fixer\ArrayNotation\ArrayListItemNewlineFixer;
use Symplify\CodingStandard\Fixer\ArrayNotation\ArrayOpenerAndCloserNewlineFixer;
use Symplify\CodingStandard\Fixer\ArrayNotation\StandaloneLineInMultilineArrayFixer;
use Symplify\CodingStandard\Fixer\Spacing\StandaloneLineConstructorParamFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Option;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return static function (ECSConfig $ecsConfig): void {
    $ecsConfig->dynamicSets([
        '@Symfony',
        '@Symfony:risky',
    ]);

    $ecsConfig->sets([
        SetList::ARRAY,
        SetList::CONTROL_STRUCTURES,
        SetList::STRICT,
        SetList::PSR_12,
    ]);

    $ecsConfig->rules([
        ModernizeTypesCastingFixer::class,
        FopenFlagsFixer::class,
        NativeConstantInvocationFixer::class,
        NullableTypeDeclarationForDefaultNullValueFixer::class,
        VoidReturnFixer::class,
        OperatorLinebreakFixer::class,
        PhpdocLineSpanFixer::class,
        PhpdocOrderFixer::class,
        PhpUnitConstructFixer::class,
        PhpUnitDedicateAssertInternalTypeFixer::class,
        PhpUnitMockFixer::class,
        PhpUnitMockShortWillReturnFixer::class,
        PhpUnitTestCaseStaticMethodCallsFixer::class,
        NoUselessReturnFixer::class,
        DeclareStrictTypesFixer::class,
        BlankLineBeforeStatementFixer::class,
        CompactNullableTypehintFixer::class,
        NoImportFromGlobalNamespaceFixer::class,
        NoSuperfluousConcatenationFixer::class,
        NoUselessCommentFixer::class,
        SingleSpaceAfterStatementFixer::class,
        NoUselessParenthesisFixer::class,
        NoUselessStrlenFixer::class,
        PhpdocTypesCommaSpacesFixer::class,
        StandaloneLineConstructorParamFixer::class,
    ]);

    $ecsConfig->ruleWithConfiguration(ClassAttributesSeparationFixer::class, ['elements' => ['property' => 'one', 'method' => 'one']]);
    $ecsConfig->ruleWithConfiguration(MethodArgumentSpaceFixer::class, ['on_multiline' => 'ensure_fully_multiline']);
    $ecsConfig->ruleWithConfiguration(NativeFunctionInvocationFixer::class, [
        'include' => [NativeFunctionInvocationFixer::SET_COMPILER_OPTIMIZED],
        'scope' => 'namespaced',
        'strict' => false,
    ]);
    $ecsConfig->ruleWithConfiguration(ConcatSpaceFixer::class, ['spacing' => 'one']);
    $ecsConfig->ruleWithConfiguration(GeneralPhpdocAnnotationRemoveFixer::class, ['annotations' => ['copyright', 'category']]);
    $ecsConfig->ruleWithConfiguration(NoSuperfluousPhpdocTagsFixer::class, ['allow_unused_params' => true, 'allow_mixed' => true]);
    $ecsConfig->ruleWithConfiguration(PhpUnitDedicateAssertFixer::class, ['target' => 'newest']);
    $ecsConfig->ruleWithConfiguration(SingleQuoteFixer::class, ['strings_containing_single_quote_chars' => true]);
    // workaround for https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/issues/5495
    $ecsConfig->ruleWithConfiguration(BinaryOperatorSpacesFixer::class, [
        'operators' => [
            '|' => null,
            '&' => null,
        ],
    ]);

    $parameters = $ecsConfig->parameters();
    $parameters->set(Option::CACHE_DIRECTORY, $_SERVER['SHOPWARE_TOOL_CACHE_ECS'] ?? 'var/cache/cs_fixer');
    $parameters->set(Option::CACHE_NAMESPACE, 'platform');

    $ecsConfig->parallel();

    $ecsConfig->skip([
        // Fixture
        'src/WebInstaller/Tests/_fixtures/Options.php',

        ArrayOpenerAndCloserNewlineFixer::class => null,
        ArrayListItemNewlineFixer::class => null,
        SingleLineThrowFixer::class => null,
        SelfAccessorFixer::class => null,
        ExplicitIndirectVariableFixer::class => null,
        BlankLineAfterOpeningTagFixer::class => null,
        PhpdocSummaryFixer::class => null,
        ExplicitStringVariableFixer::class => null,
        StandaloneLineInMultilineArrayFixer::class => null,
        AssignmentInConditionSniff::class => null,
        PhpdocToCommentFixer::class => null,
        PhpdocAlignFixer::class => null,
        PhpdocAnnotationWithoutDotFixer::class => null,
        // would otherwise destroy the example in the annotation
        NoUselessCommentFixer::class => ['src/Core/System/Annotation/Concept/DeprecationPattern/ReplaceDecoratedInterface.php'],
        // Would otherwise fix the blocking whitespace in the currency formatter tests
        NonPrintableCharacterFixer::class => ['src/Core/System/Test/Currency/CurrencyFormatterTest.php'],
        // skip php files in node modules (stylelint ships both js and php)
        '**/node_modules',
        // would otherwise destroy markdown in the description of a route annotation, since markdown interpreted spaces/indents
        PhpdocIndentFixer::class => [
            'src/**/*Controller.php',
            'src/**/*Route.php',
        ],
        // would otherwise remove lines in the description of route annotations
        PhpdocTrimConsecutiveBlankLineSeparationFixer::class => [
            'src/**/*Controller.php',
            'src/**/*Route.php',
        ],
        PhpdocNoPackageFixer::class => null,
        StandaloneLineConstructorParamFixer::class => null,
        LinebreakAfterOpeningTagFixer::class => null,
    ]);
};
