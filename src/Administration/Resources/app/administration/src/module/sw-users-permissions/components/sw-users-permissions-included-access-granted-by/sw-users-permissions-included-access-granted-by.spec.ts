/**
 * @sw-package fundamentals@framework
 */
import { mount } from '@vue/test-utils';

const className = 'sw-users-permissions-included-access-granted-by';

const grantedBy = [
    { key: 'clear_cache', label: 'Clear cache', roles: '' },
    { key: 'product', label: 'Products', roles: 'View, Edit' },
];

async function createWrapper(popoverId = 'media') {
    return mount(await wrapTestComponent('sw-users-permissions-included-access-granted-by', { sync: true }), {
        attachTo: document.body,
        props: {
            popoverId,
            grantedBy,
        },
        global: {
            stubs: {
                'mt-floating-ui': {
                    props: ['isOpened'],
                    emits: ['close'],
                    template: `
                        <div class="mt-floating-ui">
                            <slot name="trigger" />
                            <div v-if="isOpened" class="mt-floating-ui__content"><slot /></div>
                            <button class="mt-floating-ui__outside" @click="$emit('close')" />
                        </div>
                    `,
                },
            },
            mocks: {
                $tc: (key: string, count: number) => `${key} count=${count}`,
            },
        },
    });
}

const isOpen = (wrapper: Awaited<ReturnType<typeof createWrapper>>) => {
    return wrapper.find(`.${className}__popover`).exists();
};

describe('src/module/sw-users-permissions/components/sw-users-permissions-included-access-granted-by', () => {
    let wrappers: Array<Awaited<ReturnType<typeof createWrapper>>> = [];

    beforeEach(() => {
        jest.useFakeTimers();
    });

    afterEach(() => {
        wrappers.forEach((wrapper) => wrapper.unmount());
        wrappers = [];
        jest.useRealTimers();
    });

    async function create(popoverId?: string) {
        const wrapper = await createWrapper(popoverId);
        wrappers.push(wrapper);

        return wrapper;
    }

    it('should count the permissions in the trigger and describe them for screen readers', async () => {
        const wrapper = await create();

        expect(wrapper.find(`.${className}__trigger`).text()).toBe(
            'sw-users-permissions.roles.included-access.grantedBy count=2',
        );
        expect(wrapper.find(`.${className}__description`).text()).toBe('Clear cache; Products: View, Edit');
        expect(wrapper.find(`.${className}__trigger`).attributes('aria-describedby')).toBe(
            wrapper.find(`.${className}__description`).attributes('id'),
        );
    });

    it('should open after a short hover and close when the cursor leaves', async () => {
        const wrapper = await create();
        const trigger = wrapper.find(`.${className}__trigger`);

        await trigger.trigger('mouseenter');
        expect(isOpen(wrapper)).toBe(false);

        jest.advanceTimersByTime(150);
        await flushPromises();
        expect(isOpen(wrapper)).toBe(true);
        expect(trigger.attributes('aria-expanded')).toBe('true');
        expect(wrapper.find(`.${className}__popover`).text()).toContain('Products');

        await trigger.trigger('mouseleave');
        jest.advanceTimersByTime(100);
        await flushPromises();
        expect(isOpen(wrapper)).toBe(false);
    });

    it('should open on focus', async () => {
        const wrapper = await create();

        await wrapper.find(`.${className}__trigger`).trigger('focus');

        expect(isOpen(wrapper)).toBe(true);
    });

    it('should stay open after a click until an outside click or Escape', async () => {
        const wrapper = await create();
        const trigger = wrapper.find(`.${className}__trigger`);

        await trigger.trigger('click');
        await trigger.trigger('mouseleave');
        jest.advanceTimersByTime(100);
        await flushPromises();
        expect(isOpen(wrapper)).toBe(true);

        await wrapper.find('.mt-floating-ui__outside').trigger('click');
        expect(isOpen(wrapper)).toBe(false);

        await trigger.trigger('click');
        await trigger.trigger('keydown', { key: 'Escape' });
        expect(isOpen(wrapper)).toBe(false);
    });

    it('should close on a second click', async () => {
        const wrapper = await create();
        const trigger = wrapper.find(`.${className}__trigger`);

        await trigger.trigger('click');
        await trigger.trigger('click');

        expect(isOpen(wrapper)).toBe(false);
    });

    it('should keep only one popover open', async () => {
        const first = await create('media');
        const second = await create('rule');

        await first.find(`.${className}__trigger`).trigger('focus');
        await second.find(`.${className}__trigger`).trigger('focus');

        expect(isOpen(first)).toBe(false);
        expect(isOpen(second)).toBe(true);
    });

    it('should not open another popover on hover while one is pinned', async () => {
        const first = await create('media');
        const second = await create('rule');

        await first.find(`.${className}__trigger`).trigger('click');
        await second.find(`.${className}__trigger`).trigger('mouseenter');
        jest.advanceTimersByTime(150);
        await flushPromises();

        expect(isOpen(first)).toBe(true);
        expect(isOpen(second)).toBe(false);
    });
});
