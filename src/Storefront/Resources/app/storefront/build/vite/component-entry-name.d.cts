export function normalizeComponentEntryName(filePath: string, preserveExtension?: boolean): string;
export function warnForDuplicateEntryNames(
    files: string[],
    getEntryName: (file: string) => string,
    context: string,
    entryType: string,
): void;
