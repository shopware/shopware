import hydrator from 'src/module/sw-promotion/helper/code-entity-hydrator.helper';

// Disable developer hints in jest output
jest.spyOn(global.console, 'warn').mockImplementation(() => jest.fn());

describe('module/sw-promotion/helper/code-entity-hydrator.helper.js', () => {
    it('should not be redeemed if payload is NULL', async () => {
        const item = {
            payload: null
        };
        hydrator.hydrate(item);
        expect(item.isRedeemed).toBe(false);
    });

    it('should be redeemed if payload has value', async () => {
        const item = {
            payload: {
                orderId: '4',
                customerId: '15',
                customerName: 'John Doe'
            }
        };
        hydrator.hydrate(item);
        expect(item.isRedeemed).toBe(true);
    });

    it('should have orderId property with NULL if payload is null to have a consistent object', async () => {
        const item = {
            payload: null
        };
        hydrator.hydrate(item);
        expect(item.orderId).toBe(null);
    });

    it('should have a orderId property if payload has that content', async () => {
        const item = {
            payload: {
                orderId: '4',
                customerId: '15',
                customerName: 'John Doe'
            }
        };
        hydrator.hydrate(item);
        expect(item.orderId).toBe('4');
    });

    it('should have customerId property with NULL if payload is null to have a consistent object', async () => {
        const item = {
            payload: null
        };
        hydrator.hydrate(item);
        expect(item.customerId).toBe(null);
    });

    it('should have a customerId property if payload has that content', async () => {
        const item = {
            payload: {
                orderId: '4',
                customerId: '15',
                customerName: 'John Doe'
            }
        };
        hydrator.hydrate(item);
        expect(item.customerId).toBe('15');
    });

    it('should have customerName property with NULL if payload is null to have a consistent object', async () => {
        const item = {
            payload: null
        };
        hydrator.hydrate(item);
        expect(item.customerName).toBe(null);
    });

    it('should have a customerName property if payload has that content', async () => {
        const item = {
            payload: {
                orderId: '4',
                customerId: '15',
                customerName: 'John Doe'
            }
        };
        hydrator.hydrate(item);
        expect(item.customerName).toBe('John Doe');
    });
});
