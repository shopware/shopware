/**
 * @sw-package framework
 */

import { defineComponent } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import 'src/app/component/structure/sw-admin-nps-card-data-provider';

const npsCardStub = defineComponent({
    name: 'sw-admin-nps-card',
    emits: [
        'update:visible',
        'submit',
        'not-now',
        'close',
    ],
    template: '<div class="sw-admin-nps-card" />',
});

async function createWrapper() {
    return mount(await wrapTestComponent('sw-admin-nps-card-data-provider', { sync: true }), {
        global: {
            stubs: {
                'sw-admin-nps-card': npsCardStub,
            },
        },
    });
}

async function createVisibleWrapper() {
    const wrapper = await createWrapper();

    (wrapper.vm as unknown as { isVisible: boolean }).isVisible = true;
    await flushPromises();

    return wrapper;
}

describe('src/app/component/structure/sw-admin-nps-card-data-provider', () => {
    let logSpy: jest.SpyInstance;

    beforeEach(() => {
        logSpy = jest.spyOn(console, 'log').mockImplementation(() => {});
    });

    afterEach(() => {
        logSpy.mockRestore();
    });

    it('should not render the card until eligibility is implemented', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.findComponent(npsCardStub).exists()).toBe(false);
    });

    it('should render the card when visible and unmount it when the card requests to be hidden', async () => {
        const wrapper = await createVisibleWrapper();

        const card = wrapper.findComponent(npsCardStub);
        expect(card.exists()).toBe(true);

        card.vm.$emit('update:visible', false);
        await flushPromises();

        expect(wrapper.findComponent(npsCardStub).exists()).toBe(false);
    });

    it.each([
        ['submit', '[NPS] submit'],
        ['not-now', '[NPS] not now'],
    ])('should log the response on %s', async (eventName, logMessage) => {
        const wrapper = await createVisibleWrapper();

        wrapper.findComponent(npsCardStub).vm.$emit(eventName, { score: 3, reason: 'Slow' });

        expect(logSpy).toHaveBeenCalledWith(logMessage, { score: 3, reason: 'Slow' });
    });

    it('should log when the card is closed', async () => {
        const wrapper = await createVisibleWrapper();

        wrapper.findComponent(npsCardStub).vm.$emit('close');

        expect(logSpy).toHaveBeenCalledWith('[NPS] close');
    });
});
