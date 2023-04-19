import { shallowMount } from '@vue/test-utils';

async function createWrapper() {
    return shallowMount(
        {
            template: `
            <div>
                <div class="test-one">
                    <input class="test-one"/>
                </div>
                <div class="test-two" v-autofocus>
                    <input class="test-two-input"/>
                </div>
                <div class="test-three">
                    <input class="test-three-input"/>
                </div>
            </div>`,
        },
        {
            attachTo: document.body,
        },
    );
}

describe('src/app/directive/autofocus.directive.ts', () => {
    // @type Wrapper<Vue>
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();

        await flushPromises();
    });

    afterEach(async () => {
        if (wrapper) {
            await wrapper.destroy();
        }

        await flushPromises();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have registered the autofocus directive', () => {
        expect(Shopware.Directive.getByName('autofocus')).toBeDefined();
    });

    it('should autofocus on the second input', () => {
        const secondInput = wrapper.find('.test-two-input');

        expect(secondInput.element).toHaveFocus();
    });
});
