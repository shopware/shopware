import { getWorkspaceCreatorName } from './workspace-creator.util';

describe('workspace creator names', () => {
    it.each([
        { creator: { firstName: 'Ada', lastName: 'Lovelace', username: 'ada' }, expected: 'Ada Lovelace' },
        { creator: { firstName: ' Ada ', lastName: ' Lovelace ', username: 'ada' }, expected: 'Ada Lovelace' },
        { creator: { firstName: 'Ada', lastName: '', username: 'ada' }, expected: 'Ada' },
        { creator: { firstName: '', lastName: 'Lovelace', username: 'ada' }, expected: 'Lovelace' },
        { creator: { firstName: ' ', lastName: '', username: ' ada ' }, expected: 'ada' },
        { creator: { firstName: '', lastName: '', username: '' }, expected: '' },
        { creator: null, expected: '' },
        { creator: undefined, expected: '' },
    ])('formats $creator as "$expected"', ({ creator, expected }) => {
        expect(getWorkspaceCreatorName({ createdBy: creator })).toBe(expected);
    });
});
