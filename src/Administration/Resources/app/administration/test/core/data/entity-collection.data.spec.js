import EntityCollection from 'src/core/data/entity-collection.data';
import utils from 'src/core/service/util.service';

const fixture = [
    {
        id: utils.createId(),
        name: 'entity one',
        filtered: true
    }, {
        id: utils.createId(),
        name: 'entity two'
    }, {
        id: utils.createId(),
        name: 'entity three',
        filtered: true
    }, {
        id: utils.createId(),
        name: 'entity four'
    }, {
        id: utils.createId(),
        name: 'entity five',
        filtered: true
    }
];

function getCollection() {
    return new EntityCollection(
        '/test-entity',
        'testEntity',
        null,
        { isShopwareContext: true },
        fixture,
        fixture.length,
        null
    );
}

describe('entity-collection.data.js', () => {
    it('is an array', async () => {
        const ArrayPrototype = Object.getPrototypeOf([]);
        const collection = getCollection();

        expect(ArrayPrototype.isPrototypeOf(collection)).toBe(true);
        collection.forEach((item, index) => {
            expect(item).toStrictEqual(fixture[index]);
        });
    });

    it('returns first item when first is called', async () => {
        const collection = getCollection();

        expect(collection.first()).toStrictEqual(fixture[0]);
    });

    it('return null if collection is empty and first is called', async () => {
        const collection = new EntityCollection();

        expect(collection.first()).toBeNull();
    });

    it('return last item when last is called', async () => {
        const collection = getCollection();

        expect(collection.last()).toStrictEqual(fixture[fixture.length - 1]);
    });

    it('return null if collection is empty and last is called', async () => {
        const collection = new EntityCollection();

        expect(collection.last()).toBeNull();
    });

    it('removes an item depending on the id', async () => {
        const collection = getCollection();

        expect(collection.remove(fixture[2].id)).toBe(true);
        expect(collection.find((i) => i.id === fixture[2].id)).toBeUndefined();
    });

    it('returns false if no item can be removed', async () => {
        const collection = getCollection();

        expect(collection.remove(utils.createId())).toBe(false);
        expect(collection.length).toBe(fixture.length);
    });

    it('returns true if the collection contains an entity with requested id', async () => {
        const collection = getCollection();

        expect(collection.has(fixture[2].id)).toBe(true);
    });

    it('returns false if the collection does not contains an entity with requested id', async () => {
        const collection = getCollection();

        expect(collection.has(utils.createId())).toBe(false);
    });

    it('returns the entity with a given id', async () => {
        const collection = getCollection();

        expect(collection.get(fixture[2].id)).toStrictEqual(fixture[2]);
    });

    it('returns null if a entity with given id is not found', async () => {
        const collection = getCollection();

        expect(collection.get(utils.createId())).toBeNull();
    });

    it('returns the entity at a given index', async () => {
        const collection = getCollection();

        expect(collection.getAt(2)).toStrictEqual(fixture[2]);
    });

    it('returns null if no entity is set on a given index', async () => {
        const collection = getCollection();

        expect(collection.getAt(-2)).toBeNull();
    });

    it('maps ids properly', async () => {
        const ids = getCollection().getIds();
        ids.forEach((id, index) => {
            expect(id).toBe(fixture[index].id);
        });
    });

    it('adds a new item to the collection', async () => {
        const collection = getCollection();
        const initialLength = collection.length;

        const newItem = {
            id: utils.createId(),
            name: 'new item'
        };

        collection.add(newItem);
        expect(collection.length).toBe(initialLength + 1);
        expect(collection.last()).toStrictEqual(newItem);
    });

    it('adds an entity at a given position', async () => {
        const collection = getCollection();

        const newItem = {
            id: utils.createId(),
            name: 'new item'
        };

        collection.addAt(newItem, 2);

        expect(collection.getAt(2)).toStrictEqual(newItem);
    });

    it('shifts an item if you add at negative position', async () => {
        const collection = getCollection();

        const newItem = {
            id: utils.createId(),
            name: 'new item'
        };

        collection.addAt(newItem, -12);
        expect(collection.first()).toStrictEqual(newItem);
    });

    it('does not create holes if you add at index beyond length', async () => {
        const collection = getCollection();

        const newItem = {
            id: utils.createId(),
            name: 'new item'
        };

        collection.addAt(newItem, 12);
        expect(collection.last()).toStrictEqual(newItem);
        expect(collection.findIndex(i => i === undefined)).toBe(-1);
    });

    it('adds item at the end if index is undefined', async () => {
        const collection = getCollection();

        const newItem = {
            id: utils.createId(),
            name: 'new item'
        };
        collection.addAt(newItem);
        expect(collection.last()).toStrictEqual(newItem);
    });

    it('pretends moving if oldIndex equals newIndex', async () => {
        const collection = getCollection();

        expect(collection.moveItem(2, 2)).toStrictEqual(fixture[2]);
    });

    it('returns null if oldIndex is not valid', async () => {
        const collection = getCollection();

        expect(collection.moveItem(-12, 3)).toBe(null);
        expect(collection.moveItem(9000, 3)).toBe(null);
    });

    it('moves an item to the correct position', async () => {
        const collection = getCollection();
        collection.moveItem(1, 2);

        expect(collection.getAt(0)).toStrictEqual(fixture[0]);
        expect(collection.getAt(1)).toStrictEqual(fixture[2]);
        expect(collection.getAt(2)).toStrictEqual(fixture[1]);
        expect(collection.getAt(3)).toStrictEqual(fixture[3]);
    });

    it('shifts an item if new index is smaller 0 without creating holes', async () => {
        const collection = getCollection();

        collection.moveItem(1, -9000);

        expect(collection.first()).toStrictEqual(fixture[1]);
        expect(collection.getAt(1)).toStrictEqual(fixture[0]);
    });

    it('moves an item to the end without creating holes', async () => {
        const collection = getCollection();

        collection.moveItem(1, 9000);

        expect(collection.getAt(1)).toStrictEqual(fixture[2]);
        expect(collection.getAt(4)).toStrictEqual(fixture[1]);
    });

    it('does not duplicate collection if new index is undefined', async () => {
        const collection = getCollection();

        collection.moveItem(1);
        expect(collection.length).toBe(5);
        expect(collection.last()).toStrictEqual(fixture[1]);
    });

    it('does not duplicate collection if new index is undefined', async () => {
        const collection = getCollection();

        collection.moveItem(null);
        expect(collection.length).toBe(5);
        expect(collection.last()).toStrictEqual(fixture[4]);
    });

    it('preserves types after filter', async () => {
        const collectionPrototype = Object.getPrototypeOf(new EntityCollection());
        const collection = getCollection();

        const filtered = collection.filter(() => true);
        expect(filtered.entity).toBe(collection.entity);
        expect(filtered.source).toBe(collection.source);
        expect(filtered.context).toBe(collection.context);
        expect(collectionPrototype.isPrototypeOf(filtered));
    });

    it('uses a filter callback', async () => {
        const collection = getCollection();

        const filtered = collection.filter(item => item.filtered !== true);
        expect(filtered.find((item => item.filtered))).toBeUndefined();
    });

    it('even uses scopes in filter', async () => {
        function scopedFilter(item) {
            return this.name === item.name;
        }

        const collection = getCollection();

        const filtered = collection.filter(scopedFilter, fixture[0]);


        expect(filtered.length).toBe(1);
        expect(filtered.first()).toStrictEqual(fixture[0]);
    });

    it('can return an empty colletion', async () => {
        const collection = getCollection();

        const filtered = collection.filter(() => false);
        expect(filtered.length).toBe(0);
    });
});
