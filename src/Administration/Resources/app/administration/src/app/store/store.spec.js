/**
 * @package admin
 */
import Store from 'src/app/store/index';
import { reactive } from 'vue';

describe('src/app/store/index.ts', () => {
    beforeAll(() => {
        Shopware.Store.clear();
    });

    it('should be a Singleton', () => {
        const aStore = Store.instance;

        expect(aStore).toBe(Shopware.Store);
    });

    it('should return empty list without registered stores', () => {
        const store = Store.instance;

        expect(store.list()).toStrictEqual([]);
    });

    it('should return list for all registered store', () => {
        const store = Store.instance;
        store.register({
            id: 'foo',
        });

        store.register({
            id: 'bar',
        });

        expect(store.list()).toStrictEqual([
            'foo',
            'bar',
        ]);

        store.unregister('foo');
        store.unregister('bar');
    });

    it('should throw an error for none existing store', () => {
        const store = Store.instance;

        expect(() => store.get('iDontExist')).toThrow('Store with id "iDontExist" not found');
    });

    it('should return the correct store', () => {
        const root = Store.instance;

        root.register({
            id: 'foo',
            state: () => ({
                id: '',
            }),
            actions: {
                randomizeId() {
                    this.id = Shopware.Utils.createId();
                },
            },
            getters: {
                subsetId: (state) => {
                    return state.id.substring(0, 4).toUpperCase();
                },
            },
        });

        const store = root.get('foo');
        expect(store).toBeDefined();
        expect(store.id).toBe('');
        expect(store.subsetId).toHaveLength(0);
        expect(store.randomizeId).toBeInstanceOf(Function);

        store.randomizeId();
        expect(store.id).toHaveLength(32);
        expect(store.subsetId).toHaveLength(4);

        root.unregister('foo');
    });

    it('should correctly unregister a store', () => {
        const root = Store.instance;
        root.register({
            id: 'foo',
        });

        expect(root.get('foo')).toBeDefined();

        root.unregister('foo');
        expect(() => {
            root.get('foo');
        }).toThrow('Store with id "foo" not found');
    });

    it('should correctly unregister all stores', () => {
        const root = Store.instance;
        root.register({
            id: 'foo',
        });

        root.register({
            id: 'bar',
        });

        expect(root.list()).toStrictEqual([
            'foo',
            'bar',
        ]);

        root.clear();
        expect(root.list()).toStrictEqual([]);
    });

    it('should register a store using setup function', () => {
        const root = Store.instance;
        const state = reactive({
            id: 'test',
        });
        root.register('foo', () => state);

        const store = root.get('foo');
        expect(store).toBeDefined();
        expect(store.id).toBe('test');
    });

    it('should throw an error when registering with invalid params', () => {
        const root = Store.instance;

        expect(() => {
            root.register('foo');
        }).toThrow('Invalid arguments registering a Store');

        expect(() => {
            root.register({ state: { foo: 'bar' } });
        }).toThrow('Invalid arguments registering a Store');

        expect(() => {
            root.register(() => ({
                state: { foo: 'bar' },
            }));
        }).toThrow('Invalid arguments registering a Store');
    });
});
