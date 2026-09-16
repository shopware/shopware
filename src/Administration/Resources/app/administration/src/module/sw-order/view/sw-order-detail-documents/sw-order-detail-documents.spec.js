/**
 * @sw-package after-sales
 */

import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';

const orderMock = {
    id: 'order-id',
    documents: [],
};

function createFeatureMock(featureActive = false) {
    return {
        isActive: (feature) => feature === 'v6.8.0.0' && featureActive,
    };
}

async function createWrapper({ featureActive = false } = {}) {
    return mount(await wrapTestComponent('sw-order-detail-documents', { sync: true }), {
        global: {
            stubs: {
                'mt-banner': {
                    name: 'mt-banner',
                    props: ['variant'],
                    template: '<div class="mt-banner"><slot /></div>',
                },
                'sw-order-document-card': {
                    props: ['order'],
                    emits: ['document-save'],
                    template: '<div class="sw-order-document-card"></div>',
                },
            },
            provide: {
                feature: createFeatureMock(featureActive),
            },
            mocks: {
                $t: (key) => key,
                feature: createFeatureMock(featureActive),
            },
        },
    });
}

describe('src/module/sw-order/view/sw-order-detail-documents', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        Shopware.Store.get('swOrderDetail').$reset();
        Shopware.Store.get('swOrderDetail').order = orderMock;
    });

    it('should render the document card', async () => {
        const wrapper = await createWrapper();
        const documentCard = wrapper.getComponent('.sw-order-document-card');

        expect(documentCard.props('order')).toStrictEqual(orderMock);
    });

    it('should render the save warning banner while editing on the mt-tabs path', async () => {
        Shopware.Store.get('swOrderDetail').editing = true;

        const wrapper = await createWrapper({ featureActive: true });
        const banner = wrapper.get('.sw-order-detail-documents__save-warning');

        expect(banner.text()).toBe('sw-order.documentTab.tooltipSaveBeforeCreateDocument');
        expect(wrapper.getComponent({ name: 'mt-banner' }).props('variant')).toBe('attention');
    });

    it('should not render the save warning banner while editing on the legacy sw-tabs path', async () => {
        Shopware.Store.get('swOrderDetail').editing = true;

        const wrapper = await createWrapper({ featureActive: false });

        expect(wrapper.find('.sw-order-detail-documents__save-warning').exists()).toBe(false);
    });

    it('should not render the save warning banner when not editing', async () => {
        const wrapper = await createWrapper({ featureActive: true });

        expect(wrapper.find('.sw-order-detail-documents__save-warning').exists()).toBe(false);
    });
});
