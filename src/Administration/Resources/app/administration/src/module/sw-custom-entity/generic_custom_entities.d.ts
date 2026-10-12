/**
 * @sw-package framework
 */

declare namespace EntitySchema {
    interface Entities {
        generic_custom_entity: generic_custom_entity;
    }

    type GenericCustomEntityId = string & { readonly __brand: 'GenericCustomEntityId' };

    interface EntityKeys {
        generic_custom_entity: GenericCustomEntityId;
    }

    interface generic_custom_entity {
        id: EntityKey<'generic_custom_entity'>;
        swCmsPageId?: EntityKey<'cms_page'> | null;
        swSlotConfig?: { [key: string]: unknown } | null;
        swSeoMetaTitle?: string | null;
        swSeoMetaDescription?: string | null;
        swSeoUrl?: string | null;
        swOgTitle?: string | null;
        swOgDescription?: string | null;
        swOgImageId?: EntityKey<'media'> | null;
    }
}
