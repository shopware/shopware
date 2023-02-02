import RecentlySearchService from 'src/app/service/recently-search.service';

describe('app/service/recently-search.service.js', () => {
    let recentlySearchService;

    beforeEach(() => {
        recentlySearchService = new RecentlySearchService();
    });

    it('get method should return empty array', () => {
        const data = recentlySearchService.get('userId');

        expect(data).toEqual([]);
    });

    it('get method should return items from localStorage', () => {
        const items = [{ entity: 'product', id: 'productId' }];

        localStorage.setItem(recentlySearchService._key('userId'), JSON.stringify(items));

        const data = recentlySearchService.get('userId');

        expect(data).toEqual(items);
    });

    it('add method should save item into localStorage', () => {
        localStorage.removeItem(recentlySearchService._key('userId'));

        const items = [{ entity: 'product', id: 'productId', payload: {} }];

        recentlySearchService.add('userId', 'product', 'productId');

        const actualStorage = JSON.parse(localStorage.getItem(recentlySearchService._key('userId')));

        expect(actualStorage.length).toEqual(1);
        expect(actualStorage[0].timestamp).toBeTruthy();

        delete actualStorage[0].timestamp;

        expect(actualStorage).toEqual(items);
    });

    it('add method should add up to maximum stack', () => {
        const maximumStack = recentlySearchService._maxStackSize();

        for (let i = 0; i < maximumStack; i += 1) {
            recentlySearchService.add('userId', 'product', `productId-${i}`);
        }

        const data = recentlySearchService.get('userId');

        expect(data.length).toEqual(maximumStack);
    });

    it('add method should push newst item to the top', () => {
        const maximumStack = recentlySearchService._maxStackSize();

        for (let i = 0; i < maximumStack; i += 1) {
            recentlySearchService.add('userId', 'product', `productId-${i}`);
        }

        recentlySearchService.add('userId', 'product', 'productId-new');

        const data = recentlySearchService.get('userId');

        expect(data.length).toEqual(maximumStack);
        expect(data[0].entity).toEqual('product');
        expect(data[0].id).toEqual('productId-new');
    });

    it('add method should pop oldest out of stack', () => {
        const maximumStack = recentlySearchService._maxStackSize();

        recentlySearchService.add('userId', 'product', 'productId-old');

        for (let i = 0; i < maximumStack; i += 1) {
            recentlySearchService.add('userId', 'product', `productId-${i}`);
        }

        const data = recentlySearchService.get('userId');

        const foundOldProduct = data.find(item => item.id === 'productId-old');

        expect(foundOldProduct).toEqual(undefined);
    });

    it('add method should move duplicated item to the top', async () => {
        const maximumStack = recentlySearchService._maxStackSize();

        recentlySearchService.add('userId', 'product', 'productId-unique');

        const uniqueProduct = recentlySearchService.get('userId')[0];

        for (let i = 0; i < maximumStack; i += 1) {
            recentlySearchService.add('userId', 'product', `productId-${i}`);
        }

        // Sleep 1 milisecond
        await new Promise(r => setTimeout(r, 1));

        recentlySearchService.add('userId', uniqueProduct.entity, uniqueProduct.id);

        const data = recentlySearchService.get('userId');

        const foundOldProducts = data.filter(item => item.id === uniqueProduct.id);

        expect(foundOldProducts).toBeTruthy();
        expect(foundOldProducts.length).toEqual(1);

        const foundOldProduct = foundOldProducts[0];

        expect(foundOldProduct).toEqual(data[0]);
        expect(foundOldProduct.entity).toEqual(uniqueProduct.entity);
        expect(foundOldProduct.id).toEqual(uniqueProduct.id);
        expect(foundOldProduct.timestamp).toBeGreaterThanOrEqual(uniqueProduct.timestamp);
    });
});
