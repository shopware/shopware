/**
 * @sw-package framework
 */
import 'src/app/mixin/notification-translation.mixin';
import { mount } from '@vue/test-utils';
import Sanitizer from 'src/core/helper/sanitizer.helper';

const messages = {
    'global.default.error': 'Error',
    'global.notification.message': 'A <strong>translated</strong> message',
};

async function createWrapper() {
    return mount(
        {
            template: '<div class="sw-mock"></div>',
            mixins: [
                Shopware.Mixin.getByName('notification-translation'),
            ],
        },
        {
            global: {
                mocks: {
                    $te: (key) => Object.prototype.hasOwnProperty.call(messages, key),
                    $t: (key) => messages[key] ?? key,
                    $sanitize: (dirtyHtml, config) => Sanitizer.sanitize(dirtyHtml, config),
                },
            },
        },
    );
}

describe('src/app/mixin/notification-translation.mixin', () => {
    describe('getTranslatedTitle', () => {
        it('translates the title when it is a snippet key', async () => {
            const wrapper = await createWrapper();

            expect(wrapper.vm.getTranslatedTitle({ title: 'global.default.error' })).toBe('Error');
        });

        it('passes a plain string title through unchanged', async () => {
            const wrapper = await createWrapper();

            expect(wrapper.vm.getTranslatedTitle({ title: 'Plain title' })).toBe('Plain title');
        });

        it('returns an empty string when there is no title', async () => {
            const wrapper = await createWrapper();

            expect(wrapper.vm.getTranslatedTitle({})).toBe('');
        });
    });

    describe('getTranslatedMessage', () => {
        it('translates the message when it is a snippet key', async () => {
            const wrapper = await createWrapper();

            expect(wrapper.vm.getTranslatedMessage({ message: 'global.notification.message' })).toBe(
                'A <strong>translated</strong> message',
            );
        });

        it('passes a plain string message through unchanged', async () => {
            const wrapper = await createWrapper();

            expect(wrapper.vm.getTranslatedMessage({ message: 'Plain message' })).toBe('Plain message');
        });

        it('sanitizes disallowed markup from the message', async () => {
            const wrapper = await createWrapper();

            const result = wrapper.vm.getTranslatedMessage({ message: '<script>alert(1)</script><b>ok</b>' });

            expect(result).not.toContain('<script>');
            expect(result).toContain('<b>ok</b>');
        });

        it('returns an empty string when there is no message', async () => {
            const wrapper = await createWrapper();

            expect(wrapper.vm.getTranslatedMessage({})).toBe('');
        });
    });
});
