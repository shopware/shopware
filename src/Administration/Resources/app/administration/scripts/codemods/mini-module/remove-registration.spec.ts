import { dropEmptyLintBlocks, dropUnusedDestructures, removeRegistration } from './remove-registration';

describe('removeRegistration', () => {
    it('keeps the doc comment with the package when the file has no other one', () => {
        // ARRANGE
        const code =
            "/**\n * @private\n * @sw-package discovery\n */\nShopware.Component.register('sw-x', () => import('./x'));\n";

        // ACT
        const result = removeRegistration(code, { start: 0, end: code.length });

        // ASSERT
        expect(result).toBe('/**\n * @private\n * @sw-package discovery\n */\n');
    });

    it('drops the doc comment of the registration when the file keeps another package doc', () => {
        // ARRANGE
        const first = "/**\n * @sw-package discovery\n */\nShopware.Component.register('sw-x', () => import('./x'));\n";
        const code = `${first}/**\n * @sw-package discovery\n */\nShopware.Component.register('sw-y', () => import('./y'));\n`;

        // ACT
        const result = removeRegistration(code, { start: 0, end: first.length });

        // ASSERT
        expect(result).toBe(
            "/**\n * @sw-package discovery\n */\nShopware.Component.register('sw-y', () => import('./y'));\n",
        );
    });
});

describe('dropEmptyLintBlocks', () => {
    it('removes an eslint-disable that nothing follows, or only its eslint-enable', () => {
        // ARRANGE
        const codes = [
            "import './x';\n\n/* eslint-disable sw-deprecation-rules/private-feature-declarations */\n",
            "import './x';\n/* eslint-disable max-len */\n/* eslint-enable max-len */\nconst a = 1;\n",
            '/* eslint-disable max-len */\nconst a = 1;\n',
        ];

        // ACT
        const results = codes.map(dropEmptyLintBlocks);

        // ASSERT
        expect(results).toEqual([
            "import './x';\n",
            "import './x';\nconst a = 1;\n",
            '/* eslint-disable max-len */\nconst a = 1;\n',
        ]);
    });
});

describe('dropEmptyLintBlocks with private-feature-declarations', () => {
    it('removes the disables of the rule that no longer cover a registration it reports', () => {
        // ARRANGE
        const rule = 'sw-deprecation-rules/private-feature-declarations';
        const codes = [
            `import './a/register';\n\n/* eslint-disable ${rule} */\nShopware.Component.extend('sw-b', 'sw-a', () => import('./b'));\n`,
            `/* eslint-disable ${rule} */\nShopware.Component.register('sw-b', () => import('./b'));\n`,
            `/* eslint-disable-next-line ${rule} */\n/*\n * @sw-package discovery\n */\nconst a = 1;\n`,
            `// eslint-disable-next-line ${rule}\nShopware.Module.register('sw-b', {});\n`,
            `/* eslint-disable max-len, ${rule} */\nShopware.Component.extend('sw-b', 'sw-a', () => import('./b'));\n`,
        ];

        // ACT
        const results = codes.map(dropEmptyLintBlocks);

        // ASSERT
        expect(results).toEqual([
            "import './a/register';\n\nShopware.Component.extend('sw-b', 'sw-a', () => import('./b'));\n",
            codes[1],
            '/*\n * @sw-package discovery\n */\nconst a = 1;\n',
            codes[3],
            codes[4],
        ]);
    });
});

describe('dropUnusedDestructures', () => {
    it('removes the names of a destructure of the global that nothing reads', () => {
        // ARRANGE
        const codes = [
            "const { Component } = Shopware;\nimport './x';\n",
            "const { Component, Module } = Shopware;\nModule.register('x', {});\n",
        ];

        // ACT
        const results = codes.map(dropUnusedDestructures);

        // ASSERT
        expect(results).toEqual(["import './x';\n", "const { Module } = Shopware;\nModule.register('x', {});\n"]);
    });
});
