/**
 * @sw-package framework
 */
function compareVersions(version1: string, version2: string, comparator: '=' | '>' | '<' | '<=' | '>='): boolean {
    const comparison = compareVersionsRaw(version1, version2);
    return {
        '=': comparison === 0,
        '>': comparison === 1,
        '<': comparison === -1,
        '>=': comparison >= 0,
        '<=': comparison <= 0,
    }[comparator];
}

function compareVersionsRaw(version1: string, version2: string): number {
    const v1 = parseVersion(version1);
    const v2 = parseVersion(version2);

    // Compare the four numeric parts
    for (let i = 0; i < Math.min(v1.parts.length, v2.parts.length); i += 1) {
        const num1 = v1.parts[i] || 0;
        const num2 = v2.parts[i] || 0;
        if (num1 !== num2) return num1 > num2 ? 1 : -1;
    }

    // Compare suffixes alphabetically (versions without a suffix are considered greater)
    if (v1.suffix !== v2.suffix) {
        if (!v1.suffix) return 1;
        if (!v2.suffix) return -1;
        return v1.suffix.localeCompare(v2.suffix);
    }

    return 0; // Versions are equal
}

function parseVersion(version: string) {
    const [
        numbers,
        suffix = '',
    ] = version.split('-');
    return { parts: numbers.split('.').map(Number), suffix };
}

/**
 * @private
 */
export default compareVersions;
