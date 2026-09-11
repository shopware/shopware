/**
 * @sw-package checkout
 */

describe('src/module/sw-order/state/order-detail.store', () => {
    const state = Shopware.Store.get('swOrderDetail');

    beforeEach(() => {
        state.$reset();
        state.resetCustomer();
    });

    function mockCustomerRepository(get) {
        return jest.spyOn(Shopware.Service('repositoryFactory'), 'create').mockReturnValue({ get });
    }

    it('should be able to setOrder', () => {
        const newOrder = { id: 1, name: 'Test Order' };

        Shopware.Store.get('swOrderDetail').order = newOrder;

        expect(state.order).toEqual(newOrder);
    });

    it('should be able to setLoading', () => {
        Shopware.Store.get('swOrderDetail').setLoading([
            'order',
            true,
        ]);

        expect(state.loading.order).toBe(true);
        expect(Shopware.Store.get('swOrderDetail').isLoading).toBe(true);
    });

    it('should be able to setSavedSuccessful', () => {
        Shopware.Store.get('swOrderDetail').savedSuccessful = true;

        expect(state.savedSuccessful).toBe(true);
    });

    it('should be able to setVersionContext', () => {
        const versionContext = { versionId: 1, versionDate: '2021-01-01' };

        Shopware.Store.get('swOrderDetail').versionContext = versionContext;

        expect(state.versionContext).toEqual(versionContext);
    });

    it('should be able to setEditing', () => {
        Shopware.Store.get('swOrderDetail').editing = true;

        expect(state.editing).toBe(true);
        expect(Shopware.Store.get('swOrderDetail').isEditing).toBe(true);
    });

    it('should clear order address ids when setOrderAddressIds gets null', () => {
        Shopware.Store.get('swOrderDetail').setOrderAddressIds({
            orderAddressId: '0190d92db32071d689120d3dcf352197',
            customerAddressId: '0190d9275a6a72ae8b536849a4a02d85',
            type: 'billing',
        });

        Shopware.Store.get('swOrderDetail').setOrderAddressIds(null);

        expect(state.orderAddressIds).toEqual([]);
    });

    it('should set order address ids when provided valid address info', () => {
        const addressIdInfo = {
            orderAddressId: '0190d92db32071d689120d3dcf352197',
            customerAddressId: '0190d9275a6a72ae8b536849a4a02d85',
            type: 'billing',
        };

        Shopware.Store.get('swOrderDetail').setOrderAddressIds(addressIdInfo);

        expect(state.orderAddressIds).toEqual([addressIdInfo]);
    });

    it('should not set order address ids when orderAddressId equals customerAddressId', () => {
        const addressIdInfo = {
            orderAddressId: '0190d92db32071d689120d3dcf352197',
            customerAddressId: '0190d92db32071d689120d3dcf352197',
            type: 'billing',
        };

        Shopware.Store.get('swOrderDetail').setOrderAddressIds(addressIdInfo);

        expect(state.orderAddressIds).toEqual([]);
    });

    it('should update customerAddressId when orderAddressId and type match', () => {
        const initialAddressIdInfo = {
            orderAddressId: '0190d92db32071d689120d3dcf352197',
            customerAddressId: '0190d9275a6a72ae8b536849a4a02d85',
            type: 'billing',
        };

        const updatedAddressIdInfo = {
            orderAddressId: '0190d92db32071d689120d3dcf352197',
            customerAddressId: '0190d926bb427e18aa3ceb00e23d090c',
            type: 'billing',
        };

        Shopware.Store.get('swOrderDetail').setOrderAddressIds(initialAddressIdInfo);
        Shopware.Store.get('swOrderDetail').setOrderAddressIds(updatedAddressIdInfo);

        expect(state.orderAddressIds).toEqual([updatedAddressIdInfo]);
    });

    it('should remove order address id when orderAddressId equals customerAddressId and type match', () => {
        const initialAddressIdInfo = {
            orderAddressId: '0190d92db32071d689120d3dcf352197',
            customerAddressId: '0190d9275a6a72ae8b536849a4a02d85',
            type: 'billing',
        };

        const removalAddressIdInfo = {
            orderAddressId: '0190d92db32071d689120d3dcf352197',
            customerAddressId: '0190d92db32071d689120d3dcf352197',
            type: 'billing',
        };

        Shopware.Store.get('swOrderDetail').setOrderAddressIds(initialAddressIdInfo);
        expect(state.orderAddressIds).toEqual([initialAddressIdInfo]);

        Shopware.Store.get('swOrderDetail').setOrderAddressIds(removalAddressIdInfo);
        expect(state.orderAddressIds).toEqual([]);
    });

    describe('loadCustomer', () => {
        const customerId = '0190d9275a6a72ae8b536849a4a02d85';

        it('should load the customer including the addresses of the address selections', async () => {
            const customer = { id: customerId };
            const get = jest.fn(() => Promise.resolve(customer));
            mockCustomerRepository(get);

            await expect(state.loadCustomer(customerId)).resolves.toBe(customer);

            expect(state.customer).toEqual(customer);
            expect(get).toHaveBeenCalledWith(customerId, Shopware.Context.api, expect.any(Shopware.Data.Criteria));

            const { associations } = get.mock.calls[0][2].parse();
            expect(Object.keys(associations)).toEqual(['addresses']);
            expect(Object.keys(associations.addresses.associations)).toEqual(['country']);
        });

        it('should share one customer between concurrent callers instead of requesting it per address selection', async () => {
            const customer = { id: customerId };
            const get = jest.fn(() => Promise.resolve(customer));
            mockCustomerRepository(get);

            const [
                billing,
                shipping,
            ] = await Promise.all([
                state.loadCustomer(customerId),
                state.loadCustomer(customerId),
            ]);

            expect(get).toHaveBeenCalledTimes(1);
            expect(billing).toBe(shipping);
        });

        it('should not request an already loaded customer again', async () => {
            const get = jest.fn(() => Promise.resolve({ id: customerId }));
            mockCustomerRepository(get);

            await state.loadCustomer(customerId);
            await state.loadCustomer(customerId);

            expect(get).toHaveBeenCalledTimes(1);
        });

        it('should request the customer again when reloading it after an address was saved', async () => {
            const initial = { id: customerId, addresses: [] };
            const reloaded = { id: customerId, addresses: [{ id: 'newCustomerAddressId' }] };
            const get = jest.fn().mockResolvedValueOnce(initial).mockResolvedValueOnce(reloaded);
            mockCustomerRepository(get);

            await state.loadCustomer(customerId);
            await state.loadCustomer(customerId, true);

            expect(get).toHaveBeenCalledTimes(2);
            expect(state.customer).toEqual(reloaded);
        });

        it('should load another customer when the order belongs to a different one', async () => {
            const otherCustomerId = '0190d926bb427e18aa3ceb00e23d090c';
            const get = jest.fn().mockResolvedValueOnce({ id: customerId }).mockResolvedValueOnce({ id: otherCustomerId });
            mockCustomerRepository(get);

            await state.loadCustomer(customerId);
            await state.loadCustomer(otherCustomerId);

            expect(get).toHaveBeenCalledTimes(2);
            expect(state.customer).toEqual({ id: otherCustomerId });
        });

        it('should load the customer again after it was reset when leaving the order', async () => {
            const get = jest.fn(() => Promise.resolve({ id: customerId }));
            mockCustomerRepository(get);

            await state.loadCustomer(customerId);

            state.resetCustomer();
            expect(state.customer).toBeNull();

            await state.loadCustomer(customerId);

            expect(get).toHaveBeenCalledTimes(2);
        });
    });
});
