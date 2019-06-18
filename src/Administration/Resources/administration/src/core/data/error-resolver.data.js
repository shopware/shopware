import { State } from 'src/core/shopware';
import ShopwareError from './ShopwareError';

const regExRegularPointer = /\/([^\/]*)(.*)/;
const regExToManyAssociation = /\/(\w*)\/(\d)(\/.*)/;
const regExTranslations = /\/translations\/([a-fA-f\d]*)(\/.*)/;

export default class ErrorResolver {
    constructor(entityDefinitionRegistry) {
        this.definitionRegistry = entityDefinitionRegistry;
        this.errorStore = State.getStore('error');
    }

    handleWriteError({ response }, entity, changeset) {
        if (!this.isErrorDataSet(response)) {
            throw response;
        }

        const apiErrors = response.data.errors;
        const definition = this.definitionRegistry.get(entity.getEntityName());

        const systemErrors = [];
        apiErrors.forEach((error) => {
            this.resolveError(error, definition, entity, changeset, systemErrors);
        });

        if (systemErrors.length > 0) {
            this.addSystemErrors(systemErrors);
        }
    }

    handleDeleteError() { // ({ response }, deletionQueue) {

    }

    addSystemErrors(systemErrors) {
        systemErrors.forEach((error) => {
            this.errorStore.addSystemError(error);
        });
    }

    /**
     * @private
     * @param error
     * @param definition
     * @param entity
     * @param changeset
     * @param systemErrors
     */
    resolveError(error, definition, entity, changeset, systemErrors) {
        const [, fieldName] = error.source.pointer.match(regExRegularPointer);
        const field = definition.getField(fieldName);

        if (!field) {
            systemErrors.push(error);
            return;
        }

        if (definition.isToManyAssociation(field)) {
            if (fieldName === 'translations') {
                this.resolveTranslation(error, definition, entity, changeset, systemErrors);
                return;
            }

            this.handleToManyAssociationError(error, fieldName, entity[fieldName], changeset, systemErrors);
            return;
        }
        systemErrors.push(error);
        this.errorStore.addApiError(this.getErrorPath(entity, fieldName), new ShopwareError(error));
    }

    /**
     * @private
     * @param error
     * @param currentField
     * @param entityCollection
     * @param changeset
     * @param systemErrors
     */
    handleToManyAssociationError(error, currentField, entityCollection, changeset, systemErrors) {
        const definition = this.definitionRegistry.get(entityCollection.entity);

        if (!definition) {
            systemErrors.push(error);
            return;
        }

        const [, associationName, index, field] = error.source.pointer.match(regExToManyAssociation);
        const associationChanges = changeset[associationName][index];
        const entity = entityCollection.get(associationChanges.id);
        error.source.pointer = field;

        this.resolveError(error, definition, entity, changeset, systemErrors);
    }

    resolveTranslation(error, definition, entity, changeset, systemErrors) {
        const [, /* languageId */, fieldName] = error.source.pointer.match(regExTranslations);
        error.source.pointer = fieldName;

        const field = definition.getField(fieldName.substr(1));
        if (!field) {
            systemErrors.push(error);
            return;
        }

        if (!definition.isTranslatableField(field)) {
            throw new Error(`[ErrorResolver] translatable field ${fieldName}`);
        }

        this.resolveError(error, definition, entity, changeset, systemErrors);
    }

    /**
     * @private
     * @param response
     * @returns {boolean}
     */
    isErrorDataSet(response) {
        if (!response.data) {
            return false;
        }

        return !!response.data.errors;
    }

    /**
      * @private
      */
    getErrorPath(entity, currentField) {
        return `${entity.getEntityName()}.${entity.id}.${currentField}`;
    }
}
