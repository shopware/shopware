import { isDocumentedPrivate, isDocumentedPublic, markExport } from './mark-export';

describe('markExport', () => {
    it('adds @public to the doc comment of the export and drops the suppression', () => {
        // ARRANGE
        const source = `/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {};
`;

        // ACT
        const marked = markExport(source, '@public');

        // ASSERT
        expect(marked).toBe(`/**
 * @sw-package discovery
 *
 * @public
 */
export default {};
`);
    });

    it('turns @private into @public', () => {
        // ACT
        const marked = markExport('/**\n * @private\n */\nexport default {};\n', '@public');

        // ASSERT
        expect(marked).toBe('/**\n * @public\n */\nexport default {};\n');
    });

    it('gives an export without a doc comment one', () => {
        // ARRANGE
        const source = `/**
 * @sw-package inventory
 */
import template from './sw-x.html.twig';

export default { template };
`;

        // ACT
        const marked = markExport(source, '@public');

        // ASSERT
        expect(marked).toBe(`/**
 * @sw-package inventory
 */
import template from './sw-x.html.twig';

/**
 * @public
 */
export default { template };
`);
    });

    it('marks the component of a nested private module @private, and keeps a @private it has', () => {
        // ACT
        const marked = [
            markExport('/**\n * @public\n */\nexport default {};\n', '@private'),
            markExport('/**\n * @private\n */\nexport default {};\n', '@private'),
        ];

        // ASSERT
        expect(marked).toEqual([
            '/**\n * @private\n */\nexport default {};\n',
            '/**\n * @private\n */\nexport default {};\n',
        ]);
    });
});

describe('isDocumentedPrivate', () => {
    it('reads @private from the doc comment of the default export, also with a reason after it', () => {
        // ARRANGE
        const sources = [
            '/**\n * @sw-package after-sales\n *\n * @private might get removed\n */\nexport default Shopware.Component.wrapComponentConfig({});\n',
            '/**\n * @private\n */\n// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations\nexport default {};\n',
            '/**\n * @private\n * @sw-package framework\n */\nimport template from "./x.html.twig";\n\n/**\n * @public\n */\nexport default { template };\n',
            '// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations\nexport default {};\n',
        ];

        // ACT
        const results = sources.map(isDocumentedPrivate);

        // ASSERT
        expect(results).toEqual([
            true,
            true,
            false,
            false,
        ]);
    });
});

describe('isDocumentedPublic', () => {
    it('reads @public from the doc comment of the default export only', () => {
        // ARRANGE
        const sources = [
            '/**\n * @public\n * @sw-package fundamentals@after-sales\n */\n// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations\nexport default {};\n',
            '/**\n * @private\n */\nexport default {};\n',
            '/**\n * @public\n */\nimport template from "./x.html.twig";\n\nexport default { template };\n',
        ];

        // ACT
        const results = sources.map(isDocumentedPublic);

        // ASSERT
        expect(results).toEqual([
            true,
            false,
            false,
        ]);
    });
});
