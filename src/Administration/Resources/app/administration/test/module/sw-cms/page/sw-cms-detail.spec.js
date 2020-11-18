import { shallowMount, createLocalVue } from '@vue/test-utils';
import Vuex from 'vuex';
import 'src/module/sw-cms/state/cms-page.state';
import 'src/module/sw-cms/mixin/sw-cms-state.mixin';
import 'src/module/sw-cms/page/sw-cms-detail';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-cms-detail'), {
        localVue,
        stubs: {
            'sw-page': true,
            'sw-cms-toolbar': true,
            'sw-language-switch': true,
            'sw-router-link': true,
            'sw-icon': true,
            'router-link': true,
            'sw-button-process': true,
            'sw-cms-stage-add-section': true,
            'sw-cms-sidebar': true,
            'sw-loader': true,
            'sw-cms-section': true
        },
        mocks: {
            $store: Shopware.State._store,
            $tc: (value) => value,
            $route: { params: { id: '1a' } },
            $device: {
                getSystemKey: () => 'Strg'
            }
        },
        provide: {
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            },
            repositoryFactory: {
                create: () => {
                    return {
                        search: () => Promise.resolve([{}]),
                        get: () => Promise.resolve({
                            sections: [
                                {}
                            ]
                        })
                    };
                }
            },
            entityFactory: {},
            entityHydrator: {},
            loginService: {},
            cmsPageService: {},
            cmsService: {},
            cmsDataResolverService: {}
        }
    });
}

describe('module/sw-cms/page/sw-cms-detail', () => {
    let cmsPageStateBackup;

    beforeAll(() => {
        cmsPageStateBackup = { ...Shopware.State._store.state.cmsPageState };
    });

    beforeEach(() => {
        Shopware.State._store.state.cmsPageState = { ...cmsPageStateBackup };
    });

    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should disable all fields when acl rights are missing', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        await wrapper.setData({
            isLoading: false
        });

        const formIcon = wrapper.find('sw-icon-stub[name="default-basic-stack-block"]');
        expect(formIcon.classes()).toContain('is--disabled');

        const saveAction = wrapper.find('.sw-cms-detail__save-action');
        expect(saveAction.attributes().disabled).toBe('true');

        const cmsStageAddSections = wrapper.findAll('sw-cms-stage-add-section-stub');
        expect(cmsStageAddSections.length).toBe(2);
        cmsStageAddSections.wrappers.forEach(cmsStageAddSection => {
            expect(cmsStageAddSection.attributes().disabled).toBe('true');
        });

        const stageSection = wrapper.find('.sw-cms-stage-section');
        expect(stageSection.attributes().disabled).toBe('true');

        const cmsSidebar = wrapper.find('sw-cms-sidebar-stub');
        expect(cmsSidebar.attributes().disabled).toBe('true');
    });

    it('should enable all fields when acl rights are missing', async () => {
        const wrapper = createWrapper([
            'cms.editor'
        ]);
        await wrapper.vm.$nextTick();
        await wrapper.setData({
            isLoading: false
        });

        const formIcon = wrapper.find('sw-icon-stub[name="default-basic-stack-block"]');
        expect(formIcon.classes()).not.toContain('is--disabled');

        const saveAction = wrapper.find('.sw-cms-detail__save-action');
        expect(saveAction.attributes().disabled).toBeUndefined();

        const cmsStageAddSections = wrapper.findAll('sw-cms-stage-add-section-stub');
        expect(cmsStageAddSections.length).toBe(2);
        cmsStageAddSections.wrappers.forEach(cmsStageAddSection => {
            expect(cmsStageAddSection.attributes().disabled).toBeUndefined();
        });

        const stageSection = wrapper.find('.sw-cms-stage-section');
        expect(stageSection.attributes().disabled).toBeUndefined();

        const cmsSidebar = wrapper.find('sw-cms-sidebar-stub');
        expect(cmsSidebar.attributes().disabled).toBeUndefined();
    });
});
