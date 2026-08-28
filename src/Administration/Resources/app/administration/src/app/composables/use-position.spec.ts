/**
 * @sw-package framework
 */
import usePosition from './use-position';

type Item = { id: string; position: number };

function collection(items: Item[]): never {
    return items as unknown as never;
}

describe('src/app/composables/use-position', () => {
    it('changePosition ASC swaps the selected item with its lower neighbour', () => {
        const { changePosition } = usePosition();
        const items: Item[] = [
            { id: 'a', position: 1 },
            { id: 'b', position: 2 },
        ];

        changePosition(collection(items), items[1] as never, 'position', 'ASC');

        expect(items.find((item) => item.id === 'a')?.position).toBe(2);
        expect(items.find((item) => item.id === 'b')?.position).toBe(1);
    });

    it('changePosition is a no-op at the boundary', () => {
        const { changePosition } = usePosition();
        const items: Item[] = [
            { id: 'a', position: 1 },
            { id: 'b', position: 2 },
        ];

        changePosition(collection(items), items[0] as never, 'position', 'ASC');

        expect(items[0].position).toBe(1);
        expect(items[1].position).toBe(2);
    });

    it('lowerPositionValue / raisePositionValue delegate to changePosition', () => {
        const { lowerPositionValue, raisePositionValue } = usePosition();
        const items: Item[] = [
            { id: 'a', position: 1 },
            { id: 'b', position: 2 },
        ];

        raisePositionValue(collection(items), items[0] as never);
        expect(items.find((item) => item.id === 'a')?.position).toBe(2);

        lowerPositionValue(collection(items), items.find((item) => item.id === 'a') as never);
        expect(items.find((item) => item.id === 'a')?.position).toBe(1);
    });

    it('getSiblingIndex / getSibling return the neighbour, or -1/null at the boundary', () => {
        const { getSiblingIndex, getSibling } = usePosition();
        const items: Item[] = [
            { id: 'a', position: 1 },
            { id: 'b', position: 2 },
        ];

        expect(getSiblingIndex(collection(items), items[1] as never, 'position', 'ASC')).toBe(0);
        expect(getSibling(collection(items), items[1] as never, 'position', 'ASC')).toBe(items[0]);
        expect(getSiblingIndex(collection(items), items[0] as never, 'position', 'ASC')).toBe(-1);
        expect(getSibling(collection(items), items[0] as never, 'position', 'ASC')).toBeNull();
    });

    it('renumberPositions renumbers incrementally from startIndex', () => {
        const { renumberPositions } = usePosition();
        const items: Item[] = [
            { id: 'a', position: 5 },
            { id: 'b', position: 9 },
        ];

        renumberPositions(collection(items), 0);

        expect(items[0].position).toBe(0);
        expect(items[1].position).toBe(1);
    });

    it('getNewPosition returns max + 1, or 1 when there is no aggregation result', async () => {
        const { getNewPosition } = usePosition();
        const search = jest.fn().mockResolvedValue({ aggregations: { maxPosition: { max: '4' } } });
        const repository = { search } as never;
        const criteria = { addAggregation: () => criteria, addSorting: () => criteria } as never;

        await expect(getNewPosition(repository, criteria, {} as never)).resolves.toBe(5);

        search.mockResolvedValue({ aggregations: { maxPosition: { max: null } } });
        await expect(getNewPosition(repository, criteria, {} as never)).resolves.toBe(1);
    });
});
