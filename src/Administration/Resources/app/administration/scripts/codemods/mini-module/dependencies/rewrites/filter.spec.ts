import { routeForSpec } from '../fake-index';
import { rewriteFilter } from './filter';

describe('rewriteFilter', () => {
    it('looks a filter up through a function in dependencies.ts and exports its name from the filter file', () => {
        // ARRANGE
        const source = `export default {
    computed: {
        dateFilter() {
            return Shopware.Filter.getByName('date');
        },
    },
};
`;

        // ACT
        const { code, dependencies, outsideEdits } = routeForSpec(source, { use: [rewriteFilter] });

        // ASSERT
        expect(code).toContain('return getDateFilter();');
        expect(dependencies).toBe(`import { dateFilterName } from 'src/app/filter/date.filter';

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);
`);
        expect(outsideEdits).toEqual([
            { kind: 'filter-name', file: '/admin/date.filter.ts', name: 'date', exportName: 'dateFilterName' },
        ]);
    });

    it('leaves a filter that no file registers', () => {
        // ARRANGE
        const source = "const filter = Shopware.Filter.getByName('unknown');\n";

        // ACT
        const { code, dependencies } = routeForSpec(source, { use: [rewriteFilter] });

        // ASSERT
        expect(code).toBe(source);
        expect(dependencies).toBe('\n');
    });
});
