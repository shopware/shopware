import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-flow/component/sw-flow-sequence-selector';
import 'src/app/component/base/sw-button';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-flow-sequence-selector'), {
        stubs: {
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-icon': true
        },
        propsData: {
            sequence: {}
        }
    });
}

describe('src/module/sw-flow/component/sw-flow-sequence-selector', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(async () => {
        if (wrapper) await wrapper.destroy();
    });

    it('should emit an event when add if condition', async () => {
        const button = wrapper.find('.sw-flow-sequence-selector__if-condition');
        await button.trigger('click');

        const emittedEvent = wrapper.emitted()['add-if-condition'];
        expect(emittedEvent).toBeTruthy();
    });

    it('should emit an event when add then action', async () => {
        const button = wrapper.find('.sw-flow-sequence-selector__then-action');
        await button.trigger('click');

        const emittedEvent = wrapper.emitted()['add-then-action'];
        expect(emittedEvent).toBeTruthy();
    });
});
