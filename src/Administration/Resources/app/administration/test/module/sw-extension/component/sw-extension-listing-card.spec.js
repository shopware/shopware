import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-extension/component/sw-extension-listing-card';
import ShopwareService from 'src/module/sw-extension/service/shopware-extension.service';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-extension-listing-card'), {
        propsData: {
            extension: {
                id: 1,
                label: 'Test',
                name: 'Test',
                variants: [
                    {
                        id: 79102,
                        type: 'free',
                        netPrice: 0,
                        trialPhaseIncluded: true
                    }
                ]
            }
        },
        mocks: {
            $tc: v => v
        },
        stubs: {
            'sw-icon': true,
            'sw-extension-rating-stars': true,
            'router-link': true
        },
        provide: {
            repositoryFactory: {
                create: () => {
                    return {};
                }
            },
            shopwareExtensionService: new ShopwareService({}, {}, {}, {})
        }
    });
}

describe('src/module/sw-extension/component/sw-extension-listing-card', () => {
    /** @type Wrapper */
    let wrapper;

    beforeAll(() => {
        Shopware.State.registerModule('shopwareExtensions', {
            namespaced: true,
            state: {
                myExtensions: {
                    data: [
                        {
                            name: 'Test',
                            installedAt: null
                        }
                    ]
                }
            },
            mutations: {
                setExtension(state, extension) {
                    state.myExtensions.data = [extension];
                }
            }
        });
    });

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('isInstalled should be false when extension is not in store', () => {
        expect(wrapper.vm.isInstalled).toBe(false);
    });

    it('isInstalled should be true when extension is in store', () => {
        Shopware.State.commit('shopwareExtensions/setExtension', {
            name: 'Test',
            installedAt: 'some date'
        });

        expect(wrapper.vm.isInstalled).toBe(true);
    });

    it('previewMedia with no image', () => {
        expect(wrapper.vm.previewMedia).toStrictEqual({
            'background-image': 'url(\'nulladministration/static/img/theme/default_theme_preview.jpg\')'
        });
    });

    it('previewMedia gives image when set', () => {
        wrapper.setProps({
            extension: {
                label: 'Test',
                name: 'Test',
                variants: [
                    {
                        id: 79102,
                        type: 'free',
                        netPrice: 0,
                        trialPhaseIncluded: true
                    }
                ],
                images: [
                    {
                        remoteLink: 'a'
                    }
                ]
            }
        });

        expect(wrapper.vm.previewMedia).toStrictEqual({
            'background-image': 'url(\'a\')',
            'background-size': 'cover'
        });
    });

    it('calculatedPrice should be null when no variant given', () => {
        wrapper.setProps({
            extension: {
                label: 'Test',
                name: 'Test',
                variants: []
            }
        });

        expect(wrapper.vm.calculatedPrice).toBe(null);
    });

    it('isLicense should be undefined when not found', () => {
        wrapper.setProps({
            extension: {
                label: 'Test',
                name: 'Test2',
                variants: []
            }
        });

        expect(wrapper.vm.isLicensed).toBe(false);
    });

    it('openDetailPage calls router', () => {
        wrapper.vm.$router = {
            push: jest.fn()
        };

        wrapper.vm.openDetailPage();

        expect(wrapper.vm.$router.push).toBeCalled();
    });
});
