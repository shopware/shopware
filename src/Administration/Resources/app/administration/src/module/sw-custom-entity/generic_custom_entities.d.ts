declare namespace EntitySchema {
    interface generic_custom_entity {
        id: string;
        swCmsPageId?: string | null;
        swSlotConfig?: {[key: string]: unknown} | null;
        swSeoMetaTitle?: string | null;
        swSeoMetaDescription?: string | null;
        swSeoUrl?: string | null;
        swOgTitle?: string | null;
        swOgDescription?: string | null;
        swOgImageId?: string | null;
    }

    interface Entities {
        generic_custom_entity: generic_custom_entity;
    }
}
