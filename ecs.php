<?php declare(strict_types=1);

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
use PhpCsFixer\Fixer\Operator\ConcatSpaceFixer;
use PhpCsFixer\Fixer\Operator\OperatorLinebreakFixer;
use PhpCsFixer\Fixer\Phpdoc\GeneralPhpdocAnnotationRemoveFixer;
use PhpCsFixer\Fixer\Phpdoc\NoSuperfluousPhpdocTagsFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocIndentFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocLineSpanFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocOrderFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocSummaryFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocTrimConsecutiveBlankLineSeparationFixer;
use PhpCsFixer\Fixer\PhpTag\BlankLineAfterOpeningTagFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitConstructFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitDedicateAssertFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitDedicateAssertInternalTypeFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitMockFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitMockShortWillReturnFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitTestCaseStaticMethodCallsFixer;
use PhpCsFixer\Fixer\ReturnNotation\NoUselessReturnFixer;
use PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer;
use PhpCsFixer\Fixer\StringNotation\ExplicitStringVariableFixer;
use PhpCsFixer\Fixer\Whitespace\BlankLineBeforeStatementFixer;
use PhpCsFixer\Fixer\Whitespace\CompactNullableTypehintFixer;
use PhpCsFixerCustomFixers\Fixer\NoImportFromGlobalNamespaceFixer;
use PhpCsFixerCustomFixers\Fixer\NoSuperfluousConcatenationFixer;
use PhpCsFixerCustomFixers\Fixer\NoUselessCommentFixer;
use PhpCsFixerCustomFixers\Fixer\NoUselessParenthesisFixer;
use PhpCsFixerCustomFixers\Fixer\NoUselessStrlenFixer;
use PhpCsFixerCustomFixers\Fixer\SingleSpaceAfterStatementFixer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symplify\CodingStandard\Fixer\ArrayNotation\ArrayListItemNewlineFixer;
use Symplify\CodingStandard\Fixer\ArrayNotation\ArrayOpenerAndCloserNewlineFixer;
use Symplify\CodingStandard\Fixer\ArrayNotation\StandaloneLineInMultilineArrayFixer;
use Symplify\EasyCodingStandard\ValueObject\Option;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->import(SetList::SYMFONY);
    $containerConfigurator->import(SetList::SYMFONY_RISKY);
    $containerConfigurator->import(SetList::ARRAY);
    $containerConfigurator->import(SetList::CONTROL_STRUCTURES);
    $containerConfigurator->import(SetList::STRICT);
    $containerConfigurator->import(SetList::PSR_12);

    $services = $containerConfigurator->services();

    $services->set(ModernizeTypesCastingFixer::class);
    $services->set(ClassAttributesSeparationFixer::class)
        ->call('configure', [['elements' => ['property' => 'one', 'method' => 'one']]]);
    $services->set(FopenFlagsFixer::class);
    $services->set(MethodArgumentSpaceFixer::class)
        ->call('configure', [['on_multiline' => 'ensure_fully_multiline']]);
    $services->set(NativeFunctionInvocationFixer::class)
        ->call('configure', [[
            'include' => [NativeFunctionInvocationFixer::SET_COMPILER_OPTIMIZED],
            'scope' => 'namespaced',
            'strict' => false,
        ]]);
    $services->set(NativeConstantInvocationFixer::class);
    $services->set(NullableTypeDeclarationForDefaultNullValueFixer::class);
    $services->set(VoidReturnFixer::class);
    $services->set(ConcatSpaceFixer::class)
        ->call('configure', [['spacing' => 'one']]);
    $services->set(OperatorLinebreakFixer::class);
    $services->set(GeneralPhpdocAnnotationRemoveFixer::class)
        ->call('configure', [['annotations' => ['copyright', 'category']]]);
    $services->set(NoSuperfluousPhpdocTagsFixer::class)
        ->call('configure', [['allow_unused_params' => true]]);
    $services->set(PhpdocLineSpanFixer::class);
    $services->set(PhpdocOrderFixer::class);
    $services->set(PhpUnitConstructFixer::class);
    $services->set(PhpUnitDedicateAssertFixer::class)
        ->call('configure', [['target' => 'newest']]);
    $services->set(PhpUnitDedicateAssertInternalTypeFixer::class);
    $services->set(PhpUnitMockFixer::class);
    $services->set(PhpUnitMockShortWillReturnFixer::class);
    $services->set(PhpUnitTestCaseStaticMethodCallsFixer::class);
    $services->set(NoUselessReturnFixer::class);
    $services->set(DeclareStrictTypesFixer::class);
    $services->set(BlankLineBeforeStatementFixer::class);
    $services->set(CompactNullableTypehintFixer::class);
    $services->set(NoImportFromGlobalNamespaceFixer::class);
    $services->set(NoSuperfluousConcatenationFixer::class);
    $services->set(NoUselessCommentFixer::class);
    $services->set(SingleSpaceAfterStatementFixer::class);
    $services->set(NoUselessParenthesisFixer::class);
    $services->set(NoUselessStrlenFixer::class);

    $parameters = $containerConfigurator->parameters();

    $parameters->set(Option::CACHE_DIRECTORY, 'var/cache/cs_fixer');
    $parameters->set(Option::CACHE_NAMESPACE, 'platform');

    $parameters->set(Option::SKIP, [
        ArrayOpenerAndCloserNewlineFixer::class => null,
        ArrayListItemNewlineFixer::class => null,
        SingleLineThrowFixer::class => null,
        SelfAccessorFixer::class => null,
        ExplicitIndirectVariableFixer::class => null,
        BlankLineAfterOpeningTagFixer::class => null,
        PhpdocSummaryFixer::class => null,
        ExplicitStringVariableFixer::class => null,
        StandaloneLineInMultilineArrayFixer::class => null,
        // would otherwise destroy the example in the annotation
        NoUselessCommentFixer::class => ['src/Core/System/Annotation/Concept/DeprecationPattern/ReplaceDecoratedInterface.php'],
        // Would otherwise fix the blocking whitespace in the currency formatter tests
        NonPrintableCharacterFixer::class => ['src/Core/System/Test/Currency/CurrencyFormatterTest.php'],
        // skip php files in node modules (stylelint ships both js and php)
        '**/node_modules',
        // would otherwise destroy markdown in the description of a route annotation, since markdown interpreted spaces/indents
        PhpdocIndentFixer::class => [
            'src/**/*Controller.php',
            'src/**/*Route.php'
        ],
        // would otherwise remove lines in the description of route annotations
        PhpdocTrimConsecutiveBlankLineSeparationFixer::class => [
            'src/**/*Controller.php',
            'src/**/*Route.php'
        ],
    ]);
};
