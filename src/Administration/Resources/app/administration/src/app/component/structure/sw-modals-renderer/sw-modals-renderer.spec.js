/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-modals-renderer', { sync: true }), {
        global: {
            stubs: {
                'sw-modal': {
                    template: '<div class="sw-modal-stub" @click="$emit(\'modal-close\')"><slot /></div>',
                    emits: ['modal-close'],
                },
                'sw-iframe-renderer': {
                    template: '<div class="sw-iframe-renderer-stub" :data-src="src"></div>',
                    props: ['src', 'locationId'],
                },
                'mt-button': true,
            },
        },
    });
}

describe('src/app/component/structure/sw-modals-renderer', () => {
    beforeEach(() => {
        Shopware.Store.get('modals').modals = [];
    });

    it('marks a location opened with props', async () => {
        Shopware.Store.get('modals').openModal({
            locationId: 'props-modal',
            baseUrl: 'https://example.com/app?shop=1',
            props: { tab: 'tone' },
        });
        const wrapper = await createWrapper();

        const src = new URL(wrapper.find('.sw-iframe-renderer-stub').attributes('data-src'));

        expect(src.searchParams.get('sw-modal-props')).toBe('1');
        expect(src.searchParams.get('shop')).toBe('1');
    });

    it('keeps the location URL of a modal without props', async () => {
        Shopware.Store.get('modals').openModal({
            locationId: 'plain-modal',
            baseUrl: 'https://example.com/app',
        });
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-iframe-renderer-stub').attributes('data-src')).toBe('https://example.com/app');
    });

    it('reports a close by the user as dismissed', async () => {
        const onResult = jest.fn();
        Shopware.Store.get('modals').openModal({
            locationId: 'result-modal',
            baseUrl: 'https://example.com/app',
            onResult,
        });
        const wrapper = await createWrapper();

        await wrapper.find('.sw-modal-stub').trigger('click');

        expect(Shopware.Store.get('modals').modals).toHaveLength(0);
        expect(onResult).toHaveBeenCalledWith({ reason: 'dismissed' });
    });

    it('closes only the text modal the user closed', async () => {
        const first = jest.fn();
        const second = jest.fn();
        Shopware.Store.get('modals').openModal({ textContent: 'First', baseUrl: '', onResult: first });
        Shopware.Store.get('modals').openModal({ textContent: 'Second', baseUrl: '', onResult: second });
        const wrapper = await createWrapper();

        await wrapper.findAll('.sw-modal-stub').at(1).trigger('click');

        expect(Shopware.Store.get('modals').modals).toHaveLength(1);
        expect(Shopware.Store.get('modals').modals[0].textContent).toBe('First');
        expect(second).toHaveBeenCalledWith({ reason: 'dismissed' });
        expect(first).not.toHaveBeenCalled();
    });
});
