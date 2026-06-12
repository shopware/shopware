/**
 * @sw-package fundamentals@framework
 */

const operationToRole: Record<string, string> = {
    read: 'viewer',
    update: 'editor',
    create: 'creator',
    delete: 'deleter',
};

/**
 * Returns true when a colon-format privilege (e.g. "sales_channel:read") is covered by the
 * given privilege list, which may contain either colon-format or dot-format entries
 * (e.g. "sales_channel.viewer" from the General tab of the role editor).
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function colonToDot(chip: string): string | null {
    if (chip.startsWith('<')) return null;
    const [
        entity,
        operation,
    ] = chip.split(':');
    const role = operationToRole[operation];
    return entity && role ? `${entity}.${role}` : null;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function isPrivilegeGranted(chip: string, grantedPrivileges: string[]): boolean {
    if (grantedPrivileges.includes(chip)) return true;
    const [
        entity,
        operation,
    ] = chip.split(':');
    return !!operationToRole[operation] && grantedPrivileges.includes(`${entity}.${operationToRole[operation]}`);
}

interface RequiredPrivileges {
    static?: string[];
    entityParam?: string | null;
    operations?: string[];
}

/**
 * Converts a tool's requiredPrivileges structure into a flat list of display chip strings.
 * Static privileges are included as-is; entity/operation pairs become "<entity>:op" format.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function computePrivilegeChips(requiredPrivileges: RequiredPrivileges | null | undefined): string[] {
    if (!requiredPrivileges) {
        return [];
    }

    const chips = [...(requiredPrivileges.static ?? [])];

    if (requiredPrivileges.entityParam) {
        (requiredPrivileges.operations ?? []).forEach((op) => {
            chips.push(`<${requiredPrivileges.entityParam}>:${op}`);
        });
    }

    return chips;
}
