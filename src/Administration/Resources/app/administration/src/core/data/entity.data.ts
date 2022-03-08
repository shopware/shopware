import { cloneDeep } from 'src/core/service/utils/object.utils';
import BaseEntity from '@shopware-ag/admin-extension-sdk/es/data/_internals/BaseEntity';

export default class Entity extends BaseEntity {
    id: string;

    _origin: unknown;

    _entityName: string;

    _draft: {[key: string]: unknown};

    _isDirty: boolean;

    _isNew: boolean;

    constructor(id: string, entityName: string, data: {[key: string]: unknown}) {
        super();

        this.id = id;
        this._origin = cloneDeep(data);
        this._entityName = entityName;
        this._draft = data;
        this._isDirty = false;
        this._isNew = false;
        // eslint-disable-next-line @typescript-eslint/no-this-alias
        const that = this;

        // @ts-expect-error
        return new Proxy(this._draft, {
            get(target, property): unknown {
                if (property in that._draft) {
                    // @ts-expect-error
                    return that._draft[property];
                }

                // @ts-expect-error
                return that[property];
            },

            set(target, property, value): boolean {
                // @ts-expect-error
                Shopware.Application.view.setReactive(that._draft, property, value);
                that._isDirty = true;

                return true;
            },
        });
    }

    /**
     * Marks the entity as new. New entities will be provided as create request to the server
     */
    markAsNew(): void {
        this._isNew = true;
    }

    /**
     * Allows to check if the entity is a new entity and should be provided as create request to the server
     */
    isNew(): boolean {
        return this._isNew;
    }

    /**
     * Allows to check if the entity changed
     */
    getIsDirty(): boolean {
        return this._isDirty;
    }

    /**
     * Allows access the origin entity value. The origin value contains the server values
     */
    getOrigin(): unknown {
        return this._origin;
    }

    /**
     * Allows to access the draft value. The draft value contains all local changes of the entity
     */
    getDraft(): unknown {
        return this._draft;
    }

    /**
     * Allows to access the entity name. The entity name is used as unique identifier `product`, `media`, ...
     */
    getEntityName(): string {
        return this._entityName;
    }
}
