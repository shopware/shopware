import { findLeftovers } from './leftovers';

describe('findLeftovers', () => {
    it('lists the lines that still reach outside the module, as the lint does', () => {
        // ARRANGE
        const source = `import template from './sw-x.html.twig';
import { Criteria } from '../dependencies';
import Other from '../../other';
const t = Shopware.Snippet.t('x');
export default {
    inject: [
        'swProductDetailLoadAll',
    ],
};
`;

        // ACT
        const leftovers = findLeftovers(source, '/admin/m/src/sw-x.js', '/admin/m');

        // ASSERT
        expect(leftovers).toEqual([
            "3: import Other from '../../other';",
            "4: const t = Shopware.Snippet.t('x');",
            "7: 'swProductDetailLoadAll',",
        ]);
    });
});
