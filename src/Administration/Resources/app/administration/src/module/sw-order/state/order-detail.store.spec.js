/**
 * @package checkout
 * @group disabledCompat
 */

import orderDetailState from 'src/module/sw-order/state/order-detail.store';

describe('src/module/sw-order/state/order-detail.store', () => {
    let state;

    beforeAll(() => {
        Shopware.State.registerModule('swOrderDetail', orderDetailState);
    });

    beforeEach(() => {
        state = Shopware.State.get('swOrderDetail');
    });

    it('should be able to setOrder', () => {
        const newOrder = { id: 1, name: 'Test Order' };

        Shopware.State.commit('swOrderDetail/setOrder', newOrder);

        expect(state.order).toEqual(newOrder);
    });

    it('should be able to setLoading', () => {
        Shopware.State.commit('swOrderDetail/setLoading', ['order', true]);

        expect(state.loading.order).toBe(true);
        expect(Shopware.State.getters['swOrderDetail/isLoading']).toBe(true);
    });

    it('should be able to setSavedSuccessful', () => {
        Shopware.State.commit('swOrderDetail/setSavedSuccessful', true);

        expect(state.savedSuccessful).toBe(true);
    });

    it('should be able to setVersionContext', () => {
        const versionContext = { versionId: 1, versionDate: '2021-01-01' };

        Shopware.State.commit('swOrderDetail/setVersionContext', versionContext);

        expect(state.versionContext).toEqual(versionContext);
    });

    it('should be able to setEditing', () => {
        Shopware.State.commit('swOrderDetail/setEditing', true);

        expect(state.editing).toBe(true);
        expect(Shopware.State.getters['swOrderDetail/isEditing']).toBe(true);
    });

    it('should set order address ids when provided valid address info', () => {
        const addressIdInfo = {
            orderAddressId: '0190d92db32071d689120d3dcf352197',
            customerAddressId: '0190d9275a6a72ae8b536849a4a02d85',
            type: 'billing',
        };

        Shopware.State.commit('swOrderDetail/setOrderAddressIds', addressIdInfo);

        expect(state.orderAddressIds).toEqual([addressIdInfo]);
    });

    it('should not set order address ids when orderAddressId equals customerAddressId', () => {
        const addressIdInfo = {
            orderAddressId: '0190d92db32071d689120d3dcf352197',
            customerAddressId: '0190d92db32071d689120d3dcf352197',
            type: 'billing',
        };

        Shopware.State.commit('swOrderDetail/setOrderAddressIds', addressIdInfo);

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

        Shopware.State.commit('swOrderDetail/setOrderAddressIds', initialAddressIdInfo);
        Shopware.State.commit('swOrderDetail/setOrderAddressIds', updatedAddressIdInfo);

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

        Shopware.State.commit('swOrderDetail/setOrderAddressIds', initialAddressIdInfo);
        Shopware.State.commit('swOrderDetail/setOrderAddressIds', removalAddressIdInfo);

        expect(state.orderAddressIds).toEqual([]);
    });
});
