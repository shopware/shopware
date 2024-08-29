/**
 * @package admin
 */
import 'src/app/mixin/position.mixin';
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount({
        template: `
            <div class="sw-mock">
              <slot></slot>
            </div>
        `,
        mixins: [
            Shopware.Mixin.getByName('position'),
        ],
        data() {
            return {
                name: 'sw-mock-field',
            };
        },
    }, {
        attachTo: document.body,
    });
}

describe('src/app/mixin/position.mixin.ts', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();

        await flushPromises();
    });

    afterEach(async () => {
        if (wrapper) {
            await wrapper.unmount();
        }

        await flushPromises();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should return a new position value using the current max position +1 starting with 1', async () => {
        const productRepositoryMock = {
            search: () => Promise.resolve({
                total: 1,
                aggregations: {
                    maxPosition: {
                        max: 7,
                    },
                },
            }),
        };
        const criteria = new Shopware.Data.Criteria();

        const result = await wrapper.vm.getNewPosition(
            productRepositoryMock,
            criteria,
        );

        expect(result).toBe(8);
    });

    it('should return a new position value when no maxPosition is defined', async () => {
        const productRepositoryMock = {
            search: () => Promise.resolve({
                total: 1,
                aggregations: {},
            }),
        };
        const criteria = new Shopware.Data.Criteria();

        const result = await wrapper.vm.getNewPosition(
            productRepositoryMock,
            criteria,
        );

        expect(result).toBe(1);
    });

    it('should lower the position value', async () => {
        const result = await wrapper.vm.lowerPositionValue(
            [
                {
                    id: '1a',
                    position: 1,
                },
                {
                    id: '2b',
                    position: 2,
                },
                {
                    id: '3c',
                    position: 3,
                },
            ],
            {
                id: '2b',
                position: 2,
            },
        );

        expect(result).toEqual([
            { id: '2b', position: 1 },
            { id: '1a', position: 2 },
            { id: '3c', position: 3 },
        ]);
    });

    it('should raise the position value', async () => {
        const result = await wrapper.vm.raisePositionValue(
            [
                {
                    id: '1a',
                    position: 1,
                },
                {
                    id: '2b',
                    position: 2,
                },
                {
                    id: '3c',
                    position: 3,
                },
            ],
            {
                id: '2b',
                position: 2,
            },
        );

        expect(result).toEqual([
            { id: '1a', position: 1 },
            { id: '3c', position: 2 },
            { id: '2b', position: 3 },
        ]);
    });

    it('should not change the position value when collection is <2', async () => {
        const result = await wrapper.vm.changePosition(
            [
                {
                    id: '1a',
                    position: 1,
                },
            ],
            {
                id: '2b',
                position: 2,
            },
        );

        expect(result).toEqual([
            { id: '1a', position: 1 },
        ]);
    });

    it('should not change the position value when item is first and direction is ASC', async () => {
        const result = await wrapper.vm.changePosition(
            [
                {
                    id: '1a',
                    position: 1,
                },
                {
                    id: '2b',
                    position: 2,
                },
                {
                    id: '3c',
                    position: 3,
                },
            ],
            {
                id: '1a',
                position: 1,
            },
            'position',
            'ASC',
        );

        expect(result).toEqual([
            { id: '1a', position: 1 },
            { id: '2b', position: 2 },
            { id: '3c', position: 3 },
        ]);
    });

    it('should not change the position value when item is last and direction is DESC', async () => {
        const result = await wrapper.vm.changePosition(
            [
                {
                    id: '1a',
                    position: 1,
                },
                {
                    id: '2b',
                    position: 2,
                },
                {
                    id: '3c',
                    position: 3,
                },
            ],
            {
                id: '3c',
                position: 3,
            },
            'position',
            'DESC',
        );

        expect(result).toEqual([
            { id: '1a', position: 1 },
            { id: '2b', position: 2 },
            { id: '3c', position: 3 },
        ]);
    });

    it('should change the position value', async () => {
        const result = await wrapper.vm.changePosition(
            [
                {
                    id: '1a',
                    position: 1,
                },
                {
                    id: '2b',
                    position: 2,
                },
                {
                    id: '3c',
                    position: 3,
                },
            ],
            {
                id: '2b',
                position: 2,
            },
            'position',
            'DESC',
        );

        expect(result).toEqual([
            { id: '1a', position: 1 },
            { id: '3c', position: 2 },
            { id: '2b', position: 3 },
        ]);
    });

    it('should return the sibling index (DESC)', async () => {
        const result = await wrapper.vm.getSiblingIndex(
            [
                {
                    id: '1a',
                    position: 1,
                },
                {
                    id: '2b',
                    position: 2,
                },
                {
                    id: '3c',
                    position: 3,
                },
            ],
            {
                id: '2b',
                position: 2,
            },
            'position',
            'DESC',
        );

        expect(result).toBe(2);
    });

    it('should return the sibling index (ASC)', async () => {
        const result = await wrapper.vm.getSiblingIndex(
            [
                {
                    id: '1a',
                    position: 1,
                },
                {
                    id: '2b',
                    position: 2,
                },
                {
                    id: '3c',
                    position: 3,
                },
            ],
            {
                id: '2b',
                position: 2,
            },
            'position',
            'ASC',
        );

        expect(result).toBe(0);
    });

    it('should return the sibling index -1 when collection is below 2', async () => {
        const result = await wrapper.vm.getSiblingIndex(
            [
                {
                    id: '1a',
                    position: 1,
                },
            ],
            {
                id: '1a',
                position: 1,
            },
        );

        expect(result).toBe(-1);
    });

    it('should return the sibling index -1 when item is first and ASC', async () => {
        const result = await wrapper.vm.getSiblingIndex(
            [
                {
                    id: '1a',
                    position: 1,
                },
                {
                    id: '2b',
                    position: 2,
                },
                {
                    id: '3c',
                    position: 3,
                },
            ],
            {
                id: '1a',
                position: 1,
            },
        );

        expect(result).toBe(-1);
    });

    it('should return the sibling index -1 when item is last and DESC', async () => {
        const result = await wrapper.vm.getSiblingIndex(
            [
                {
                    id: '1a',
                    position: 1,
                },
                {
                    id: '2b',
                    position: 2,
                },
                {
                    id: '3c',
                    position: 3,
                },
            ],
            {
                id: '3c',
                position: 3,
            },
            'position',
            'DESC',
        );

        expect(result).toBe(-1);
    });

    it('should return the sibling (DESC)', async () => {
        const result = await wrapper.vm.getSibling(
            [
                {
                    id: '1a',
                    position: 1,
                },
                {
                    id: '2b',
                    position: 2,
                },
                {
                    id: '3c',
                    position: 3,
                },
            ],
            {
                id: '2b',
                position: 2,
            },
            'position',
            'DESC',
        );

        expect(result).toEqual({
            id: '3c',
            position: 3,
        });
    });

    it('should return the sibling (ASC)', async () => {
        const result = await wrapper.vm.getSibling(
            [
                {
                    id: '1a',
                    position: 1,
                },
                {
                    id: '2b',
                    position: 2,
                },
                {
                    id: '3c',
                    position: 3,
                },
            ],
            {
                id: '2b',
                position: 2,
            },
            'position',
            'ASC',
        );

        expect(result).toEqual({
            id: '1a',
            position: 1,
        });
    });

    it('should return the sibling null when collection is below 2', async () => {
        const result = await wrapper.vm.getSibling(
            [
                {
                    id: '1a',
                    position: 1,
                },
            ],
            {
                id: '1a',
                position: 1,
            },
        );

        expect(result).toBeNull();
    });

    it('should return the sibling null when item is first and ASC', async () => {
        const result = await wrapper.vm.getSibling(
            [
                {
                    id: '1a',
                    position: 1,
                },
                {
                    id: '2b',
                    position: 2,
                },
                {
                    id: '3c',
                    position: 3,
                },
            ],
            {
                id: '1a',
                position: 1,
            },
        );

        expect(result).toBeNull();
    });

    it('should return the sibling null when item is last and DESC', async () => {
        const result = await wrapper.vm.getSibling(
            [
                {
                    id: '1a',
                    position: 1,
                },
                {
                    id: '2b',
                    position: 2,
                },
                {
                    id: '3c',
                    position: 3,
                },
            ],
            {
                id: '3c',
                position: 3,
            },
            'position',
            'DESC',
        );

        expect(result).toBeNull();
    });

    it('should renumber positions', async () => {
        const result = await wrapper.vm.renumberPositions(
            [
                {
                    id: '1a',
                    position: 1,
                },
                {
                    id: '2b',
                    position: 2,
                },
                {
                    id: '3c',
                    position: 3,
                },
            ],
            1,
            'position',
        );

        expect(result).toEqual([
            {
                id: '1a',
                position: 1,
            },
            {
                id: '2b',
                position: 2,
            },
            {
                id: '3c',
                position: 3,
            },
        ]);
    });
});
