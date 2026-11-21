import { shallowMount } from '@vue/test-utils';
import type { ContentSystemElementTypeProperty } from 'src/core/service/api/content-system-element-type.api.service';
import settingsFieldsComponent from '../index';

describe('grid editor field', () => {
    it('passes the fallback rules to the declared editor and forwards updates under the field key', async () => {
        const layout = { lg: { columns: [{ type: 'auto' }], areas: [['a1']] } };
        const updated = { lg: { columns: [{ type: 'auto' }, { type: 'auto' }], areas: [['a1', 'a2']] } };
        const breakpointFallbacks = { xs: ['md', 'lg'] } as const;
        const wrapper = shallowMount(settingsFieldsComponent, {
            props: {
                fields: [
                    {
                        key: 'layout',
                        property: {
                            type: 'object',
                            title: 'Grid',
                            adminUI: { component: 'grid-editor' },
                        } as ContentSystemElementTypeProperty,
                    },
                ],
                values: { layout },
                allowEdit: true,
                breakpointFallbacks,
            },
            global: {
                provide: { repositoryFactory: {} },
                stubs: {
                    'sw-collapse': { template: '<div><slot name="content" /></div>' },
                    'sw-experience-studio-grid-editor': {
                        name: 'sw-experience-studio-grid-editor',
                        props: [
                            'gridLayout',
                            'label',
                            'allowEdit',
                            'breakpointFallbacks',
                        ],
                        emits: ['update:gridLayout'],
                        template: '<button @click="$emit(\'update:gridLayout\', next)"><slot /></button>',
                        data: () => ({ next: updated }),
                    },
                },
            },
        });

        const editor = wrapper.getComponent({ name: 'sw-experience-studio-grid-editor' });
        expect(editor.props('label')).toBe('Grid');
        expect(editor.props('gridLayout')).toEqual(layout);
        expect(editor.props('breakpointFallbacks')).toEqual(breakpointFallbacks);
        await editor.trigger('click');
        expect(wrapper.emitted('update-field')).toEqual([[{ key: 'layout', value: updated }]]);
        wrapper.unmount();
    });
});
