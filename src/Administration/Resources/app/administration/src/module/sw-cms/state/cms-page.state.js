import Vue from 'vue';

Shopware.State.registerModule('cmsPageState', {
    namespaced: true,

    state: {
        currentPage: null,
        currentMappingEntity: null,
        currentMappingTypes: {},
        currentDemoEntity: null,
        pageEntityName: 'cms_page',
        defaultMediaFolderId: null,
        currentCmsDeviceView: 'desktop',
        selectedSection: null,
        selectedBlock: null,
        isSystemDefaultLanguage: true,
        fieldOptions: {
            alignment: {
                'flex-start': {
                    label: 'sw-cms.elements.general.config.label.verticalAlignTop',
                    vertical: true
                },
                center: {
                    label: 'sw-cms.elements.general.config.label.verticalAlignCenter',
                    vertical: true
                },
                'flex-end': {
                    label: 'sw-cms.elements.general.config.label.verticalAlignBottom',
                    vertical: true
                }
            },
            formType: {
                contact: {
                    label: 'sw-cms.elements.form.config.label.typeContact'
                },
                newsletter: {
                    label: 'sw-cms.elements.form.config.label.typeNewsletter'
                }
            },
            mediaBackgroundMode: {
                auto: {
                    label: 'sw-cms.detail.label.backgroundMediaModeAuto'
                },
                contain: {
                    label: 'sw-cms.detail.label.backgroundMediaModeContain'
                },
                cover: {
                    label: 'sw-cms.detail.label.backgroundMediaModeCover'
                }
            },
            mediaDisplayMode: {
                standard: {
                    label: 'sw-cms.elements.general.config.label.displayModeStandard',
                    image: true,
                    video: true
                },
                cover: {
                    label: 'sw-cms.elements.general.config.label.displayModeCover',
                    image: true
                },
                contain: {
                    label: 'sw-cms.elements.general.config.label.displayModeContain',
                    image: true
                },
                stretched: {
                    label: 'sw-cms.elements.general.config.label.displayModeStretch',
                    video: true
                }
            },
            mediaGalleryNavigationPreviewPosition: {
                left: {
                    label: 'sw-cms.elements.imageGallery.config.label.navigationPreviewPositionLeft'
                },
                underneath: {
                    label: 'sw-cms.elements.imageGallery.config.label.navigationPreviewPositionUnderneath'
                }
            },
            mediaSliderNavigationPosition: {
                inside: {
                    label: 'sw-cms.elements.imageSlider.config.label.navigationPositionInside'
                },
                outside: {
                    label: 'sw-cms.elements.imageSlider.config.label.navigationPositionOutside'
                }
            },
            pageType: {
                page: {
                    label: 'sw-cms.detail.label.pageTypeShopPage'
                },
                landingpage: {
                    label: 'sw-cms.detail.label.pageTypeLandingpage'
                },
                product_list: {
                    label: 'sw-cms.detail.label.pageTypeCategory'
                }
                /*
                will be implemented in the future
                product_detail: {
                    label: 'sw-cms.detail.label.pageTypeProduct'
                }
                */
            },
            productBoxLayoutType: {
                standard: {
                    label: 'sw-cms.elements.productBox.config.label.layoutTypeStandard'
                },
                image: {
                    label: 'sw-cms.elements.productBox.config.label.layoutTypeImage'
                },
                minimal: {
                    label: 'sw-cms.elements.productBox.config.label.layoutTypeMinimal'
                }
            },
            sectionMobileBehaviour: {
                hidden: {
                    label: 'sw-cms.detail.sidebar.mobileOptionHidden'
                },
                wrap: {
                    label: 'sw-cms.detail.sidebar.mobileOptionWrap'
                }
            },
            sectionSizingMode: {
                boxed: {
                    label: 'sw-cms.detail.label.sizingOptionBoxed'
                },
                full_width: {
                    label: 'sw-cms.detail.label.sizingOptionFull'
                }
            }
        }
    },

    mutations: {
        setCurrentPage(state, page) {
            state.currentPage = page;
        },

        removeCurrentPage(state) {
            state.currentPage = null;
        },

        setCurrentMappingEntity(state, entity) {
            state.currentMappingEntity = entity;
        },

        removeCurrentMappingEntity(state) {
            state.currentMappingEntity = null;
        },

        setCurrentMappingTypes(state, types) {
            state.currentMappingTypes = types;
        },

        removeCurrentMappingTypes(state) {
            state.currentMappingTypes = {};
        },

        setCurrentDemoEntity(state, entity) {
            state.currentDemoEntity = entity;
        },

        removeCurrentDemoEntity(state) {
            state.currentDemoEntity = null;
        },

        setPageEntityName(state, entity) {
            state.pageEntityName = entity;
        },

        removePageEntityName(state) {
            state.pageEntityName = 'cms_page';
        },

        setDefaultMediaFolderId(state, folderId) {
            state.defaultMediaFolderId = folderId;
        },

        removeDefaultMediaFolderId(state) {
            state.defaultMediaFolderId = null;
        },

        setCurrentCmsDeviceView(state, view) {
            state.currentCmsDeviceView = view;
        },

        removeCurrentCmsDeviceView(state) {
            state.currentCmsDeviceView = 'desktop';
        },

        setSelectedSection(state, section) {
            state.selectedSection = section;
        },

        removeSelectedSection(state) {
            state.selectedSection = null;
        },

        setSelectedBlock(state, block) {
            state.selectedBlock = block;
        },

        removeSelectedBlock(state) {
            state.selectedBlock = null;
        },

        setIsSystemDefaultLanguage(state, isSystemDefaultLanguage) {
            state.isSystemDefaultLanguage = isSystemDefaultLanguage;
        },

        setAlignment(state, configuration) {
            if (!('name' in configuration)) {
                return;
            }

            configuration = { ...configuration };
            const name = configuration.name;
            delete configuration.name;

            Vue.set(state.fieldOptions.alignment, name, {
                ...(state.fieldOptions.alignment[name] || {}),
                ...configuration
            });
        },

        setFormType(state, configuration) {
            if (!('name' in configuration)) {
                return;
            }

            configuration = { ...configuration };
            const name = configuration.name;
            delete configuration.name;

            Vue.set(state.fieldOptions.formType, name, {
                ...(state.fieldOptions.formType[name] || {}),
                ...configuration
            });
        },

        setMediaBackgroundMode(state, configuration) {
            if (!('name' in configuration)) {
                return;
            }

            configuration = { ...configuration };
            const name = configuration.name;
            delete configuration.name;

            Vue.set(state.fieldOptions.mediaBackgroundMode, name, {
                ...(state.fieldOptions.mediaBackgroundMode[name] || {}),
                ...configuration
            });
        },

        setMediaDisplayMode(state, configuration) {
            if (!('name' in configuration)) {
                return;
            }

            configuration = { ...configuration };
            const name = configuration.name;
            delete configuration.name;

            Vue.set(state.fieldOptions.mediaDisplayMode, name, {
                ...(state.fieldOptions.mediaDisplayMode[name] || {}),
                ...configuration
            });
        },

        setMediaGalleryNavigationPreviewPosition(state, configuration) {
            if (!('name' in configuration)) {
                return;
            }

            configuration = { ...configuration };
            const name = configuration.name;
            delete configuration.name;

            Vue.set(state.fieldOptions.mediaGalleryNavigationPreviewPosition, name, {
                ...(state.fieldOptions.mediaGalleryNavigationPreviewPosition[name] || {}),
                ...configuration
            });
        },

        setMediaSliderNavigationPosition(state, configuration) {
            if (!('name' in configuration)) {
                return;
            }

            configuration = { ...configuration };
            const name = configuration.name;
            delete configuration.name;

            Vue.set(state.fieldOptions.mediaSliderNavigationPosition, name, {
                ...(state.fieldOptions.mediaSliderNavigationPosition[name] || {}),
                ...configuration
            });
        },

        setPageType(state, configuration) {
            if (!('name' in configuration)) {
                return;
            }

            configuration = { ...configuration };
            const name = configuration.name;
            delete configuration.name;

            Vue.set(state.fieldOptions.pageType, name, {
                ...(state.fieldOptions.pageType[name] || {}),
                ...configuration
            });
        },

        setProductBoxLayoutType(state, configuration) {
            if (!('name' in configuration)) {
                return;
            }

            configuration = { ...configuration };
            const name = configuration.name;
            delete configuration.name;

            Vue.set(state.fieldOptions.productBoxLayoutType, name, {
                ...(state.fieldOptions.productBoxLayoutType[name] || {}),
                ...configuration
            });
        },

        setSectionMobileBehaviour(state, configuration) {
            if (!('name' in configuration)) {
                return;
            }

            configuration = { ...configuration };
            const name = configuration.name;
            delete configuration.name;

            Vue.set(state.fieldOptions.sectionMobileBehaviour, name, {
                ...(state.fieldOptions.sectionMobileBehaviour[name] || {}),
                ...configuration
            });
        },

        setSectionSizingMode(state, configuration) {
            if (!('name' in configuration)) {
                return;
            }

            configuration = { ...configuration };
            const name = configuration.name;
            delete configuration.name;

            Vue.set(state.fieldOptions.sectionSizingMode, name, {
                ...(state.fieldOptions.sectionSizingMode[name] || {}),
                ...configuration
            });
        }
    },

    actions: {
        resetCmsPageState({ commit }) {
            commit('removeCurrentPage');
            commit('removeCurrentMappingEntity');
            commit('removeCurrentMappingTypes');
            commit('removeCurrentDemoEntity');
        },

        setSection({ commit }, section) {
            commit('removeSelectedBlock');
            commit('setSelectedSection', section);
        },

        setBlock({ commit }, block) {
            commit('removeSelectedSection');
            commit('setSelectedBlock', block);
        }
    },

    getters: {
        verticalAlignments(state) {
            return Object.fromEntries(
                Object.entries(state.fieldOptions.alignment)
                    .filter(config => config[1] && config[1].vertical)
            );
        },

        horizontalAlignments(state) {
            return Object.fromEntries(
                Object.entries(state.fieldOptions.alignment)
                    .filter(config => config[1] && config[1].horizontal)
            );
        },

        imageDisplayModes(state) {
            return Object.fromEntries(
                Object.entries(state.fieldOptions.mediaDisplayMode)
                    .filter(config => config[1] && config[1].image)
            );
        },

        videoDisplayModes(state) {
            return Object.fromEntries(
                Object.entries(state.fieldOptions.mediaDisplayMode)
                    .filter(config => config[1] && config[1].video)
            );
        }
    }
});
