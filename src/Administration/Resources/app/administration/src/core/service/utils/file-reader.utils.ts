/**
 * @package admin
 *
 * @module core/service/utils/file-reader
 */

function registerPromiseOnFileReader(
    fileReader: FileReader,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    resolve: (value?: any) => void,
    reject: (reason?: unknown) => void,
): void {
    fileReader.onerror = (): void => {
        fileReader.abort();
        reject(new DOMException('Problem parsing file.'));
    };

    fileReader.onload = (): void => {
        resolve(fileReader.result);
    };
}

function splitFileNameAndExtension(completeFileName: string): {
    extension: string | undefined;
    fileName: string;
} {
    const fileParts = completeFileName.split('.');

    // no dot in filename
    if (fileParts.length === 1) {
        return {
            extension: '',
            fileName: completeFileName,
        };
    }

    // hidden file without extension
    if (fileParts.length === 2 && !fileParts[0]) {
        return {
            extension: '',
            fileName: completeFileName,
        };
    }

    return {
        extension: fileParts.pop(),
        fileName: fileParts.join('.'),
    };
}

function readFileAsArrayBuffer<FILE = unknown>(inputFile: Blob): Promise<FILE> {
    const fReader = new FileReader();

    return new Promise((resolve, reject) => {
        registerPromiseOnFileReader(fReader, resolve, reject);

        fReader.readAsArrayBuffer(inputFile);
    });
}

function readFileAsDataURL<FILE = unknown>(inputFile: Blob): Promise<FILE> {
    const fReader = new FileReader();

    return new Promise((resolve, reject) => {
        registerPromiseOnFileReader(fReader, resolve, reject);

        fReader.readAsDataURL(inputFile);
    });
}

function readFileAsText<FILE = unknown>(inputFile: Blob): Promise<FILE> {
    const fReader = new FileReader();

    return new Promise((resolve, reject) => {
        registerPromiseOnFileReader(fReader, resolve, reject);

        fReader.readAsText(inputFile);
    });
}

function getNameAndExtensionFromFile(fileHandle: File): {
    extension: string | undefined;
    fileName: string;
} {
    return splitFileNameAndExtension(fileHandle.name);
}

function getNameAndExtensionFromUrl(urlObject: URL): {
    extension: string | undefined;
    fileName: string;
} {
    let ref = urlObject.href.split('/').pop();

    if (!ref) {
        throw new Error('Invalid URL');
    }

    const indexOfQueryIndicator = ref.indexOf('?');
    if (indexOfQueryIndicator > 0) {
        ref = ref.substring(0, indexOfQueryIndicator);
    }

    ref = decodeURI(ref);

    return splitFileNameAndExtension(ref);
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    readFileAsArrayBuffer,
    readFileAsDataURL,
    readFileAsText,
    getNameAndExtensionFromFile,
    getNameAndExtensionFromUrl,
};
