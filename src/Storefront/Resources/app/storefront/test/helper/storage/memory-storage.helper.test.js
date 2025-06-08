import MemoryStorage from '../../../src/helper/storage/memory-storage.helper';

describe('memory-storage-helper', () => {
    test('it sets and overides items', () => {
        const storage = new MemoryStorage();

        storage.setItem('newItem', 5);

        expect(storage._storage.newItem).toStrictEqual(5);

        storage.setItem('newItem', 12);
        
        expect(storage._storage.newItem).toStrictEqual(12);
    });

    test('it returns null if a item is not found', () => {
        const storage = new MemoryStorage();
        
        expect(storage.getItem()).toBeNull();
        expect(storage.getItem('notSet')).toBeNull();
    });

    test('it can remove items', () => {
        const  storage = new MemoryStorage();
        const itemKey = 'toDelete';
        
        storage.setItem(itemKey, 15);
        
        expect(storage._storage.toDelete).toStrictEqual(15);
        
        storage.removeItem(itemKey);
        
        expect(storage.getItem(itemKey)).toBeNull();
        expect(typeof storage._storage[itemKey]).toStrictEqual('undefined');
    });

    test('key returns a defined item', () => {
        const storage = new MemoryStorage();
        storage.setItem('firstItem', 1);
        storage.setItem('secondItem', 2);

        expect(storage.key(0)).toStrictEqual(1);
        expect(storage.key(1)).toStrictEqual(2);
    });

    test('it returns null if index is not in range', () => {
        const storage = new MemoryStorage();
        storage.setItem('onlyItem', 1);

        expect(storage.key(12)).toBeNull();
        expect(storage.key(-12)).toBeNull();
        expect(storage.key()).toBeNull();
    });

    test('clear resets storage', () => {
        const storage = new MemoryStorage();
        storage.setItem('onlyItem', 1);

        expect(storage.getItem('onlyItem')).toBeDefined();

        storage.clear();

        expect(storage.getItem('onlyItem')).toBeNull();
        expect(JSON.stringify(storage._storage)).toBe('{}');
    });
});
