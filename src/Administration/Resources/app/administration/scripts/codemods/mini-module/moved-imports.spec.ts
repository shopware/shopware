import { findSpecifiers, importedName, movedPath, rewriteMovedImports } from './moved-imports';

const origin = 'src/app/component/base/sw-icon';
const moves = new Map([
    [`${origin}/index.js`, `${origin}/src/sw-icon.js`],
    [`${origin}/sw-icon.html.twig`, `${origin}/src/sw-icon.html.twig`],
    [`${origin}/sw-icon.spec.js`, `${origin}/tests/sw-icon.spec.js`],
]);

describe('movedPath', () => {
    it('follows a moved file also when the import leaves out its extension or index', () => {
        // ACT
        const paths = [
            movedPath(moves, origin),
            movedPath(moves, `${origin}/sw-icon.html.twig`),
            movedPath(moves, 'src/app/component/base/sw-other'),
        ];

        // ASSERT
        expect(paths).toEqual([
            `${origin}/src/sw-icon.js`,
            `${origin}/src/sw-icon.html.twig`,
            'src/app/component/base/sw-other',
        ]);
    });
});

describe('importedName', () => {
    it('names the directory of an index file, which an import leaves out', () => {
        // ACT
        const names = [
            importedName('src/m/subcomponents/sw-check-list/index.ts'),
            importedName('src/m/sw-tree/fixtures/treeItems.js'),
        ];

        // ASSERT
        expect(names).toEqual([
            'sw-check-list',
            'treeItems',
        ]);
    });
});

describe('findSpecifiers', () => {
    it('finds static, dynamic, required and mocked paths, without their queries', () => {
        // ARRANGE
        const source = `import a from '../a';
import './b.scss';
export { c } from 'src/c';
jest.mock('src/d');
const e = () => import(\`./icons/\${name}.svg?raw\`);
import f from 'pinia';
`;

        // ACT
        const specifiers = findSpecifiers(source);

        // ASSERT
        expect(specifiers).toEqual([
            '../a',
            './b.scss',
            'src/c',
            'src/d',
            './icons/',
        ]);
    });
});

describe('rewriteMovedImports', () => {
    it('rewrites the paths of a moved file for its new depth, with queries and into node_modules', () => {
        // ARRANGE
        const source = `import template from './sw-icon.html.twig';
import raw from './../../../../../node_modules/kit/icons/regular/circle-download.svg?raw';
import Criteria from 'src/core/data/criteria.data';

const load = (variant, name) => import(\`./../../../../../node_modules/kit/icons/\${variant}/\${name}.svg?raw\`);
`;

        // ACT
        const rewritten = rewriteMovedImports(source, { moves, from: `${origin}/index.js`, to: `${origin}/src/sw-icon.js` });

        // ASSERT
        expect(rewritten).toBe(`import template from './sw-icon.html.twig';
import raw from '../../../../../../node_modules/kit/icons/regular/circle-download.svg?raw';
import Criteria from 'src/core/data/criteria.data';

const load = (variant, name) => import(\`../../../../../../node_modules/kit/icons/\${variant}/\${name}.svg?raw\`);
`);
    });

    it('points a moved spec at the component in src/', () => {
        // ARRANGE
        const source = "import SwIcon from './index';\nimport 'src/app/component/base/sw-icon';\n";

        // ACT
        const rewritten = rewriteMovedImports(source, {
            moves,
            from: `${origin}/sw-icon.spec.js`,
            to: `${origin}/tests/sw-icon.spec.js`,
        });

        // ASSERT
        expect(rewritten).toBe(
            "import SwIcon from '../src/sw-icon';\nimport 'src/app/component/base/sw-icon/src/sw-icon';\n",
        );
    });

    it('imports the private.ts of a private module for a bare import of its directory elsewhere, saying why', () => {
        // ARRANGE
        const source = `import 'src/app/component/base/sw-icon';
import '../../../app/component/base/sw-icon/index';
import 'src/app/component/base/sw-icon-other';
`;
        const file = 'src/module/sw-x/page/sw-x.spec.js';

        // ACT
        const rewritten = rewriteMovedImports(source, {
            moves,
            from: file,
            to: file,
            entry: { directory: origin, name: 'sw-icon', isPrivate: true },
        });

        // ASSERT
        expect(rewritten)
            .toBe(`// eslint-disable-next-line sw-core-rules/mini-module-boundaries -- a bare import that registers nothing, as before the module moved
import 'src/app/component/base/sw-icon/private';
// eslint-disable-next-line sw-core-rules/mini-module-boundaries -- a bare import that registers nothing, as before the module moved
import '../../../app/component/base/sw-icon/private';
import 'src/app/component/base/sw-icon-other';
`);
    });

    it('keeps a bare import of a public module, and takes the component from src/ for any other use, saying why', () => {
        // ARRANGE
        const source = `import 'src/app/component/base/sw-icon';
import SwIcon from 'src/app/component/base/sw-icon';
import {
    a,
} from 'src/app/component/base/sw-icon/index.js';

jest.mock('src/app/component/base/sw-icon');
`;
        const file = 'src/module/sw-x/page/sw-x.spec.js';

        // ACT
        const rewritten = rewriteMovedImports(source, {
            moves,
            from: file,
            to: file,
            entry: { directory: origin, name: 'sw-icon', isPrivate: false },
        });

        // ASSERT
        expect(rewritten).toBe(`import 'src/app/component/base/sw-icon';
// eslint-disable-next-line sw-core-rules/mini-module-boundaries -- it uses the component itself, not its declaration
import SwIcon from 'src/app/component/base/sw-icon/src/sw-icon';
import {
    a,
// eslint-disable-next-line sw-core-rules/mini-module-boundaries -- it uses the component itself, not its declaration
} from 'src/app/component/base/sw-icon/src/sw-icon';

jest.mock('src/app/component/base/sw-icon/src/sw-icon');
`);
    });
});
