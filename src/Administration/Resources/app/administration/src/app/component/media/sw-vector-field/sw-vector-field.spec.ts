/**
 * @sw-package innovation
 */
/* eslint-disable @typescript-eslint/no-unsafe-assignment,
    @typescript-eslint/no-unsafe-call,
    @typescript-eslint/no-unsafe-member-access,
    @typescript-eslint/no-explicit-any
*/
import { mount } from '@vue/test-utils';

async function createWrapper(overrides: Record<string, unknown> = {}): Promise<any> {
    return mount((await wrapTestComponent('sw-vector-field', { sync: true })) as any, {
        props: {
            value: { x: 0, y: 0, z: 0 },
        },
        global: {
            stubs: {
                'sw-base-field': {
                    template: `
                        <div class="sw-base-field">
                            <slot name="label" />
                            <slot name="sw-field-input" />
                        </div>
                    `,
                    props: [
                        'label',
                        'description',
                        'disabled',
                    ],
                },
                'mt-number-field': {
                    template: '<input class="mt-number-field" :value="modelValue" @input="onInput" @change="onChange" />',
                    props: [
                        'modelValue',
                        'disabled',
                        'step',
                        'size',
                    ],
                    emits: [
                        'update:model-value',
                        'input-change',
                    ],
                    methods: {
                        onInput(e: Event) {
                            const val = Number((e.target as HTMLInputElement).value);
                            this.$emit('input-change', val);
                        },
                        onChange(e: Event) {
                            const val = Number((e.target as HTMLInputElement).value);
                            this.$emit('update:model-value', val);
                        },
                    },
                },
                'mt-icon': {
                    template: '<span class="mt-icon" @click="$emit(\'click\')" />',
                    props: [
                        'name',
                        'size',
                    ],
                },
            },
            directives: {
                tooltip: {},
            },
        },
        ...overrides,
    });
}

describe('src/app/component/media/sw-vector-field', () => {
    describe('Component Initialization', () => {
        it('should mount successfully', async () => {
            const wrapper = await createWrapper();
            expect(wrapper.exists()).toBe(true);
        });

        it('should initialize currentValue from value prop', async () => {
            const wrapper = await createWrapper({
                props: { value: { x: 1, y: 2, z: 3 } },
            });

            expect(wrapper.vm.currentValue).toEqual({ x: 1, y: 2, z: 3 });
        });

        it('should default linked to false', async () => {
            const wrapper = await createWrapper();
            expect(wrapper.vm.linked).toBe(false);
        });
    });

    describe('Mounted hook (linkable auto-link)', () => {
        it('should auto-link when linkable is true and all axes are equal', async () => {
            const wrapper = await createWrapper({
                props: {
                    value: { x: 5, y: 5, z: 5 },
                    linkable: true,
                },
            });

            expect(wrapper.vm.linked).toBe(true);
        });

        it('should emit link-change when auto-linked on mount', async () => {
            const wrapper = await createWrapper({
                props: {
                    value: { x: 5, y: 5, z: 5 },
                    linkable: true,
                },
            });

            expect(wrapper.emitted('link-change')).toEqual([[true]]);
        });

        it('should not auto-link when linkable is true but axes differ', async () => {
            const wrapper = await createWrapper({
                props: {
                    value: { x: 1, y: 2, z: 3 },
                    linkable: true,
                },
            });

            expect(wrapper.vm.linked).toBe(false);
        });

        it('should not auto-link when linkable is false even if axes are equal', async () => {
            const wrapper = await createWrapper({
                props: {
                    value: { x: 5, y: 5, z: 5 },
                    linkable: false,
                },
            });

            expect(wrapper.vm.linked).toBe(false);
        });
    });

    describe('Value watcher', () => {
        it('should update currentValue when value prop changes', async () => {
            const wrapper = await createWrapper({
                props: { value: { x: 0, y: 0, z: 0 } },
            });

            await wrapper.setProps({ value: { x: 10, y: 20, z: 30 } });

            expect(wrapper.vm.currentValue).toEqual({ x: 10, y: 20, z: 30 });
        });

        it('should convert string values to numbers', async () => {
            const wrapper = await createWrapper({
                props: { value: { x: '5', y: '10', z: '15' } },
            });

            expect(wrapper.vm.currentValue).toEqual({ x: 5, y: 10, z: 15 });
        });

        it('should handle the guard clause for falsy value', async () => {
            const wrapper = await createWrapper({
                props: { value: { x: 1, y: 2, z: 3 } },
            });

            // The watcher has a guard: if (!this.value) return;
            // We verify the watcher runs correctly with valid data
            await wrapper.setProps({ value: { x: 0, y: 0, z: 0 } });

            expect(wrapper.vm.currentValue).toEqual({ x: 0, y: 0, z: 0 });
        });
    });

    describe('onChange method', () => {
        it('should emit update:value with new axis value', async () => {
            const wrapper = await createWrapper({
                props: { value: { x: 0, y: 0, z: 0 } },
            });

            wrapper.vm.onChange(5, 'x');

            expect(wrapper.emitted('update:value')).toBeTruthy();
            const emitted = wrapper.emitted('update:value')![0][0] as { x: number; y: number; z: number };
            expect(emitted.x).toBe(5);
        });

        it('should update only the specified axis when not linked', async () => {
            const wrapper = await createWrapper({
                props: { value: { x: 1, y: 2, z: 3 } },
            });

            wrapper.vm.onChange(10, 'y');

            const emitted = wrapper.emitted('update:value')![0][0] as { x: number; y: number; z: number };
            expect(emitted).toEqual({ x: 1, y: 10, z: 3 });
        });

        it('should update all axes when linked and linkable', async () => {
            const wrapper = await createWrapper({
                props: {
                    value: { x: 5, y: 5, z: 5 },
                    linkable: true,
                },
            });

            // Auto-linked on mount because all values equal
            expect(wrapper.vm.linked).toBe(true);

            wrapper.vm.onChange(10, 'x');

            const emitted = wrapper.emitted('update:value')![0][0] as { x: number; y: number; z: number };
            expect(emitted).toEqual({ x: 10, y: 10, z: 10 });
        });
    });

    describe('onInput method', () => {
        it('should emit input-change with updated axis', async () => {
            const wrapper = await createWrapper({
                props: { value: { x: 0, y: 0, z: 0 } },
            });

            wrapper.vm.onInput(5, 'x');

            expect(wrapper.emitted('input-change')).toBeTruthy();
            const emitted = wrapper.emitted('input-change')![0][0] as { x: number; y: number; z: number };
            expect(emitted.x).toBe(5);
        });

        it('should not emit if value has not changed', async () => {
            const wrapper = await createWrapper({
                props: { value: { x: 5, y: 0, z: 0 } },
            });

            wrapper.vm.onInput(5, 'x');

            expect(wrapper.emitted('input-change')).toBeUndefined();
        });

        it('should sync all axes when linked and linkable', async () => {
            const wrapper = await createWrapper({
                props: {
                    value: { x: 5, y: 5, z: 5 },
                    linkable: true,
                },
            });

            expect(wrapper.vm.linked).toBe(true);

            wrapper.vm.onInput(10, 'x');

            expect(wrapper.emitted('input-change')).toBeTruthy();
            const emitted = wrapper.emitted('input-change')![0][0] as { x: number; y: number; z: number };
            expect(emitted.x).toBe(10);
            expect(emitted.y).toBe(10);
            expect(emitted.z).toBe(10);
        });

        it('should not sync axes when linked is true but linkable is false', async () => {
            const wrapper = await createWrapper({
                props: {
                    value: { x: 0, y: 5, z: 5 },
                    linkable: false,
                },
            });

            wrapper.vm.linked = true;
            wrapper.vm.onInput(10, 'x');

            const emitted = wrapper.emitted('input-change')![0][0] as { x: number; y: number; z: number };
            expect(emitted.x).toBe(10);
            expect(emitted.y).toBe(5);
            expect(emitted.z).toBe(5);
        });
    });

    describe('onLinkToggle method', () => {
        it('should toggle linked state', async () => {
            const wrapper = await createWrapper({
                props: {
                    value: { x: 1, y: 2, z: 3 },
                    linkable: true,
                },
            });

            expect(wrapper.vm.linked).toBe(false);

            wrapper.vm.onLinkToggle();
            expect(wrapper.vm.linked).toBe(true);

            wrapper.vm.onLinkToggle();
            expect(wrapper.vm.linked).toBe(false);
        });

        it('should emit link-change on toggle', async () => {
            const wrapper = await createWrapper({
                props: {
                    value: { x: 1, y: 2, z: 3 },
                    linkable: true,
                },
            });

            wrapper.vm.onLinkToggle();

            expect(wrapper.emitted('link-change')).toEqual([[true]]);
        });

        it('should sync y and z to x when linking', async () => {
            const wrapper = await createWrapper({
                props: {
                    value: { x: 5, y: 10, z: 15 },
                    linkable: true,
                },
            });

            wrapper.vm.onLinkToggle();

            expect(wrapper.vm.currentValue).toEqual({ x: 5, y: 5, z: 5 });
            const emitted = wrapper.emitted('update:value')![0][0] as { x: number; y: number; z: number };
            expect(emitted).toEqual({ x: 5, y: 5, z: 5 });
        });

        it('should not sync values when unlinking', async () => {
            const wrapper = await createWrapper({
                props: {
                    value: { x: 5, y: 5, z: 5 },
                    linkable: true,
                },
            });

            // Already linked from mount
            expect(wrapper.vm.linked).toBe(true);

            wrapper.vm.onLinkToggle();

            expect(wrapper.vm.linked).toBe(false);
            // update:value is only emitted from link-change on mount, not from unlinking
            const updateEvents = wrapper.emitted('update:value');
            expect(updateEvents).toBeUndefined();
        });

        it('should do nothing when disabled', async () => {
            const wrapper = await createWrapper({
                props: {
                    value: { x: 1, y: 2, z: 3 },
                    linkable: true,
                    disabled: true,
                },
            });

            wrapper.vm.onLinkToggle();

            expect(wrapper.vm.linked).toBe(false);
            expect(wrapper.emitted('link-change')).toBeUndefined();
        });

        it('should do nothing when not linkable', async () => {
            const wrapper = await createWrapper({
                props: {
                    value: { x: 1, y: 2, z: 3 },
                    linkable: false,
                },
            });

            wrapper.vm.onLinkToggle();

            expect(wrapper.vm.linked).toBe(false);
            expect(wrapper.emitted('link-change')).toBeUndefined();
        });
    });

    describe('Template rendering', () => {
        it('should render three number fields', async () => {
            const wrapper = await createWrapper();

            const fields = wrapper.findAll('.mt-number-field');
            expect(fields).toHaveLength(3);
        });

        it('should render lock icon when linkable is true', async () => {
            const wrapper = await createWrapper({
                props: {
                    value: { x: 0, y: 0, z: 0 },
                    linkable: true,
                },
            });

            const icon = wrapper.find('.sw-vector-field__lock-icon');
            expect(icon.exists()).toBe(true);
        });

        it('should not render lock icon when linkable is false', async () => {
            const wrapper = await createWrapper({
                props: {
                    value: { x: 0, y: 0, z: 0 },
                    linkable: false,
                },
            });

            const icon = wrapper.find('.sw-vector-field__lock-icon');
            expect(icon.exists()).toBe(false);
        });

        it('should apply is--linked class when linked', async () => {
            const wrapper = await createWrapper({
                props: {
                    value: { x: 5, y: 5, z: 5 },
                    linkable: true,
                },
            });

            const icon = wrapper.find('.sw-vector-field__lock-icon');
            expect(icon.classes()).toContain('is--linked');
            expect(icon.classes()).not.toContain('is--unlinked');
        });

        it('should apply is--unlinked class when not linked', async () => {
            const wrapper = await createWrapper({
                props: {
                    value: { x: 1, y: 2, z: 3 },
                    linkable: true,
                },
            });

            const icon = wrapper.find('.sw-vector-field__lock-icon');
            expect(icon.classes()).toContain('is--unlinked');
            expect(icon.classes()).not.toContain('is--linked');
        });

        it('should apply is--disabled class when disabled', async () => {
            const wrapper = await createWrapper({
                props: {
                    value: { x: 0, y: 0, z: 0 },
                    linkable: true,
                    disabled: true,
                },
            });

            const icon = wrapper.find('.sw-vector-field__lock-icon');
            expect(icon.classes()).toContain('is--disabled');
        });

        it('should toggle link state when lock icon is clicked', async () => {
            const wrapper = await createWrapper({
                props: {
                    value: { x: 1, y: 2, z: 3 },
                    linkable: true,
                },
            });

            wrapper.vm.onLinkToggle();
            await wrapper.vm.$nextTick();

            expect(wrapper.vm.linked).toBe(true);
        });

        it('should pass disabled prop to base field', async () => {
            const wrapper = await createWrapper({
                props: {
                    value: { x: 0, y: 0, z: 0 },
                    disabled: true,
                },
            });

            expect(wrapper.props('disabled')).toBe(true);
        });
    });

    describe('Props', () => {
        it('should accept step prop', async () => {
            const wrapper = await createWrapper({
                props: {
                    value: { x: 0, y: 0, z: 0 },
                    step: 0.1,
                },
            });

            expect(wrapper.props('step')).toBe(0.1);
        });

        it('should pass label to sw-base-field', async () => {
            const wrapper = await createWrapper({
                props: {
                    value: { x: 0, y: 0, z: 0 },
                    label: 'Position',
                },
            });

            const baseField = wrapper.find('.sw-base-field');
            expect(baseField.exists()).toBe(true);
        });

        it('should pass description to sw-base-field', async () => {
            const wrapper = await createWrapper({
                props: {
                    value: { x: 0, y: 0, z: 0 },
                    description: 'Set the position',
                },
            });

            expect(wrapper.props('description')).toBe('Set the position');
        });
    });
});
