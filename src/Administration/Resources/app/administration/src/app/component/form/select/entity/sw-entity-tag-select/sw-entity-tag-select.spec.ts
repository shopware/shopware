/**
 * @sw-package framework
 */

import swEntityTagSelect from './index';

type CheckTagExistsContext = {
    tagExists: boolean;
    context: object;
    repository: { searchIds: jest.Mock };
};

function createContext(total: number): CheckTagExistsContext {
    return {
        tagExists: true,
        context: {},
        repository: {
            searchIds: jest.fn(() => Promise.resolve({ total, data: [] })),
        },
    };
}

describe('src/app/component/form/select/entity/sw-entity-tag-select', () => {
    const { checkTagExists } = swEntityTagSelect.methods as {
        checkTagExists: (this: CheckTagExistsContext, term: string) => Promise<void>;
    };

    it('should mark the tag as existing when an id is found', async () => {
        const context = createContext(1);

        await checkTagExists.call(context, 'summer');

        expect(context.repository.searchIds).toHaveBeenCalledTimes(1);
        expect(context.tagExists).toBe(true);
    });

    it('should mark the tag as missing when no id is found', async () => {
        const context = createContext(0);

        await checkTagExists.call(context, 'summer');

        expect(context.tagExists).toBe(false);
    });

    it('should not search for an empty term', async () => {
        const context = createContext(0);

        await checkTagExists.call(context, '   ');

        expect(context.repository.searchIds).not.toHaveBeenCalled();
        expect(context.tagExists).toBe(true);
    });
});
