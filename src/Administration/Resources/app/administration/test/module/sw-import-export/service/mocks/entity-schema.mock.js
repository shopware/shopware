/* istanbul ignore file */

export default {
    media: {
        entity: 'media',
        properties: {
            id: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            userId: {
                type: 'uuid',
                flags: {
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                }
            },
            mediaFolderId: {
                type: 'uuid',
                flags: {
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                }
            },
            mimeType: {
                type: 'string',
                flags: {
                    write_protected: [
                        [
                            'system'
                        ]
                    ]
                }
            },
            fileExtension: {
                type: 'string',
                flags: {
                    write_protected: [
                        [
                            'system'
                        ]
                    ]
                }
            },
            uploadedAt: {
                type: 'date',
                flags: {
                    write_protected: [
                        [
                            'system'
                        ]
                    ]
                }
            },
            fileName: {
                type: 'text',
                flags: {
                    write_protected: [
                        [
                            'system'
                        ]
                    ],
                    search_ranking: 500
                }
            },
            fileSize: {
                type: 'int',
                flags: {
                    write_protected: [
                        [
                            'system'
                        ]
                    ]
                }
            },
            mediaTypeRaw: {
                type: 'blob',
                flags: {
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\AdminApiSource',
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ],
                    write_protected: [
                        [
                            'system'
                        ]
                    ]
                }
            },
            metaData: {
                type: 'json_object',
                properties: [],
                flags: {
                    write_protected: [
                        [
                            'system'
                        ]
                    ]
                }
            },
            mediaType: {
                type: 'json_object',
                properties: [],
                flags: {
                    write_protected: [
                        []
                    ],
                    runtime: true,
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                }
            },
            alt: {
                type: 'text',
                flags: {
                    search_ranking: 250,
                    translatable: true
                }
            },
            title: {
                type: 'string',
                flags: {
                    search_ranking: 500,
                    translatable: true
                }
            },
            url: {
                type: 'string',
                flags: {
                    runtime: true
                }
            },
            hasFile: {
                type: 'boolean',
                flags: {
                    runtime: true
                }
            },
            private: {
                type: 'boolean',
                flags: []
            },
            customFields: {
                type: 'json_object',
                properties: [],
                flags: {
                    translatable: true
                }
            },
            thumbnailsRo: {
                type: 'blob',
                flags: {
                    computed: true,
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\AdminApiSource',
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                }
            },
            translations: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'media_translation',
                flags: {
                    cascade_delete: true,
                    required: true
                },
                localField: 'id',
                referenceField: 'mediaId'
            },
            tags: {
                type: 'association',
                relation: 'many_to_many',
                entity: 'tag',
                flags: []
            },
            thumbnails: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'media_thumbnail',
                flags: {
                    cascade_delete: true
                },
                localField: 'id',
                referenceField: 'mediaId'
            },
            user: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'user',
                flags: {
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                },
                localField: 'userId',
                referenceField: 'id'
            },
            categories: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'category',
                flags: {
                    set_null_on_delete: true,
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                },
                localField: 'id',
                referenceField: 'mediaId'
            },
            productManufacturers: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'product_manufacturer',
                flags: {
                    set_null_on_delete: true,
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                },
                localField: 'id',
                referenceField: 'mediaId'
            },
            productMedia: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'product_media',
                flags: {
                    cascade_delete: true,
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                },
                localField: 'id',
                referenceField: 'mediaId'
            },
            avatarUser: {
                type: 'association',
                relation: 'one_to_one',
                entity: 'user',
                flags: {
                    cascade_delete: true,
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                },
                localField: 'id',
                referenceField: 'avatarId'
            },
            mediaFolder: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'media_folder',
                flags: {
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                },
                localField: 'mediaFolderId',
                referenceField: 'id'
            },
            propertyGroupOptions: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'property_group_option',
                flags: {
                    set_null_on_delete: true,
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                },
                localField: 'id',
                referenceField: 'mediaId'
            },
            mailTemplateMedia: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'mail_template_media',
                flags: {
                    cascade_delete: true,
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                },
                localField: 'id',
                referenceField: 'mediaId'
            },
            documentBaseConfigs: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'document_base_config',
                flags: {
                    cascade_delete: true,
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                },
                localField: 'id',
                referenceField: 'logoId'
            },
            shippingMethods: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'shipping_method',
                flags: {
                    set_null_on_delete: true,
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                },
                localField: 'id',
                referenceField: 'mediaId'
            },
            paymentMethods: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'payment_method',
                flags: {
                    set_null_on_delete: true,
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                },
                localField: 'id',
                referenceField: 'mediaId'
            },
            productConfiguratorSettings: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'product_configurator_setting',
                flags: {
                    set_null_on_delete: true,
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                },
                localField: 'id',
                referenceField: 'mediaId'
            },
            orderLineItems: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'order_line_item',
                flags: {
                    restrict_delete: true,
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                },
                localField: 'id',
                referenceField: 'coverId'
            },
            cmsBlocks: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'cms_block',
                flags: {
                    restrict_delete: true,
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                },
                localField: 'id',
                referenceField: 'backgroundMediaId'
            },
            cmsSections: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'cms_section',
                flags: {
                    restrict_delete: true,
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                },
                localField: 'id',
                referenceField: 'backgroundMediaId'
            },
            cmsPages: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'cms_page',
                flags: {
                    restrict_delete: true,
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                },
                localField: 'id',
                referenceField: 'previewMediaId'
            },
            documents: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'document',
                flags: {
                    restrict_delete: true,
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                },
                localField: 'id',
                referenceField: 'documentMediaFileId'
            },
            createdAt: {
                type: 'date',
                flags: {
                    required: true
                }
            },
            updatedAt: {
                type: 'date',
                flags: []
            },
            themes: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'theme',
                flags: {
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ],
                    extension: true
                },
                localField: 'id',
                referenceField: 'previewMediaId'
            },
            themeMedia: {
                type: 'association',
                relation: 'many_to_many',
                entity: 'theme',
                flags: {
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ],
                    extension: true
                }
            },
            translated: {
                type: 'json_object',
                properties: [],
                flags: {
                    computed: true,
                    runtime: true
                }
            }
        }
    },
    media_default_folder: {
        entity: 'media_default_folder',
        properties: {
            id: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            associationFields: {
                type: 'json_list',
                flags: {
                    required: true
                }
            },
            entity: {
                type: 'string',
                flags: {
                    required: true
                }
            },
            folder: {
                type: 'association',
                relation: 'one_to_one',
                entity: 'media_folder',
                flags: [],
                localField: 'id',
                referenceField: 'defaultFolderId'
            },
            customFields: {
                type: 'json_object',
                properties: [],
                flags: []
            },
            createdAt: {
                type: 'date',
                flags: {
                    required: true
                }
            },
            updatedAt: {
                type: 'date',
                flags: []
            }
        }
    },
    media_folder: {
        entity: 'media_folder',
        properties: {
            id: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            useParentConfiguration: {
                type: 'boolean',
                flags: []
            },
            configurationId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            defaultFolderId: {
                type: 'uuid',
                flags: []
            },
            parentId: {
                type: 'uuid',
                flags: []
            },
            parent: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'media_folder',
                flags: [],
                localField: 'parentId',
                referenceField: 'id'
            },
            children: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'media_folder',
                flags: {
                    cascade_delete: true
                },
                localField: 'id',
                referenceField: 'parentId'
            },
            childCount: {
                type: 'int',
                flags: {
                    write_protected: [
                        []
                    ]
                }
            },
            media: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'media',
                flags: {
                    set_null_on_delete: true
                },
                localField: 'id',
                referenceField: 'mediaFolderId'
            },
            defaultFolder: {
                type: 'association',
                relation: 'one_to_one',
                entity: 'media_default_folder',
                flags: [],
                localField: 'defaultFolderId',
                referenceField: 'id'
            },
            configuration: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'media_folder_configuration',
                flags: [],
                localField: 'configurationId',
                referenceField: 'id'
            },
            name: {
                type: 'string',
                flags: {
                    search_ranking: 500,
                    required: true
                }
            },
            customFields: {
                type: 'json_object',
                properties: [],
                flags: []
            },
            createdAt: {
                type: 'date',
                flags: {
                    required: true
                }
            },
            updatedAt: {
                type: 'date',
                flags: []
            }
        }
    },
    media_folder_configuration: {
        entity: 'media_folder_configuration',
        properties: {
            id: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            createThumbnails: {
                type: 'boolean',
                flags: []
            },
            keepAspectRatio: {
                type: 'boolean',
                flags: []
            },
            thumbnailQuality: {
                type: 'int',
                flags: []
            },
            private: {
                type: 'boolean',
                flags: []
            },
            noAssociation: {
                type: 'boolean',
                flags: []
            },
            mediaFolders: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'media_folder',
                flags: [],
                localField: 'id',
                referenceField: 'configurationId'
            },
            mediaThumbnailSizes: {
                type: 'association',
                relation: 'many_to_many',
                entity: 'media_thumbnail_size',
                flags: []
            },
            mediaThumbnailSizesRo: {
                type: 'blob',
                flags: {
                    computed: true,
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource',
                            'Shopware\\Core\\Framework\\Api\\Context\\AdminApiSource'
                        ]
                    ]
                }
            },
            customFields: {
                type: 'json_object',
                properties: [],
                flags: []
            },
            createdAt: {
                type: 'date',
                flags: {
                    required: true
                }
            },
            updatedAt: {
                type: 'date',
                flags: []
            }
        }
    },
    media_folder_configuration_media_thumbnail_size: {
        entity: 'media_folder_configuration_media_thumbnail_size',
        properties: {
            mediaFolderConfigurationId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            mediaThumbnailSizeId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            mediaFolderConfiguration: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'media_folder_configuration',
                flags: [],
                localField: 'mediaFolderConfigurationId',
                referenceField: 'id'
            },
            mediaThumbnailSize: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'media_thumbnail_size',
                flags: [],
                localField: 'mediaThumbnailSizeId',
                referenceField: 'id'
            }
        }
    },
    media_tag: {
        entity: 'media_tag',
        properties: {
            mediaId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            tagId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            media: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'media',
                flags: [],
                localField: 'mediaId',
                referenceField: 'id'
            },
            tag: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'tag',
                flags: [],
                localField: 'tagId',
                referenceField: 'id'
            }
        }
    },
    media_thumbnail: {
        entity: 'media_thumbnail',
        properties: {
            id: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            mediaId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            width: {
                type: 'int',
                flags: {
                    required: true,
                    write_protected: [
                        [
                            'system'
                        ]
                    ]
                }
            },
            height: {
                type: 'int',
                flags: {
                    required: true,
                    write_protected: [
                        [
                            'system'
                        ]
                    ]
                }
            },
            url: {
                type: 'string',
                flags: {
                    runtime: true
                }
            },
            media: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'media',
                flags: [],
                localField: 'mediaId',
                referenceField: 'id'
            },
            customFields: {
                type: 'json_object',
                properties: [],
                flags: []
            },
            createdAt: {
                type: 'date',
                flags: {
                    required: true
                }
            },
            updatedAt: {
                type: 'date',
                flags: []
            }
        }
    },
    media_thumbnail_size: {
        entity: 'media_thumbnail_size',
        properties: {
            id: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            width: {
                type: 'int',
                flags: {
                    required: true
                }
            },
            height: {
                type: 'int',
                flags: {
                    required: true
                }
            },
            mediaFolderConfigurations: {
                type: 'association',
                relation: 'many_to_many',
                entity: 'media_folder_configuration',
                flags: []
            },
            customFields: {
                type: 'json_object',
                properties: [],
                flags: []
            },
            createdAt: {
                type: 'date',
                flags: {
                    required: true
                }
            },
            updatedAt: {
                type: 'date',
                flags: []
            }
        }
    },
    media_translation: {
        entity: 'media_translation',
        properties: {
            title: {
                type: 'string',
                flags: []
            },
            alt: {
                type: 'text',
                flags: []
            },
            customFields: {
                type: 'json_object',
                properties: [],
                flags: []
            },
            createdAt: {
                type: 'date',
                flags: {
                    required: true
                }
            },
            updatedAt: {
                type: 'date',
                flags: []
            },
            mediaId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            languageId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            media: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'media',
                flags: [],
                localField: 'mediaId',
                referenceField: 'id'
            },
            language: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'language',
                flags: [],
                localField: 'languageId',
                referenceField: 'id'
            }
        }
    },
    product: {
        entity: 'product',
        properties: {
            id: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            versionId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            parentId: {
                type: 'uuid',
                flags: []
            },
            parentVersionId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            childCount: {
                type: 'int',
                flags: {
                    write_protected: [
                        []
                    ]
                }
            },
            blacklistIds: {
                type: 'json_object',
                properties: [],
                flags: {
                    inherited: true,
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                }
            },
            whitelistIds: {
                type: 'json_object',
                properties: [],
                flags: {
                    inherited: true,
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                }
            },
            autoIncrement: {
                type: 'int',
                flags: {
                    write_protected: [
                        []
                    ]
                }
            },
            active: {
                type: 'boolean',
                flags: []
            },
            stock: {
                type: 'int',
                flags: {
                    required: true
                }
            },
            availableStock: {
                type: 'int',
                flags: {
                    write_protected: [
                        []
                    ]
                }
            },
            available: {
                type: 'boolean',
                flags: {
                    write_protected: [
                        []
                    ]
                }
            },
            variantRestrictions: {
                type: 'json_object',
                properties: [],
                flags: {
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                }
            },
            displayGroup: {
                type: 'string',
                flags: {
                    write_protected: [
                        []
                    ]
                }
            },
            configuratorGroupConfig: {
                type: 'json_object',
                properties: [],
                flags: {
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ],
                    inherited: true
                }
            },
            manufacturerId: {
                type: 'uuid',
                flags: {
                    inherited: true
                }
            },
            productManufacturerVersionId: {
                type: 'uuid',
                flags: {
                    inherited: true,
                    required: true
                }
            },
            unitId: {
                type: 'uuid',
                flags: {
                    inherited: true
                }
            },
            taxId: {
                type: 'uuid',
                flags: {
                    inherited: true,
                    required: true
                }
            },
            coverId: {
                type: 'uuid',
                flags: {
                    inherited: true
                }
            },
            productMediaVersionId: {
                type: 'uuid',
                flags: {
                    inherited: true
                }
            },
            price: {
                type: 'json_object',
                properties: [],
                flags: {
                    inherited: true,
                    required: true
                }
            },
            manufacturerNumber: {
                type: 'string',
                flags: {
                    inherited: true
                }
            },
            ean: {
                type: 'string',
                flags: {
                    inherited: true,
                    search_ranking: 250
                }
            },
            productNumber: {
                type: 'string',
                flags: {
                    search_ranking: 500,
                    required: true
                }
            },
            isCloseout: {
                type: 'boolean',
                flags: {
                    inherited: true
                }
            },
            purchaseSteps: {
                type: 'int',
                flags: {
                    inherited: true
                }
            },
            maxPurchase: {
                type: 'int',
                flags: {
                    inherited: true
                }
            },
            minPurchase: {
                type: 'int',
                flags: {
                    inherited: true
                }
            },
            purchaseUnit: {
                type: 'float',
                flags: {
                    inherited: true
                }
            },
            referenceUnit: {
                type: 'float',
                flags: {
                    inherited: true
                }
            },
            shippingFree: {
                type: 'boolean',
                flags: {
                    inherited: true
                }
            },
            purchasePrice: {
                type: 'float',
                flags: {
                    inherited: true
                }
            },
            markAsTopseller: {
                type: 'boolean',
                flags: {
                    inherited: true
                }
            },
            weight: {
                type: 'float',
                flags: {
                    inherited: true
                }
            },
            width: {
                type: 'float',
                flags: {
                    inherited: true
                }
            },
            height: {
                type: 'float',
                flags: {
                    inherited: true
                }
            },
            length: {
                type: 'float',
                flags: {
                    inherited: true
                }
            },
            releaseDate: {
                type: 'date',
                flags: {
                    inherited: true
                }
            },
            categoryTree: {
                type: 'json_list',
                flags: {
                    inherited: true,
                    write_protected: [
                        []
                    ]
                }
            },
            propertyIds: {
                type: 'json_list',
                flags: {
                    write_protected: [
                        []
                    ],
                    inherited: true
                }
            },
            optionIds: {
                type: 'json_list',
                flags: {
                    write_protected: [
                        []
                    ],
                    inherited: true
                }
            },
            tagIds: {
                type: 'json_list',
                flags: {
                    write_protected: [
                        []
                    ],
                    inherited: true
                }
            },
            listingPrices: {
                type: 'json_object',
                properties: [],
                flags: {
                    write_protected: [
                        []
                    ],
                    inherited: true
                }
            },
            categoriesRo: {
                type: 'association',
                relation: 'many_to_many',
                entity: 'category',
                flags: {
                    cascade_delete: true,
                    write_protected: [
                        []
                    ]
                }
            },
            ratingAverage: {
                type: 'float',
                flags: {
                    write_protected: [
                        []
                    ],
                    inherited: true
                }
            },
            deliveryTimeId: {
                type: 'uuid',
                flags: {
                    inherited: true
                }
            },
            deliveryTime: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'delivery_time',
                flags: {
                    inherited: true
                },
                localField: 'deliveryTimeId',
                referenceField: 'id'
            },
            restockTime: {
                type: 'int',
                flags: {
                    inherited: true
                }
            },
            metaDescription: {
                type: 'string',
                flags: {
                    inherited: true,
                    translatable: true
                }
            },
            name: {
                type: 'string',
                flags: {
                    required: true,
                    inherited: true,
                    search_ranking: 500,
                    translatable: true
                }
            },
            keywords: {
                type: 'text',
                flags: {
                    inherited: true,
                    translatable: true
                }
            },
            description: {
                type: 'text',
                flags: {
                    allow_html: true,
                    inherited: true,
                    translatable: true
                }
            },
            metaTitle: {
                type: 'string',
                flags: {
                    inherited: true,
                    translatable: true
                }
            },
            packUnit: {
                type: 'string',
                flags: {
                    inherited: true,
                    translatable: true
                }
            },
            packUnitPlural: {
                type: 'string',
                flags: {
                    inherited: true,
                    translatable: true
                }
            },
            customFields: {
                type: 'json_object',
                properties: [],
                flags: {
                    translatable: true
                }
            },
            parent: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'product',
                flags: [],
                localField: 'parentId',
                referenceField: 'id'
            },
            children: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'product',
                flags: {
                    cascade_delete: true
                },
                localField: 'id',
                referenceField: 'parentId'
            },
            tax: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'tax',
                flags: {
                    inherited: true
                },
                localField: 'taxId',
                referenceField: 'id'
            },
            manufacturer: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'product_manufacturer',
                flags: {
                    inherited: true
                },
                localField: 'manufacturerId',
                referenceField: 'id'
            },
            unit: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'unit',
                flags: {
                    inherited: true
                },
                localField: 'unitId',
                referenceField: 'id'
            },
            cover: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'product_media',
                flags: {
                    inherited: true
                },
                localField: 'coverId',
                referenceField: 'id'
            },
            prices: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'product_price',
                flags: {
                    cascade_delete: true,
                    inherited: true
                },
                localField: 'id',
                referenceField: 'productId'
            },
            media: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'product_media',
                flags: {
                    cascade_delete: true,
                    inherited: true
                },
                localField: 'id',
                referenceField: 'productId'
            },
            crossSellings: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'product_cross_selling',
                flags: {
                    cascade_delete: true,
                    inherited: true
                },
                localField: 'id',
                referenceField: 'productId'
            },
            crossSellingAssignedProducts: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'product_cross_selling_assigned_products',
                flags: [],
                localField: 'id',
                referenceField: 'productId'
            },
            properties: {
                type: 'association',
                relation: 'many_to_many',
                entity: 'property_group_option',
                flags: {
                    cascade_delete: true,
                    inherited: true
                }
            },
            categories: {
                type: 'association',
                relation: 'many_to_many',
                entity: 'category',
                flags: {
                    cascade_delete: true,
                    inherited: true
                }
            },
            tags: {
                type: 'association',
                relation: 'many_to_many',
                entity: 'tag',
                flags: {
                    inherited: true
                }
            },
            translations: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'product_translation',
                flags: {
                    cascade_delete: true,
                    inherited: true,
                    required: true
                },
                localField: 'id',
                referenceField: 'productId'
            },
            configuratorSettings: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'product_configurator_setting',
                flags: {
                    cascade_delete: true,
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                },
                localField: 'id',
                referenceField: 'productId'
            },
            options: {
                type: 'association',
                relation: 'many_to_many',
                entity: 'property_group_option',
                flags: {
                    cascade_delete: true
                }
            },
            visibilities: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'product_visibility',
                flags: {
                    cascade_delete: true,
                    inherited: true,
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                },
                localField: 'id',
                referenceField: 'productId'
            },
            searchKeywords: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'product_search_keyword',
                flags: {
                    cascade_delete: true,
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                },
                localField: 'id',
                referenceField: 'productId'
            },
            productReviews: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'product_review',
                flags: {
                    cascade_delete: true
                },
                localField: 'id',
                referenceField: 'productId'
            },
            mainCategories: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'main_category',
                flags: {
                    cascade_delete: true
                },
                localField: 'id',
                referenceField: 'productId'
            },
            seoUrls: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'seo_url',
                flags: [],
                localField: 'id',
                referenceField: 'foreignKey'
            },
            orderLineItems: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'order_line_item',
                flags: {
                    set_null_on_delete: true,
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                },
                localField: 'id',
                referenceField: 'productId'
            },
            createdAt: {
                type: 'date',
                flags: {
                    required: true
                }
            },
            updatedAt: {
                type: 'date',
                flags: []
            },
            translated: {
                type: 'json_object',
                properties: [],
                flags: {
                    computed: true,
                    runtime: true
                }
            }
        }
    },
    product_category: {
        entity: 'product_category',
        properties: {
            productId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            productVersionId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            categoryId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            categoryVersionId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            product: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'product',
                flags: [],
                localField: 'productId',
                referenceField: 'id'
            },
            category: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'category',
                flags: [],
                localField: 'categoryId',
                referenceField: 'id'
            }
        }
    },
    product_category_tree: {
        entity: 'product_category_tree',
        properties: {
            productId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            productVersionId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            categoryId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            categoryVersionId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            product: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'product',
                flags: [],
                localField: 'productId',
                referenceField: 'id'
            },
            category: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'category',
                flags: [],
                localField: 'categoryId',
                referenceField: 'id'
            }
        }
    },
    product_configurator_setting: {
        entity: 'product_configurator_setting',
        properties: {
            id: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            versionId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            productId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            productVersionId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            mediaId: {
                type: 'uuid',
                flags: []
            },
            optionId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            price: {
                type: 'json_object',
                properties: [],
                flags: []
            },
            position: {
                type: 'int',
                flags: []
            },
            product: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'product',
                flags: [],
                localField: 'productId',
                referenceField: 'id'
            },
            media: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'media',
                flags: [],
                localField: 'mediaId',
                referenceField: 'id'
            },
            option: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'property_group_option',
                flags: [],
                localField: 'optionId',
                referenceField: 'id'
            },
            customFields: {
                type: 'json_object',
                properties: [],
                flags: []
            },
            createdAt: {
                type: 'date',
                flags: {
                    required: true
                }
            },
            updatedAt: {
                type: 'date',
                flags: []
            }
        }
    },
    product_cross_selling: {
        entity: 'product_cross_selling',
        properties: {
            id: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            name: {
                type: 'string',
                flags: {
                    required: true,
                    translatable: true
                }
            },
            position: {
                type: 'int',
                flags: {
                    required: true
                }
            },
            sortBy: {
                type: 'string',
                flags: []
            },
            sortDirection: {
                type: 'string',
                flags: []
            },
            type: {
                type: 'string',
                flags: {
                    required: true
                }
            },
            active: {
                type: 'boolean',
                flags: []
            },
            limit: {
                type: 'int',
                flags: []
            },
            productId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            productVersionId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            product: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'product',
                flags: {
                    reversed_inherited: 'crossSellings'
                },
                localField: 'productId',
                referenceField: 'id'
            },
            productStreamId: {
                type: 'uuid',
                flags: []
            },
            productStream: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'product_stream',
                flags: {
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                },
                localField: 'productStreamId',
                referenceField: 'id'
            },
            assignedProducts: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'product_cross_selling_assigned_products',
                flags: {
                    cascade_delete: true
                },
                localField: 'id',
                referenceField: 'crossSellingId'
            },
            translations: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'product_cross_selling_translation',
                flags: {
                    cascade_delete: true
                },
                localField: 'id',
                referenceField: 'productCrossSellingId'
            },
            createdAt: {
                type: 'date',
                flags: {
                    required: true
                }
            },
            updatedAt: {
                type: 'date',
                flags: []
            },
            translated: {
                type: 'json_object',
                properties: [],
                flags: {
                    computed: true,
                    runtime: true
                }
            }
        }
    },
    product_cross_selling_assigned_products: {
        entity: 'product_cross_selling_assigned_products',
        properties: {
            id: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            crossSellingId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            productId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            productVersionId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            product: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'product',
                flags: [],
                localField: 'productId',
                referenceField: 'id'
            },
            crossSelling: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'product_cross_selling',
                flags: [],
                localField: 'crossSellingId',
                referenceField: 'id'
            },
            position: {
                type: 'int',
                flags: []
            },
            createdAt: {
                type: 'date',
                flags: {
                    required: true
                }
            },
            updatedAt: {
                type: 'date',
                flags: []
            }
        }
    },
    product_cross_selling_translation: {
        entity: 'product_cross_selling_translation',
        properties: {
            name: {
                type: 'string',
                flags: {
                    required: true
                }
            },
            createdAt: {
                type: 'date',
                flags: {
                    required: true
                }
            },
            updatedAt: {
                type: 'date',
                flags: []
            },
            productCrossSellingId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            languageId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            productCrossSelling: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'product_cross_selling',
                flags: [],
                localField: 'productCrossSellingId',
                referenceField: 'id'
            },
            language: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'language',
                flags: [],
                localField: 'languageId',
                referenceField: 'id'
            }
        }
    },
    product_export: {
        entity: 'product_export',
        properties: {
            id: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            productStreamId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            storefrontSalesChannelId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            salesChannelId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            salesChannelDomainId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            currencyId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            fileName: {
                type: 'string',
                flags: {
                    required: true
                }
            },
            accessKey: {
                type: 'string',
                flags: {
                    required: true
                }
            },
            encoding: {
                type: 'string',
                flags: {
                    required: true
                }
            },
            fileFormat: {
                type: 'string',
                flags: {
                    required: true
                }
            },
            includeVariants: {
                type: 'boolean',
                flags: []
            },
            generateByCronjob: {
                type: 'boolean',
                flags: {
                    required: true
                }
            },
            generatedAt: {
                type: 'date',
                flags: []
            },
            interval: {
                type: 'int',
                flags: {
                    required: true
                }
            },
            headerTemplate: {
                type: 'text',
                flags: {
                    allow_html: true
                }
            },
            bodyTemplate: {
                type: 'text',
                flags: {
                    allow_html: true
                }
            },
            footerTemplate: {
                type: 'text',
                flags: {
                    allow_html: true
                }
            },
            productStream: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'product_stream',
                flags: [],
                localField: 'productStreamId',
                referenceField: 'id'
            },
            storefrontSalesChannel: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'sales_channel',
                flags: [],
                localField: 'storefrontSalesChannelId',
                referenceField: 'id'
            },
            salesChannel: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'sales_channel',
                flags: [],
                localField: 'salesChannelId',
                referenceField: 'id'
            },
            salesChannelDomain: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'sales_channel_domain',
                flags: [],
                localField: 'salesChannelDomainId',
                referenceField: 'id'
            },
            currency: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'currency',
                flags: [],
                localField: 'currencyId',
                referenceField: 'id'
            },
            createdAt: {
                type: 'date',
                flags: {
                    required: true
                }
            },
            updatedAt: {
                type: 'date',
                flags: []
            }
        }
    },
    product_keyword_dictionary: {
        entity: 'product_keyword_dictionary',
        properties: {
            id: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            languageId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            keyword: {
                type: 'string',
                flags: {
                    required: true
                }
            },
            reversed: {
                type: 'string',
                flags: {
                    computed: true
                }
            },
            language: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'language',
                flags: [],
                localField: 'languageId',
                referenceField: 'id'
            }
        }
    },
    product_manufacturer: {
        entity: 'product_manufacturer',
        properties: {
            id: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            versionId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            mediaId: {
                type: 'uuid',
                flags: []
            },
            link: {
                type: 'string',
                flags: []
            },
            name: {
                type: 'string',
                flags: {
                    required: true,
                    search_ranking: 500,
                    translatable: true
                }
            },
            description: {
                type: 'text',
                flags: {
                    allow_html: true,
                    translatable: true
                }
            },
            customFields: {
                type: 'json_object',
                properties: [],
                flags: {
                    translatable: true
                }
            },
            media: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'media',
                flags: [],
                localField: 'mediaId',
                referenceField: 'id'
            },
            products: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'product',
                flags: {
                    restrict_delete: true,
                    reversed_inherited: 'manufacturer'
                },
                localField: 'id',
                referenceField: 'manufacturerId'
            },
            translations: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'product_manufacturer_translation',
                flags: {
                    cascade_delete: true,
                    required: true
                },
                localField: 'id',
                referenceField: 'productManufacturerId'
            },
            createdAt: {
                type: 'date',
                flags: {
                    required: true
                }
            },
            updatedAt: {
                type: 'date',
                flags: []
            },
            translated: {
                type: 'json_object',
                properties: [],
                flags: {
                    computed: true,
                    runtime: true
                }
            }
        }
    },
    product_manufacturer_translation: {
        entity: 'product_manufacturer_translation',
        properties: {
            name: {
                type: 'string',
                flags: {
                    required: true
                }
            },
            description: {
                type: 'text',
                flags: {
                    allow_html: true
                }
            },
            customFields: {
                type: 'json_object',
                properties: [],
                flags: []
            },
            createdAt: {
                type: 'date',
                flags: {
                    required: true
                }
            },
            updatedAt: {
                type: 'date',
                flags: []
            },
            productManufacturerId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            languageId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            productManufacturer: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'product_manufacturer',
                flags: [],
                localField: 'productManufacturerId',
                referenceField: 'id'
            },
            language: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'language',
                flags: [],
                localField: 'languageId',
                referenceField: 'id'
            },
            productManufacturerVersionId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            }
        }
    },
    product_media: {
        entity: 'product_media',
        properties: {
            id: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            versionId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            productId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            productVersionId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            mediaId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            position: {
                type: 'int',
                flags: []
            },
            product: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'product',
                flags: {
                    reversed_inherited: 'media'
                },
                localField: 'productId',
                referenceField: 'id'
            },
            media: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'media',
                flags: [],
                localField: 'mediaId',
                referenceField: 'id'
            },
            customFields: {
                type: 'json_object',
                properties: [],
                flags: []
            },
            createdAt: {
                type: 'date',
                flags: {
                    required: true
                }
            },
            updatedAt: {
                type: 'date',
                flags: []
            }
        }
    },
    product_option: {
        entity: 'product_option',
        properties: {
            productId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            productVersionId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            optionId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            product: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'product',
                flags: [],
                localField: 'productId',
                referenceField: 'id'
            },
            option: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'property_group_option',
                flags: [],
                localField: 'optionId',
                referenceField: 'id'
            }
        }
    },
    product_price: {
        entity: 'product_price',
        properties: {
            id: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            versionId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            productId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            productVersionId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            ruleId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            price: {
                type: 'json_object',
                properties: [],
                flags: {
                    required: true
                }
            },
            quantityStart: {
                type: 'int',
                flags: {
                    required: true
                }
            },
            quantityEnd: {
                type: 'int',
                flags: []
            },
            product: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'product',
                flags: {
                    reversed_inherited: 'prices'
                },
                localField: 'productId',
                referenceField: 'id'
            },
            rule: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'rule',
                flags: {
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                },
                localField: 'ruleId',
                referenceField: 'id'
            },
            customFields: {
                type: 'json_object',
                properties: [],
                flags: []
            },
            createdAt: {
                type: 'date',
                flags: {
                    required: true
                }
            },
            updatedAt: {
                type: 'date',
                flags: []
            }
        }
    },
    product_property: {
        entity: 'product_property',
        properties: {
            productId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            productVersionId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            optionId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            product: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'product',
                flags: [],
                localField: 'productId',
                referenceField: 'id'
            },
            option: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'property_group_option',
                flags: [],
                localField: 'optionId',
                referenceField: 'id'
            }
        }
    },
    product_review: {
        entity: 'product_review',
        properties: {
            id: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            productId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            productVersionId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            customerId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            salesChannelId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            languageId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            externalUser: {
                type: 'string',
                flags: {
                    search_ranking: 250,
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                }
            },
            externalEmail: {
                type: 'string',
                flags: {
                    search_ranking: 250,
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                }
            },
            title: {
                type: 'string',
                flags: {
                    required: true,
                    search_ranking: 80
                }
            },
            content: {
                type: 'text',
                flags: {
                    required: true,
                    search_ranking: 80,
                    allow_html: true
                }
            },
            points: {
                type: 'float',
                flags: []
            },
            status: {
                type: 'boolean',
                flags: []
            },
            comment: {
                type: 'text',
                flags: []
            },
            updatedAt: {
                type: 'date',
                flags: []
            },
            createdAt: {
                type: 'date',
                flags: {
                    required: true
                }
            },
            product: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'product',
                flags: {
                    search_ranking: 0.25
                },
                localField: 'productId',
                referenceField: 'id'
            },
            customer: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'customer',
                flags: {
                    search_ranking: 250,
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                },
                localField: 'customerId',
                referenceField: 'id'
            },
            salesChannel: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'sales_channel',
                flags: {
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                },
                localField: 'salesChannelId',
                referenceField: 'id'
            },
            language: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'language',
                flags: {
                    read_protected: [
                        [
                            'Shopware\\Core\\Framework\\Api\\Context\\SalesChannelApiSource'
                        ]
                    ]
                },
                localField: 'languageId',
                referenceField: 'id'
            }
        }
    },
    product_search_keyword: {
        entity: 'product_search_keyword',
        properties: {
            id: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            versionId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            languageId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            productId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            productVersionId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            keyword: {
                type: 'string',
                flags: {
                    required: true
                }
            },
            ranking: {
                type: 'float',
                flags: {
                    required: true
                }
            },
            product: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'product',
                flags: [],
                localField: 'productId',
                referenceField: 'id'
            },
            language: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'language',
                flags: [],
                localField: 'languageId',
                referenceField: 'id'
            },
            createdAt: {
                type: 'date',
                flags: {
                    required: true
                }
            },
            updatedAt: {
                type: 'date',
                flags: []
            }
        }
    },
    product_stream: {
        entity: 'product_stream',
        properties: {
            id: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            apiFilter: {
                type: 'json_object',
                properties: [],
                flags: {
                    write_protected: [
                        []
                    ]
                }
            },
            invalid: {
                type: 'boolean',
                flags: {
                    write_protected: [
                        []
                    ]
                }
            },
            name: {
                type: 'string',
                flags: {
                    required: true,
                    search_ranking: 500,
                    translatable: true
                }
            },
            description: {
                type: 'text',
                flags: {
                    translatable: true
                }
            },
            customFields: {
                type: 'json_object',
                properties: [],
                flags: {
                    translatable: true
                }
            },
            translations: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'product_stream_translation',
                flags: {
                    cascade_delete: true,
                    required: true
                },
                localField: 'id',
                referenceField: 'productStreamId'
            },
            filters: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'product_stream_filter',
                flags: {
                    cascade_delete: true
                },
                localField: 'id',
                referenceField: 'productStreamId'
            },
            productCrossSellings: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'product_cross_selling',
                flags: {
                    cascade_delete: true
                },
                localField: 'id',
                referenceField: 'productStreamId'
            },
            productExports: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'product_export',
                flags: [],
                localField: 'id',
                referenceField: 'productStreamId'
            },
            createdAt: {
                type: 'date',
                flags: {
                    required: true
                }
            },
            updatedAt: {
                type: 'date',
                flags: []
            },
            translated: {
                type: 'json_object',
                properties: [],
                flags: {
                    computed: true,
                    runtime: true
                }
            }
        }
    },
    product_stream_filter: {
        entity: 'product_stream_filter',
        properties: {
            id: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            productStreamId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            parentId: {
                type: 'uuid',
                flags: []
            },
            type: {
                type: 'string',
                flags: {
                    required: true
                }
            },
            field: {
                type: 'string',
                flags: []
            },
            operator: {
                type: 'string',
                flags: []
            },
            value: {
                type: 'text',
                flags: []
            },
            parameters: {
                type: 'json_object',
                properties: [],
                flags: []
            },
            position: {
                type: 'int',
                flags: []
            },
            productStream: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'product_stream',
                flags: [],
                localField: 'productStreamId',
                referenceField: 'id'
            },
            parent: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'product_stream_filter',
                flags: [],
                localField: 'parentId',
                referenceField: 'id'
            },
            queries: {
                type: 'association',
                relation: 'one_to_many',
                entity: 'product_stream_filter',
                flags: {
                    cascade_delete: true
                },
                localField: 'id',
                referenceField: 'parentId'
            },
            customFields: {
                type: 'json_object',
                properties: [],
                flags: []
            },
            createdAt: {
                type: 'date',
                flags: {
                    required: true
                }
            },
            updatedAt: {
                type: 'date',
                flags: []
            }
        }
    },
    product_stream_translation: {
        entity: 'product_stream_translation',
        properties: {
            name: {
                type: 'string',
                flags: {
                    required: true
                }
            },
            description: {
                type: 'text',
                flags: []
            },
            customFields: {
                type: 'json_object',
                properties: [],
                flags: []
            },
            createdAt: {
                type: 'date',
                flags: {
                    required: true
                }
            },
            updatedAt: {
                type: 'date',
                flags: []
            },
            productStreamId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            languageId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            productStream: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'product_stream',
                flags: [],
                localField: 'productStreamId',
                referenceField: 'id'
            },
            language: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'language',
                flags: [],
                localField: 'languageId',
                referenceField: 'id'
            }
        }
    },
    product_tag: {
        entity: 'product_tag',
        properties: {
            productId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            productVersionId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            tagId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            product: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'product',
                flags: [],
                localField: 'productId',
                referenceField: 'id'
            },
            tag: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'tag',
                flags: [],
                localField: 'tagId',
                referenceField: 'id'
            }
        }
    },
    product_translation: {
        entity: 'product_translation',
        properties: {
            metaDescription: {
                type: 'string',
                flags: []
            },
            name: {
                type: 'string',
                flags: {
                    required: true
                }
            },
            keywords: {
                type: 'text',
                flags: []
            },
            description: {
                type: 'text',
                flags: {
                    allow_html: true
                }
            },
            metaTitle: {
                type: 'string',
                flags: []
            },
            packUnit: {
                type: 'string',
                flags: []
            },
            packUnitPlural: {
                type: 'string',
                flags: []
            },
            customFields: {
                type: 'json_object',
                properties: [],
                flags: []
            },
            createdAt: {
                type: 'date',
                flags: {
                    required: true
                }
            },
            updatedAt: {
                type: 'date',
                flags: []
            },
            productId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            languageId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            },
            product: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'product',
                flags: [],
                localField: 'productId',
                referenceField: 'id'
            },
            language: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'language',
                flags: [],
                localField: 'languageId',
                referenceField: 'id'
            },
            productVersionId: {
                type: 'uuid',
                flags: {
                    primary_key: true,
                    required: true
                }
            }
        }
    },
    product_visibility: {
        entity: 'product_visibility',
        properties: {
            id: {
                type: 'uuid',
                flags: {
                    required: true,
                    primary_key: true
                }
            },
            productId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            productVersionId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            salesChannelId: {
                type: 'uuid',
                flags: {
                    required: true
                }
            },
            visibility: {
                type: 'int',
                flags: {
                    required: true
                }
            },
            salesChannel: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'sales_channel',
                flags: [],
                localField: 'salesChannelId',
                referenceField: 'id'
            },
            product: {
                type: 'association',
                relation: 'many_to_one',
                entity: 'product',
                flags: [],
                localField: 'productId',
                referenceField: 'id'
            },
            createdAt: {
                type: 'date',
                flags: {
                    required: true
                }
            },
            updatedAt: {
                type: 'date',
                flags: []
            }
        }
    }
};
