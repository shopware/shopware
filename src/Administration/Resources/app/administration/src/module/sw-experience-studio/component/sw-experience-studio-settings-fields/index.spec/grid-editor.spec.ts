import { shallowMount } from '@vue/test-utils';
import type { ContentSystemElementTypeProperty } from 'src/core/service/api/content-system-element-type.api.service';
import settingsFieldsComponent from '../index';

describe('grid editor field', () => {
    it('renders the declared editor and forwards its value under the field key', async () => {
        const layout = { xs: { columns: [{ type: 'auto' }], areas: [['a1']] } };
        const updated = { xs: { columns: [{ type: 'auto' }, { type: 'auto' }], areas: [['a1', 'a2']] } };
        const wrapper = shallowMount(settingsFieldsComponent, {
            props: {
                fields: [
                    {
                        key: 'layout',
                        property: {
                            type: 'object',
                            adminUI: { component: 'grid-editor' },
                        } as ContentSystemElementTypeProperty,
                    },
                ],
                values: { layout },
                allowEdit: true,
            },
            global: {
                provide: { repositoryFactory: {} },
                stubs: {
                    'sw-collapse': { template: '<div><slot name="content" /></div>' },
                    'sw-experience-studio-grid-editor': {
                        name: 'sw-experience-studio-grid-editor',
                        props: ['gridLayout', 'allowEdit'],
                        emits: ['update:gridLayout'],
                        template: '<button @click="$emit(\'update:gridLayout\', next)"><slot /></button>',
                        data: () => ({ next: updated }),
                    },
                },
            },
        });

        const editor = wrapper.getComponent({ name: 'sw-experience-studio-grid-editor' });
        expect(editor.props('gridLayout')).toEqual(layout);
        await editor.trigger('click');
        expect(wrapper.emitted('update-field')).toEqual([[{ key: 'layout', value: updated }]]);
        wrapper.unmount();
    });
});
