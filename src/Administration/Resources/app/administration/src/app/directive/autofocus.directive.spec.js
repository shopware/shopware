/**
 * @package admin
 */
import { mount } from '@vue/test-utils';

async function createWrapper({ template = '' } = { template: '' }) {
    return mount(
        {
            template: template.length > 0 ? template : `
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

    it('should not do anything when autofocus does not contain an input element', async () => {
        wrapper = await createWrapper({
            template: `
            <div>
                <div class="test-one">
                    <span class="test-one-non-input">One</span>
                </div>
                <div class="test-two" v-autofocus>
                    <span class="test-two-non-input">Two</span>
                </div>
                <div class="test-three">
                    <span class="test-three-non-input">Three</span>
                </div>
            </div>`,
        });

        const secondNonInput = wrapper.find('.test-two-non-input');

        expect(secondNonInput.element).not.toHaveFocus();
    });
});
