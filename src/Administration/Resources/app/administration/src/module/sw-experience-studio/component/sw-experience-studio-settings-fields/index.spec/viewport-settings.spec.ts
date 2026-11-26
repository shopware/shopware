import { shallowMount } from '@vue/test-utils';
import type { ContentSystemElementTypeProperty } from 'src/core/service/api/content-system-element-type.api.service';
import BreakpointSelect from '../../sw-experience-studio-breakpoint-select';
import settingsFields from '../index';

const visibility = {
    key: 'display',
    breakpointAware: true,
    property: {
        type: 'boolean',
        title: 'Display',
        default: true,
        adminUI: { component: 'switch' },
    } as ContentSystemElementTypeProperty,
};

function createWrapper(values: Record<string, unknown>, field = visibility) {
    return shallowMount(settingsFields, {
        props: {
            fields: [field],
            values,
            allowEdit: true,
            breakpointFallbacks: { xs: ['sm', 'lg'], sm: ['lg'], md: ['lg'], lg: [], xl: ['lg'], xxl: ['xl', 'lg'] },
        },
        global: {
            provide: { repositoryFactory: {} },
            stubs: {
                'sw-collapse': { template: '<div><slot name="content" /></div>' },
                BreakpointSelect: {
                    props: ['modelValue', 'overrides', 'canReset'],
                    emits: ['update:modelValue', 'reset'],
                    template: `<div>
                        <select aria-label="Viewport" :value="modelValue" @change="$emit('update:modelValue', $event.target.value)">
                            <option v-for="size in ['xs', 'sm', 'md', 'lg', 'xl', 'xxl']" :key="size">{{ size }}</option>
                        </select>
                        <button v-if="canReset && overrides.includes(modelValue)" class="reset-viewport" @click="$emit('reset')">Reset</button>
                    </div>`,
                },
                'mt-slider': {
                    props: ['modelValue'],
                    emits: ['update:modelValue'],
                    template: `<input type="number" :value="modelValue" @input="$emit('update:modelValue', $event.target.valueAsNumber)">`,
                },
                'mt-switch': {
                    props: ['modelValue'],
                    emits: ['update:modelValue'],
                    template: `<button class="viewport-switch" @click="$emit('update:modelValue', !modelValue)">{{ modelValue }}</button>`,
                },
                'mt-text-field': {
                    props: ['modelValue'],
                    emits: ['update:modelValue'],
                    template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)">',
                },
            },
        },
    });
}

describe('responsive settings field', () => {
    let wrapper: ReturnType<typeof createWrapper>;

    afterEach(() => wrapper?.unmount());

    it('keeps false as an explicit SM override and inherits it at XS only', async () => {
        wrapper = createWrapper({ display: { lg: true, sm: false } });
        const select = wrapper.getComponent(BreakpointSelect);
        const control = () => wrapper.get('.viewport-switch');
        expect(select.props('modelValue')).toBe('lg');
        expect(select.props('overrides')).toEqual(['sm']);
        expect(control().text()).toBe('true');

        await wrapper.get('select[aria-label="Viewport"]').setValue('xs');
        expect(control().text()).toBe('false');
        expect(select.props('overrides')).toEqual(['sm']);
        expect(wrapper.emitted('update-field')).toBeUndefined();

        await wrapper.get('select[aria-label="Viewport"]').setValue('md');
        expect(control().text()).toBe('true');
        await control().trigger('click');
        expect(wrapper.emitted('update-field')).toEqual([[{ key: 'display', value: { lg: true, sm: false, md: false } }]]);
    });

    it('inherits an explicit zero from XL at XXL and can override it', async () => {
        wrapper = createWrapper(
            { size: { lg: 6, xl: 0 } },
            {
                key: 'size',
                breakpointAware: true,
                property: {
                    ...visibility.property,
                    type: 'integer',
                    title: 'Size',
                    default: 0,
                    adminUI: { component: 'responsive-number', props: { min: 0, max: 12 } },
                },
            },
        );
        const select = wrapper.getComponent(BreakpointSelect);
        const input = () => wrapper.get<HTMLInputElement>('.sw-experience-studio-settings-fields__responsive-control input');
        expect(select.props('overrides')).toEqual(['xl']);

        await wrapper.get('select[aria-label="Viewport"]').setValue('xl');
        expect(input().element.value).toBe('0');

        await wrapper.get('select[aria-label="Viewport"]').setValue('xxl');
        expect(input().element.value).toBe('0');
        await input().setValue('1');
        expect(wrapper.emitted('update-field')).toEqual([[{ key: 'size', value: { lg: 6, xl: 0, xxl: 1 } }]]);
    });

    it('updates the displayed field when the supplied fallback order changes', async () => {
        wrapper = createWrapper({ display: { lg: true, sm: false, md: true } });
        await wrapper.get('select[aria-label="Viewport"]').setValue('xs');
        expect(wrapper.get('.viewport-switch').text()).toBe('false');

        await wrapper.setProps({ breakpointFallbacks: { xs: ['md', 'sm', 'lg'] } });
        expect(wrapper.get('.viewport-switch').text()).toBe('true');
        expect(wrapper.emitted('update-field')).toBeUndefined();
    });

    it('resets only the selected field value and shows the inherited value', async () => {
        const remaining = { lg: true, sm: false, xl: false };
        wrapper = createWrapper({ display: { ...remaining, xs: true } });
        const selector = wrapper.get<HTMLSelectElement>('select[aria-label="Viewport"]');

        await selector.setValue('xs');
        expect(wrapper.get('.viewport-switch').text()).toBe('true');
        await wrapper.get('.reset-viewport').trigger('click');

        expect(wrapper.emitted('update-field')).toEqual([[{ key: 'display', value: remaining }]]);
        await wrapper.setProps({ values: { display: remaining } });
        expect(selector.element.value).toBe('xs');
        expect(wrapper.get('.viewport-switch').text()).toBe('false');
        expect(wrapper.find('.reset-viewport').exists()).toBe(false);
    });

    it('retains a legacy scalar as Base when adding an override', async () => {
        wrapper = createWrapper({ display: false });
        await wrapper.get('select[aria-label="Viewport"]').setValue('xl');

        await wrapper.get('.viewport-switch').trigger('click');
        expect(wrapper.emitted('update-field')).toEqual([[{ key: 'display', value: { lg: false, xl: true } }]]);
    });

    it('shows the declared Base default without storing it when adding the first override', async () => {
        wrapper = createWrapper({});
        await wrapper.get('select[aria-label="Viewport"]').setValue('sm');

        const control = wrapper.get('.viewport-switch');
        expect(control.text()).toBe('true');
        await control.trigger('click');

        expect(wrapper.emitted('update-field')).toEqual([[{ key: 'display', value: { sm: false } }]]);
        await wrapper.setProps({ values: { display: { sm: false } } });
        await wrapper.get('select[aria-label="Viewport"]').setValue('lg');
        expect(wrapper.get('.viewport-switch').text()).toBe('true');
    });

    it('leaves Base unset when the field has no non-empty default', async () => {
        const field = {
            ...visibility,
            key: 'label',
            property: { ...visibility.property, type: 'string', default: null, adminUI: { component: 'text' } },
        };
        wrapper = createWrapper({}, field);
        await wrapper.get('select[aria-label="Viewport"]').setValue('sm');
        await wrapper.get('.sw-experience-studio-settings-fields__responsive-control input').setValue('Mobile');

        expect(wrapper.emitted('update-field')).toEqual([[{ key: 'label', value: { sm: 'Mobile' } }]]);
    });

    it('clears the field when resetting its only override', async () => {
        wrapper = createWrapper({ display: { sm: false } });
        await wrapper.get('select[aria-label="Viewport"]').setValue('sm');
        await wrapper.get('.reset-viewport').trigger('click');

        expect(wrapper.emitted('update-field')).toEqual([[{ key: 'display', value: null }]]);
    });

    it('ignores an update from a responsive control in read-only mode', async () => {
        wrapper = createWrapper({ display: { lg: true } });
        await wrapper.setProps({ allowEdit: false });
        const control = wrapper.get<HTMLButtonElement>('.viewport-switch');
        expect(control.element.disabled).toBe(true);
        control.element.removeAttribute('disabled');
        await control.trigger('click');

        expect(wrapper.emitted('update-field')).toBeUndefined();
    });
});
