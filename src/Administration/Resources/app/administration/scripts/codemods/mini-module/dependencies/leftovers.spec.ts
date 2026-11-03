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

    it('names an import that runs only for its side effect', () => {
        // ARRANGE
        const source = `import Ace from 'ace-builds/src-noconflict/ace';
import 'ace-builds/src-noconflict/mode-twig';
import './sw-x.scss';
`;

        // ACT
        const leftovers = findLeftovers(source, '/admin/m/src/sw-x.js', '/admin/m');

        // ASSERT
        expect(leftovers).toEqual([
            "1: import Ace from 'ace-builds/src-noconflict/ace';",
            "2: import 'ace-builds/src-noconflict/mode-twig'; (a side effect: it stays in this file, below the imports it needs)",
        ]);
    });
});
