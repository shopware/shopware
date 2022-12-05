declare namespace EntitySchema {
    interface generic_custom_entity {
        id: string;
        swCmsPageId?: string | null;
        swSlotConfig?: {[key: string]: unknown} | null;
    }

    interface Entities {
        generic_custom_entity: generic_custom_entity;
    }
}
