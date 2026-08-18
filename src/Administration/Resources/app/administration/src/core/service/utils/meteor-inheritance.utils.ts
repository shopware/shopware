/**
 * @sw-package framework
 * @private
 */

type InheritanceSlotProps = {
    isInheritField?: boolean;
    isInherited?: boolean;
    removeInheritance?: () => void;
    restoreInheritance?: () => void;
};

/**
 * @sw-package framework
 * @private
 */
export function mapInheritanceSlotPropsToMeteorProps(
    inheritance: InheritanceSlotProps | null = null,
    inheritedValue: unknown = null,
) {
    if (!inheritance) {
        return {};
    }

    return {
        isInheritanceField: inheritance.isInheritField,
        isInherited: inheritance.isInherited,
        inheritanceRemove: inheritance.removeInheritance,
        inheritanceRestore: inheritance.restoreInheritance,
        inheritedValue: inheritance.isInheritField ? inheritedValue : null,
    };
}
