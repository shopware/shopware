import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-event-action/component/sw-event-action-detail-recipients';
import 'src/app/component/data-grid/sw-data-grid';

function createWrapper(configRecipients = null, privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-event-action-detail-recipients'), {
        localVue,
        mocks: {
            $tc: (translationPath) => translationPath,
            $te: (translationPath) => translationPath,
            $device: {
                onResize: () => {}
            }
        },
        stubs: {
            'sw-button': {
                template: '<button class="sw-button"><slot></slot></button>'
            },
            'sw-card': {
                template: '<div class="sw-card"><slot name="toolbar"></slot><slot></slot><slot name="grid"></slot></div>'
            },
            'sw-data-grid': Shopware.Component.build('sw-data-grid'),
            'sw-empty-state': true,
            'sw-context-button': true,
            'sw-context-menu-item': true,
            'sw-icon': true,
            'sw-text-field': {
                props: ['value'],
                template: '<input class="sw-text-field" :value="value" @input="$emit(\'input\', $event.target.value)" />'
            }
        },
        propsData: {
            configRecipients: configRecipients,
            isLoading: false
        },
        provide: {
            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }

                    return privileges.includes(identifier);
                }
            }
        }
    });
}

describe('src/module/sw-event-action/component/sw-event-action-detail-recipients', () => {
    it('should be instantiated', () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render empty state when no recipients are given', () => {
        const wrapper = createWrapper();
        expect(wrapper.find('sw-empty-state-stub').exists()).toBeTruthy();
    });

    it('should convert recipients object to array when recipients are given', async () => {
        const wrapper = createWrapper({
            'test@example.com': 'Test example name',
            'info@shopware.com': 'Info'
        });

        await wrapper.vm.$nextTick();

        // Empty state should not be present
        expect(wrapper.find('sw-empty-state-stub').exists()).toBeFalsy();

        // Verify emails and names are inside dataProp recipients array including random ids
        expect(wrapper.vm.recipients[0]).toEqual(expect.objectContaining({
            email: 'test@example.com',
            name: 'Test example name',
            id: expect.any(String)
        }));
        expect(wrapper.vm.recipients[1]).toEqual(expect.objectContaining({
            email: 'info@shopware.com',
            name: 'Info',
            id: expect.any(String)
        }));
    });

    it('should render given recipients inside data-grid', async () => {
        const wrapper = createWrapper({
            'test@example.com': 'Test example name',
            'info@shopware.com': 'Info'
        });

        await wrapper.vm.$nextTick();

        const rowItem0 = wrapper.find('.sw-data-grid__body .sw-data-grid__row--0');
        const cellEmailItem0 = rowItem0.find('.sw-data-grid__cell--email .sw-data-grid__cell-content');
        const cellNameItem0 = rowItem0.find('.sw-data-grid__cell--name .sw-data-grid__cell-content');

        const rowItem1 = wrapper.find('.sw-data-grid__body .sw-data-grid__row--1');
        const cellEmailItem1 = rowItem1.find('.sw-data-grid__cell--email .sw-data-grid__cell-content');
        const cellNameItem1 = rowItem1.find('.sw-data-grid__cell--name .sw-data-grid__cell-content');

        expect(cellEmailItem0.text()).toEqual('test@example.com');
        expect(cellNameItem0.text()).toEqual('Test example name');
        expect(cellEmailItem1.text()).toEqual('info@shopware.com');
        expect(cellNameItem1.text()).toEqual('Info');
    });

    it('should enable inline edit with empty item when adding recipient', async () => {
        const wrapper = createWrapper({
            'test@example.com': 'Test example name',
            'info@shopware.com': 'Info'
        });

        await wrapper.vm.$nextTick();

        // Perform add action
        wrapper.vm.addRecipient();

        await wrapper.vm.$nextTick();

        // Expect new overall length
        expect(wrapper.vm.recipients.length).toBe(3);

        // Expect new item to be in recipients array
        expect(wrapper.vm.recipients[0]).toEqual(expect.objectContaining({
            email: '',
            name: '',
            id: expect.any(String)
        }));

        // Expect inline-edit to be active with correct id
        expect(wrapper.vm.$refs.recipientsGrid.currentInlineEditId).toBe(wrapper.vm.recipients[0].id);
        expect(wrapper.vm.$refs.recipientsGrid.isInlineEditActive).toBeTruthy();
    });

    it('should delete recipient', async () => {
        const wrapper = createWrapper({
            'test@example.com': 'Test example name',
            'info@shopware.com': 'Info',
            'info@delete-me.net': 'Delete me'
        });

        await wrapper.vm.$nextTick();

        const itemToDelete = wrapper.vm.recipients[2].email;

        // Perform delete action
        wrapper.vm.onDeleteRecipient(itemToDelete.id);

        await wrapper.vm.$nextTick();

        // Verify deleted item is not in recipients array
        expect(wrapper.vm.recipients).toEqual(
            expect.not.arrayContaining([
                expect.objectContaining({
                    email: 'info@delete-me.net'
                })
            ])
        );

        // Expect update-list event to be emitted
        expect(wrapper.emitted('update-list')[0][0]).toEqual(wrapper.vm.recipients);
    });

    it('should add recipient', async () => {
        const wrapper = createWrapper({
            'test@example.com': 'Test example name',
            'info@shopware.com': 'Info'
        });

        await wrapper.vm.$nextTick();

        wrapper.vm.addRecipient();

        await wrapper.vm.$nextTick();

        const item0 = wrapper.find('.sw-data-grid__body .sw-data-grid__row--0');
        const emailInput = item0.find('.sw-data-grid__cell--email .sw-text-field');
        const nameInput = item0.find('.sw-data-grid__cell--name .sw-text-field');

        // Enter inline-edit data
        emailInput.setValue('new@mail.com');
        nameInput.setValue('New mail');

        // Verify data has been set
        const updatedRecipient = wrapper.vm.recipients[0];
        expect(updatedRecipient.email).toBe('new@mail.com');
        expect(updatedRecipient.name).toBe('New mail');

        // Perform save
        wrapper.vm.saveRecipient(updatedRecipient);

        // Expect update-list event to be fired
        expect(wrapper.emitted('update-list')[0][0]).toEqual(wrapper.vm.recipients);
    });

    it('should edit recipient', async () => {
        const wrapper = createWrapper({
            'test@example.com': 'Test example name',
            'info@shopware.com': 'Info',
            'edit@me.me': 'Edit this'
        });

        await wrapper.vm.$nextTick();

        const editRecipient = wrapper.vm.recipients[2];

        wrapper.vm.onEditRecipient(editRecipient.id);

        // Expect inline-edit to be active with correct id
        expect(wrapper.vm.$refs.recipientsGrid.currentInlineEditId).toBe(editRecipient.id);
        expect(wrapper.vm.$refs.recipientsGrid.isInlineEditActive).toBeTruthy();
    });

    it('should disable edit actions with viewer privileges', async () => {
        const wrapper = createWrapper({
            'test@example.com': 'Test example name',
            'info@shopware.com': 'Info'
        }, [
            'event_action.viewer'
        ]);

        await wrapper.vm.$nextTick();

        // Expect add button to be disabled
        expect(wrapper.find('.sw-event-action-detail-recipients__action-add').attributes().disabled).toBeTruthy();

        // Expect data-grid edit actions to be disabled
        expect(wrapper.find('.sw-event-action-detail-recipients__grid').props().allowInlineEdit).toBeFalsy();

        const contextMenuItems = wrapper.findAll('.sw-event-action-detail-recipients__grid-action-edit');
        contextMenuItems.wrappers.forEach((item) => {
            expect(item.attributes().disabled).toBeTruthy();
        });
    });
});
