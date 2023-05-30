import UtilService from './util.service';

describe('src/core/service/util.service.spec.js', () => {
    describe('moveItem', () => {
        let entity = new MutationObserver(() => {});

        beforeEach(() => {
            entity = [
                { id: 1 },
                { id: 2 },
                { id: 3 },
                { id: 4 },
            ];
        });

        it('should move the item correctly', () => {
            const oldIndex = 1;
            const newIndex = 3;

            UtilService.moveItem(entity, oldIndex, newIndex);

            expect(entity).toEqual([
                { id: 1 },
                { id: 3 },
                { id: 4 },
                { id: 2 },
            ]);
        });

        it('should not move the item if the new index is null', () => {
            const oldIndex = 2;
            const newIndex = null;

            UtilService.moveItem(entity, oldIndex, newIndex);

            expect(entity).toEqual([
                { id: 1 },
                { id: 2 },
                { id: 4 },
                { id: 3 },
            ]);
        });

        it('should not move the item if the old index is out of bounds', () => {
            const oldIndex = -1;
            const newIndex = 2;

            UtilService.moveItem(entity, oldIndex, newIndex);

            expect(entity).toEqual([
                { id: 1 },
                { id: 2 },
                { id: 3 },
                { id: 4 },
            ]);
        });

        it('should not move the item if the new index is the same as the old index', () => {
            const oldIndex = 2;
            const newIndex = 2;

            UtilService.moveItem(entity, oldIndex, newIndex);

            expect(entity).toEqual([
                { id: 1 },
                { id: 2 },
                { id: 3 },
                { id: 4 },
            ]);
        });

        it('should not move the item if it does not exist', () => {
            const oldIndex = 10;
            const newIndex = 2;

            UtilService.moveItem(entity, oldIndex, newIndex);

            expect(entity).toEqual([
                { id: 1 },
                { id: 2 },
                { id: 3 },
                { id: 4 },
            ]);
        });

        it('should not move the item if entity does not exist', () => {
            entity = [null, 1, null];
            const oldIndex = 0;
            const newIndex = 1;

            UtilService.moveItem(entity, oldIndex, newIndex);

            expect(entity).toEqual([null, 1, null]);
        });
    });
});

