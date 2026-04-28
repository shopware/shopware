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

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function getMeteorInheritanceConfig(inheritance: InheritanceSlotProps | null = null, inheritedValue: unknown = null) {
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
