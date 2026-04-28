/**
 * @sw-package framework
 * @private
 */

export function getMeteorInheritanceConfig(inheritance = null, inheritedValue = null) {
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
