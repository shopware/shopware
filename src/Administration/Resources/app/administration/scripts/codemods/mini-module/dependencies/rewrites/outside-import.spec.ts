import { routeForSpec } from '../fake-index';
import { rewriteOutsideImports } from './outside-import';

describe('rewriteOutsideImports', () => {
    it('moves imports from outside the module into dependencies.ts, relative paths from there', () => {
        // ARRANGE
        const source = `import { mapState } from 'pinia';
import type { ServiceDescription } from '../../../service/shopware-services.service';
import extractError from '../../../composables/extract-error';
import template from './sw-x-page.html.twig';
import './sw-x-page.scss';
import SwXCard from './sw-x-card/private';
import 'src/app/component/base/sw-card';

export default {};
`;

        // ACT
        const { code, dependencies } = routeForSpec(
            source,
            { file: [rewriteOutsideImports] },
            '/admin/src/module/sw-x/page/sw-x-page/src/sw-x-page.ts',
        );

        // ASSERT
        expect(code).toBe(`import template from './sw-x-page.html.twig';
import './sw-x-page.scss';
import SwXCard from './sw-x-card/private';
import 'src/app/component/base/sw-card';
import { extractError, mapState, type ServiceDescription } from '../dependencies';

export default {};
`);
        expect(dependencies).toBe(`/** @private */
export { mapState } from 'pinia';
/** @private */
export { default as extractError } from '../../composables/extract-error';
/** @private */
export type { ServiceDescription } from '../../service/shopware-services.service';
`);
    });
});
