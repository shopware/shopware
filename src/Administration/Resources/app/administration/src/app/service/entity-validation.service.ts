import type { Entity } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';
import type ChangesetGenerator from 'src/core/data/changeset-generator.data';
import type EntityDefinition from 'src/core/data/entity-definition.data';
import type ErrorResolver from 'src/core/data/error-resolver.data';
import type EntityDefinitionFactory from 'src/core/factory/entity-definition.factory';

/**
 * @module app/entity-validation-service
 */

/**
 * @private
 */
export type ValidationError = {
    code: string,
    source: {
        pointer: string
    }
}

/**
 * @private
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any,max-len,sw-deprecation-rules/private-feature-declarations
export type CustomValidator = (errors: ValidationError[], entity: Entity<any>, definition: EntityDefinition<any>) => ValidationError[];


/**
 * A service for client side validation of entities
 * @private
 */
export default class EntityValidationService {
    private entityDefinitionFactory: typeof EntityDefinitionFactory;

    private changesetGenerator: ChangesetGenerator;

    private errorResolver: ErrorResolver;

    public static readonly ERROR_CODE_REQUIRED = 'c1051bb4-d103-4f74-8988-acbcafc7fdc3';

    constructor(
        entityDefinitionFactory: typeof EntityDefinitionFactory,
        changesetGenerator: ChangesetGenerator,
        errorResolver: ErrorResolver,
    ) {
        this.entityDefinitionFactory = entityDefinitionFactory;
        this.changesetGenerator = changesetGenerator;
        this.errorResolver = errorResolver;
    }

    public static createRequiredError(fieldPointer: string): ValidationError {
        return {
            code: EntityValidationService.ERROR_CODE_REQUIRED,
            source: {
                pointer: fieldPointer,
            },
        };
    }

    /**
     * Validates an entity and returns true if it is valid.
     * Found errors are reported to the internal error resolver and
     * displayed by looking into the error Store (is done automatically for most sw-fields).
     *
     * A CustomValidator callback can be provided to modify or add to the found errors.
     */
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    public validate(entity: Entity<any>, customValidator: CustomValidator | undefined): boolean {
        // eslint-disable-next-line max-len
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-call
        const entityName = entity.getEntityName();
        const definition = this.entityDefinitionFactory.get(entityName);
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
        const { changes } = this.changesetGenerator.generate(entity);
        let errors: ValidationError[] = [];

        // check for required fields
        const requiredFields = definition.getRequiredFields() as Record<string, never>;
        errors.push(...this.getRequiredErrors(entity, requiredFields));

        // run custom validator
        if (customValidator !== undefined) {
            errors = customValidator(errors, entity, definition);
        }

        // report errors
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
        this.errorResolver.handleWriteErrors({ errors }, [{ entity, changes }]);
        return errors.length < 1;
    }

    /**
     * Tries to find all the required fields which are not set in the given entity.
     * TODO: This implementation may only find required fields on the top level and may needs further improvement
     * for other use cases.
     *
     * @private
     */
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    private getRequiredErrors(entity: Entity<any>, requiredFields: Record<string, never>): ValidationError[] {
        const errors: ValidationError[] = [];

        Object.keys(requiredFields).forEach((field) => {
            // eslint-disable-next-line @typescript-eslint/no-explicit-any,@typescript-eslint/no-unsafe-assignment
            const fieldDefinition = requiredFields[field] as any;
            // eslint-disable-next-line max-len
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-member-access
            const value = entity[field];
            // eslint-disable-next-line prefer-regex-literals
            const fieldFilterRegex = new RegExp('version|createdAt|translations', 'i');

            if (fieldFilterRegex.test(field)) {
                return;
            }

            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            if (field.includes('price') && fieldDefinition.type === 'json_object' && Array.isArray(value)) {
                // detected price field -> custom handling of price fields
                value.forEach((price, index) => {
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                    if (price.net === undefined || price.net === null) {
                        errors.push(EntityValidationService.createRequiredError(`/0/${field}/${index}/net`));
                    }
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                    if (price.gross === undefined || price.gross === null) {
                        errors.push(EntityValidationService.createRequiredError(`/0/${field}/${index}/gross`));
                    }
                });
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            } else if (value === undefined || (fieldDefinition.type === 'string' && value === '')) {
                // any other field
                errors.push(EntityValidationService.createRequiredError(`/0/${field}`));
            }
        });

        return errors;
    }
}
