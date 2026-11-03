const { filterMigrations, getSelectedMigrationIds, isMigrationSelected, selectedUsages } = require('./filter-migrations');
const { selectDeprecationIds } = require('../test-utils/selected-deprecation-ids');

const migrations = [
    { id: 'a' },
    { id: 'b' },
    { id: 'c' },
];

describe('deprecation id selection', () => {
    let restore = () => {};

    function selectIds(value) {
        restore = selectDeprecationIds(value);
    }

    afterEach(() => {
        restore();
    });

    it('selects every migration when no ids are set', () => {
        selectIds(undefined);

        expect(getSelectedMigrationIds()).toEqual([]);
        expect(isMigrationSelected({ id: 'anything' })).toBe(true);
        expect(filterMigrations(migrations)).toEqual(migrations);
    });

    it('keeps only the migrations whose trimmed ids are listed', () => {
        selectIds('a, b');

        expect(getSelectedMigrationIds()).toEqual([
            'a',
            'b',
        ]);
        expect(filterMigrations(migrations)).toEqual([
            { id: 'a' },
            { id: 'b' },
        ]);
    });

    it('ignores empty segments in the id list', () => {
        selectIds(',a,,  ,');

        expect(getSelectedMigrationIds()).toEqual(['a']);
    });

    it('treats a list of only commas and whitespace as no selection', () => {
        selectIds(' , ,  ');

        expect(getSelectedMigrationIds()).toEqual([]);
        expect(isMigrationSelected({ id: 'anything' })).toBe(true);
        expect(filterMigrations(migrations)).toEqual(migrations);
    });

    it('flattens the selected migrations into their usage steps, optionally of one kind', () => {
        const rename = { kind: 'rename', from: 'x' };
        const remove = { kind: 'remove', from: 'y' };
        const withUsages = [
            { id: 'a', usage: [rename, remove] },
            { id: 'b', usage: [rename] },
        ];
        selectIds('a');

        expect(selectedUsages(withUsages)).toEqual([
            { migration: withUsages[0], usage: rename },
            { migration: withUsages[0], usage: remove },
        ]);
        expect(selectedUsages(withUsages, 'remove')).toEqual([
            { migration: withUsages[0], usage: remove },
        ]);
    });
});
