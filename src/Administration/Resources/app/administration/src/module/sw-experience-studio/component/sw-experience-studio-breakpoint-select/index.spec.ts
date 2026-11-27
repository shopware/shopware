import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { nextTick } from 'vue';
import { MtDropdownMenuRoot, MtIcon, MtStatusDot } from '@shopware-ag/meteor-component-library';
import breakpointSelect, { type GridBreakpoint } from './index';

describe('Experience Studio breakpoint selection', () => {
    let wrapper: VueWrapper;

    afterEach(() => wrapper?.unmount());

    const menu = () => document.body.querySelector('[role="menu"]');
    const options = (): HTMLElement[] => Array.from(document.body.querySelectorAll('[role="menuitemradio"]'));
    const labels = () => options().map((option) => option.getAttribute('data-breakpoint'));
    const toggle = () => menu()?.querySelector<HTMLButtonElement>('.sw-experience-studio-breakpoint-select__toggle');

    async function openMenu() {
        await wrapper.get('button').trigger('keydown', { key: 'ArrowDown' });
        await flushPromises();
    }

    it('opens a short menu and expands the same overlay in size order', async () => {
        wrapper = mount(breakpointSelect, { props: { modelValue: 'lg', overrides: [] }, attachTo: document.body });

        expect(wrapper.get('button').text()).toBe('sw-experience-studio.breakpointSelect.base');
        expect(wrapper.get('button').getComponent(MtIcon).props('name')).toBe('regular-desktop');
        expect(menu()).toBeNull();
        await openMenu();
        const overlay = menu();
        expect(labels()).toEqual(['sm', 'md', 'lg']);
        expect(
            options().map((option) =>
                Array.from(option.querySelectorAll('.sw-experience-studio-breakpoint-select__detail'))
                    .at(-1)
                    ?.textContent?.trim(),
            ),
        ).toEqual(['SM', 'MD', 'sw-experience-studio.breakpointSelect.base']);
        expect(options().find((option) => option.getAttribute('aria-checked') === 'true')?.dataset.breakpoint).toBe('lg');

        toggle()?.click();
        await nextTick();
        expect(menu()).toBe(overlay);
        expect(labels()).toEqual([
            'xs',
            'sm',
            'md',
            'lg',
            'xl',
            'xxl',
        ]);
        expect(options().at(-1)?.textContent).toContain('ultrawide');
        expect(options().at(-1)?.textContent).toContain('XXL');
        expect(
            options().every((option) => option.querySelector('.sw-experience-studio-breakpoint-select__device svg')),
        ).toBe(true);

        toggle()?.click();
        await nextTick();
        expect(menu()).toBe(overlay);
        expect(labels()).toEqual(['sm', 'md', 'lg']);
    });

    it('dismisses on a pointer press outside the menu without changing the selection', async () => {
        wrapper = mount(breakpointSelect, { props: { modelValue: 'md', overrides: [] }, attachTo: document.body });
        await openMenu();
        expect(menu()).not.toBeNull();
        expect(getComputedStyle(document.body).pointerEvents).not.toBe('none');

        // Reka attaches the outside pointer listener on the next task.
        await new Promise<void>((resolve) => {
            window.setTimeout(resolve, 0);
        });
        document.body.dispatchEvent(new MouseEvent('pointerdown', { bubbles: true }));
        await flushPromises();

        expect(menu()).toBeNull();
        expect(wrapper.get('button').text()).toBe('MD');
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('closes when the window loses focus to the preview iframe', async () => {
        wrapper = mount(breakpointSelect, { props: { modelValue: 'md', overrides: [] }, attachTo: document.body });
        await openMenu();
        expect(menu()).not.toBeNull();

        window.dispatchEvent(new Event('blur'));
        await flushPromises();

        expect(menu()).toBeNull();
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('keeps a single blur listener when the dropdown repeats its open event', async () => {
        wrapper = mount(breakpointSelect, { props: { modelValue: 'md', overrides: [] }, attachTo: document.body });
        const add = jest.spyOn(window, 'addEventListener');

        try {
            await openMenu();
            wrapper.getComponent(MtDropdownMenuRoot).vm.$emit('update:open', true);
            expect(add.mock.calls.filter(([event]) => event === 'blur')).toHaveLength(1);

            window.dispatchEvent(new Event('blur'));
            await flushPromises();
            expect(menu()).toBeNull();
        } finally {
            add.mockRestore();
        }
    });

    it('shows the override count and ignores a stale reset click after switching to Base', async () => {
        let deliverResetClick: (() => void) | undefined;
        wrapper = mount(breakpointSelect, {
            props: { modelValue: 'sm', overrides: ['sm', 'xl'], canReset: true },
            global: {
                stubs: {
                    'mt-link': {
                        setup(_, { attrs }) {
                            deliverResetClick = attrs.onClick as () => void;
                        },
                        template: '<button type="button"><slot /></button>',
                    },
                },
                mocks: {
                    $t: (key: string, values?: { count?: number }) =>
                        key === 'sw-experience-studio.breakpointSelect.overrideCount' ? String(values?.count) : key,
                },
            },
        });
        const reset = '.sw-experience-studio-breakpoint-select__reset';
        expect(wrapper.find(reset).exists()).toBe(true);
        expect(deliverResetClick).toEqual(expect.any(Function));

        await wrapper.setProps({ modelValue: 'lg' });

        expect(wrapper.get('.sw-experience-studio-breakpoint-select__hint').text()).toBe('2');
        expect(wrapper.find(reset).exists()).toBe(false);
        deliverResetClick!();
        expect(wrapper.emitted('reset')).toBeUndefined();
    });

    it('shows the desktop size when the selected breakpoint is unrecognized', () => {
        wrapper = mount(breakpointSelect, { props: { modelValue: 'unknown' as GridBreakpoint, overrides: [] } });

        expect(wrapper.get('button').text()).toBe('LG');
        expect(wrapper.getComponent(MtIcon).props('name')).toBe('regular-desktop');
    });

    it.each(['sm', 'xxl'] as const)('replaces the override status with Reset when %s is selected', async (breakpoint) => {
        wrapper = mount(breakpointSelect, {
            props: { modelValue: 'lg', overrides: [breakpoint], canReset: true },
            attachTo: document.body,
        });
        const reset = '.sw-experience-studio-breakpoint-select__reset';
        const status = () => wrapper.get('.sw-experience-studio-breakpoint-select__hint');

        expect(status().text()).toBe('sw-experience-studio.breakpointSelect.singleOverride');
        expect(wrapper.find(reset).exists()).toBe(false);
        await wrapper.setProps({ modelValue: 'xs' });
        expect(status().text()).toBe('sw-experience-studio.breakpointSelect.singleOverride');

        await wrapper.setProps({ modelValue: breakpoint });
        expect(status().text()).toBe('sw-experience-studio.breakpointSelect.reset');
        expect(status().getComponent(MtStatusDot).props('variant')).toBe('info');
        await status().get(reset).trigger('click');

        expect(wrapper.emitted('reset')).toEqual([[]]);
        expect(document.activeElement).toBe(wrapper.get('.sw-experience-studio-breakpoint-select__trigger').element);

        await wrapper.setProps({ canReset: false });
        expect(wrapper.find(reset).exists()).toBe(false);
    });

    it('keeps selected and overridden sizes in the short menu and emits the selection', async () => {
        wrapper = mount(breakpointSelect, {
            props: { modelValue: 'xs', overrides: ['xxl'] },
            attachTo: document.body,
            global: {
                mocks: {
                    $t: (key: string, values?: { size?: string }) =>
                        key === 'sw-experience-studio.breakpointSelect.singleOverride' ? `${values?.size} override` : key,
                },
            },
        });

        expect(wrapper.get('button').text()).toBe('XS');
        expect(wrapper.get('button').getComponent(MtIcon).props('name')).toBe('regular-mobile');
        expect(wrapper.get('.sw-experience-studio-breakpoint-select__hint').text()).toBe('XXL override');
        await openMenu();
        expect(labels()).toEqual([
            'xs',
            'sm',
            'md',
            'lg',
            'xxl',
        ]);
        expect(
            options().at(-1)?.querySelector('[aria-label="sw-experience-studio.breakpointSelect.override"]'),
        ).not.toBeNull();
        options().at(-1)?.click();
        await flushPromises();

        expect(wrapper.emitted('update:modelValue')).toEqual([['xxl']]);
        expect(menu()).toBeNull();
        expect(wrapper.find('.sw-experience-studio-breakpoint-select__hint').exists()).toBe(true);
    });
});
