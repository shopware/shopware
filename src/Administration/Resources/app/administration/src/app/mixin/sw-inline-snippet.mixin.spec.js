import 'src/app/mixin/sw-inline-snippet.mixin';
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount({
        template: `
            <div class="sw-mock">
              <slot></slot>
            </div>
        `,
        mixins: [
            Shopware.Mixin.getByName('sw-inline-snippet'),
        ],
        data() {
            return {
                name: 'sw-mock-field',
            };
        },
    }, {
        attachTo: document.body,
    });
}

describe('src/app/mixin/sw-inline-snippet.mixin.ts', () => {
    let wrapper;

    beforeEach(async () => {
        Shopware.Context.app.fallbackLocale = 'de-DE';
        wrapper = await createWrapper();

        await flushPromises();
    });

    afterEach(async () => {
        if (wrapper) {
            await wrapper.unmount();
        }

        await flushPromises();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should return the inline snippet', () => {
        const result = wrapper.vm.getInlineSnippet('sw.example');

        expect(result).toBe('sw.example');
    });

    it('should return empty string when using the getInlineSnippet method without value', () => {
        const result = wrapper.vm.getInlineSnippet('');

        expect(result).toBe('');
    });

    it('should return correct value with locale using the getInlineSnippet method without value', () => {
        const result = wrapper.vm.getInlineSnippet({
            'en-GB': 'English',
        });

        expect(result).toBe('English');
    });

    it('should return correct fallback value with locale using the getInlineSnippet method without value', () => {
        const result = wrapper.vm.getInlineSnippet({
            'fr-FR': 'French',
            'de-DE': 'German',
        });

        expect(result).toBe('German');
    });

    it('should return first value when no fallback is defined using the getInlineSnippet method without value', () => {
        const result = wrapper.vm.getInlineSnippet({
            'fr-FR': 'French',
        });

        expect(result).toBe('French');
    });
});
