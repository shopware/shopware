import { routeForSpec } from '../fake-index';
import { rewriteComponentHelper } from './component-helper';

describe('rewriteComponentHelper', () => {
    it('imports destructured helpers from the modules that define them, under the names the file uses', () => {
        // ARRANGE
        const source = `const { Component } = Shopware;
const { mapPropertyErrors, mapState: mapPiniaState } = Component.getComponentHelper();

export default {
    computed: {
        ...mapPropertyErrors('product', ['name']),
        ...mapPiniaState(useStore, ['x']),
    },
};
`;

        // ACT
        const { code, dependencies } = routeForSpec(source, { use: [rewriteComponentHelper] });

        // ASSERT
        expect(code).toBe(`import { mapPropertyErrors, mapState as mapPiniaState } from '../dependencies';

export default {
    computed: {
        ...mapPropertyErrors('product', ['name']),
        ...mapPiniaState(useStore, ['x']),
    },
};
`);
        expect(dependencies).toBe(`/** @private */
export { mapState } from 'pinia';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';
`);
    });

    it('leaves the destructure when one helper has no module it comes from', () => {
        // ARRANGE
        const source = 'const { mapPropertyErrors, test } = Shopware.Component.getComponentHelper();\n';

        // ACT
        const { code } = routeForSpec(source, { use: [rewriteComponentHelper] });

        // ASSERT
        expect(code).toBe(source);
    });
});
