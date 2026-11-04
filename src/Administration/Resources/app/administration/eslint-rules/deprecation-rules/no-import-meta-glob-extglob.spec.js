const { RuleTester } = require('eslint');
const rule = require('./no-import-meta-glob-extglob');

const ruleTester = new RuleTester({
    languageOptions: { ecmaVersion: 2022, sourceType: 'module' },
});

const message = (pattern, replacement) => `Vite 8, the default from Shopware 6.8 on, does not match the extglob pattern "${pattern}" in import.meta.glob. Use ${replacement} instead.`;

ruleTester.run('no-import-meta-glob-extglob', rule, {
    valid: [
        {
            name: 'allows globs without extglobs',
            code: "import.meta.glob('./**/*.ts', { eager: true });",
        },
        {
            name: 'allows negated patterns',
            code: "import.meta.glob(['./**/*.{j,t}s', '!./**/*.spec.{j,t}s'], { eager: true });",
        },
        {
            name: 'ignores dynamic patterns',
            code: 'import.meta.glob(`./${folder}/!(*.spec).ts`);',
        },
        {
            name: 'ignores extglobs outside of import.meta.glob',
            code: "const pattern = './**/!(*.spec).ts'; glob('./**/!(*.spec).ts');",
        },
    ],
    invalid: [
        {
            name: 'rewrites a single pattern into an array and keeps the options',
            code: "const context = import.meta.glob('./**/!(*.spec).{j,t}s', { eager: true });",
            output: "const context = import.meta.glob(['./**/*.{j,t}s', '!./**/*.spec.{j,t}s'], { eager: true });",
            errors: [{ message: message('./**/!(*.spec).{j,t}s', "['./**/*.{j,t}s', '!./**/*.spec.{j,t}s']") }],
        },
        {
            name: 'keeps the quotes of the pattern',
            code: 'import.meta.glob("./**/!(*.spec).ts");',
            output: 'import.meta.glob(["./**/*.ts", "!./**/*.spec.ts"]);',
            errors: 1,
        },
        {
            name: 'rewrites a template literal without expressions',
            code: 'import.meta.glob(`./**/!(*.spec).ts`);',
            output: 'import.meta.glob([`./**/*.ts`, `!./**/*.spec.ts`]);',
            errors: 1,
        },
        {
            name: 'rewrites alternations into a brace expansion',
            code: "import.meta.glob('./**/!(*.spec|*.test).ts');",
            output: "import.meta.glob(['./**/*.ts', '!./**/{*.spec,*.test}.ts']);",
            errors: 1,
        },
        {
            name: 'rewrites the extglob patterns of an array in place',
            code: "import.meta.glob(['./**/*.js', './sw-login/index!(*.spec).ts', '!./**/!(*.spec).twig']);",
            output: "import.meta.glob(['./**/*.js', './sw-login/index*.ts', '!./sw-login/index*.spec.ts', '!./**/*.twig']);",
            errors: 2,
        },
        {
            name: 'reports extglobs it cannot rewrite without a fix',
            code: "import.meta.glob('./**/@(index|main).ts');",
            output: null,
            errors: [{ message: 'Vite 8, the default from Shopware 6.8 on, does not match the extglob pattern "./**/@(index|main).ts" in import.meta.glob. Replace it with negated patterns.' }],
        },
    ],
});
