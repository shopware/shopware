import { markPublic } from './mark-public';

describe('markPublic', () => {
    it('adds @public to the doc comment of the export and drops the suppression', () => {
        // ARRANGE
        const source = `/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {};
`;

        // ACT
        const marked = markPublic(source);

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
        const marked = markPublic('/**\n * @private\n */\nexport default {};\n');

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
        const marked = markPublic(source);

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
});
