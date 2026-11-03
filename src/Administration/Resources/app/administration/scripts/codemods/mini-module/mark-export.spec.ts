import { markExport } from './mark-export';

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
