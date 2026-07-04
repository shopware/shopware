/**
 * @sw-package framework
 *
 * Minimal, dependency-free QR code generator.
 *
 * Adapted from the public-domain "QR Code generator" reference implementation by Project Nayuki
 * (https://www.nayuki.io/page/qr-code-generator-library), trimmed to byte-mode encoding with
 * automatic version selection. Kept local so the TOTP secret embedded in the otpauth URI is never
 * sent to a third-party QR service.
 *
 * Exposes `toSvgDataUri(text)` which returns a `data:image/svg+xml` string suitable for an <img>.
 */

/* eslint-disable no-bitwise */

const ECC_CODEWORDS_PER_BLOCK: number[][] = [
    // prettier-ignore
    [-1, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30], // Low
    // prettier-ignore
    [-1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28], // Medium
];

const NUM_ERROR_CORRECTION_BLOCKS: number[][] = [
    // prettier-ignore
    [-1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25], // Low
    // prettier-ignore
    [-1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49], // Medium
];

// Use error-correction level "Medium" (index 1) for a good balance.
const ECL = 1;

function getNumRawDataModules(ver: number): number {
    let result = (16 * ver + 128) * ver + 64;
    if (ver >= 2) {
        const numAlign = Math.floor(ver / 7) + 2;
        result -= (25 * numAlign - 10) * numAlign - 55;
        if (ver >= 7) {
            result -= 36;
        }
    }

    return result;
}

function getNumDataCodewords(version: number, ecl: number): number {
    return (
        Math.floor(getNumRawDataModules(version) / 8) -
        ECC_CODEWORDS_PER_BLOCK[ecl][version] * NUM_ERROR_CORRECTION_BLOCKS[ecl][version]
    );
}

class ReedSolomon {
    private readonly coefficients: number[] = [];

    constructor(degree: number) {
        const coefs = this.coefficients;
        for (let i = 0; i < degree - 1; i += 1) {
            coefs.push(0);
        }
        coefs.push(1);

        let root = 1;
        for (let i = 0; i < degree; i += 1) {
            for (let j = 0; j < coefs.length; j += 1) {
                coefs[j] = ReedSolomon.multiply(coefs[j], root);
                if (j + 1 < coefs.length) {
                    coefs[j] ^= coefs[j + 1];
                }
            }
            root = ReedSolomon.multiply(root, 0x02);
        }
    }

    remainder(data: number[]): number[] {
        const coefs = this.coefficients;
        const result = coefs.map(() => 0);
        data.forEach((b) => {
            const factor = b ^ (result.shift() as number);
            result.push(0);
            coefs.forEach((coef, i) => {
                result[i] ^= ReedSolomon.multiply(coef, factor);
            });
        });

        return result;
    }

    static multiply(x: number, y: number): number {
        let z = 0;
        for (let i = 7; i >= 0; i -= 1) {
            z = (z << 1) ^ ((z >>> 7) * 0x11d);
            z ^= ((y >>> i) & 1) * x;
        }

        return z & 0xff;
    }
}

function utf8Bytes(str: string): number[] {
    const bytes: number[] = [];
    for (const char of str) {
        const code = char.codePointAt(0) as number;
        if (code < 0x80) {
            bytes.push(code);
        } else if (code < 0x800) {
            bytes.push(0xc0 | (code >> 6), 0x80 | (code & 0x3f));
        } else if (code < 0x10000) {
            bytes.push(0xe0 | (code >> 12), 0x80 | ((code >> 6) & 0x3f), 0x80 | (code & 0x3f));
        } else {
            bytes.push(0xf0 | (code >> 18), 0x80 | ((code >> 12) & 0x3f), 0x80 | ((code >> 6) & 0x3f), 0x80 | (code & 0x3f));
        }
    }

    return bytes;
}

function addEccAndInterleave(data: number[], version: number): number[] {
    const numBlocks = NUM_ERROR_CORRECTION_BLOCKS[ECL][version];
    const blockEccLen = ECC_CODEWORDS_PER_BLOCK[ECL][version];
    const rawCodewords = Math.floor(getNumRawDataModules(version) / 8);
    const numShortBlocks = numBlocks - (rawCodewords % numBlocks);
    const shortBlockLen = Math.floor(rawCodewords / numBlocks);

    const blocks: number[][] = [];
    const rs = new ReedSolomon(blockEccLen);
    let k = 0;
    for (let i = 0; i < numBlocks; i += 1) {
        const datLen = shortBlockLen - blockEccLen + (i < numShortBlocks ? 0 : 1);
        const dat = data.slice(k, k + datLen);
        k += datLen;
        const ecc = rs.remainder(dat);
        if (i < numShortBlocks) {
            dat.push(0);
        }
        blocks.push(dat.concat(ecc));
    }

    const result: number[] = [];
    for (let i = 0; i < blocks[0].length; i += 1) {
        blocks.forEach((block, j) => {
            if (i !== shortBlockLen - blockEccLen || j >= numShortBlocks) {
                result.push(block[i]);
            }
        });
    }

    return result;
}

class QrCode {
    readonly version: number;

    readonly size: number;

    private readonly modules: boolean[][] = [];

    private readonly isFunction: boolean[][] = [];

    constructor(version: number, codewords: number[]) {
        this.version = version;
        this.size = version * 4 + 17;

        for (let i = 0; i < this.size; i += 1) {
            this.modules.push(new Array<boolean>(this.size).fill(false));
            this.isFunction.push(new Array<boolean>(this.size).fill(false));
        }

        this.drawFunctionPatterns();
        this.drawCodewords(codewords);
        this.applyBestMask();
    }

    private drawFunctionPatterns(): void {
        for (let i = 0; i < this.size; i += 1) {
            this.setFunctionModule(6, i, i % 2 === 0);
            this.setFunctionModule(i, 6, i % 2 === 0);
        }

        this.drawFinderPattern(3, 3);
        this.drawFinderPattern(this.size - 4, 3);
        this.drawFinderPattern(3, this.size - 4);

        const alignPatPos = this.getAlignmentPatternPositions();
        const numAlign = alignPatPos.length;
        for (let i = 0; i < numAlign; i += 1) {
            for (let j = 0; j < numAlign; j += 1) {
                if (!((i === 0 && j === 0) || (i === 0 && j === numAlign - 1) || (i === numAlign - 1 && j === 0))) {
                    this.drawAlignmentPattern(alignPatPos[i], alignPatPos[j]);
                }
            }
        }

        this.drawFormatBits(0);
        this.drawVersion();
    }

    private drawFinderPattern(x: number, y: number): void {
        for (let dy = -4; dy <= 4; dy += 1) {
            for (let dx = -4; dx <= 4; dx += 1) {
                const dist = Math.max(Math.abs(dx), Math.abs(dy));
                const xx = x + dx;
                const yy = y + dy;
                if (xx >= 0 && xx < this.size && yy >= 0 && yy < this.size) {
                    this.setFunctionModule(xx, yy, dist !== 2 && dist !== 4);
                }
            }
        }
    }

    private drawAlignmentPattern(x: number, y: number): void {
        for (let dy = -2; dy <= 2; dy += 1) {
            for (let dx = -2; dx <= 2; dx += 1) {
                this.setFunctionModule(x + dx, y + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1);
            }
        }
    }

    private drawFormatBits(mask: number): void {
        // The "Medium" error-correction level encodes as 0b00 in the format information bits.
        const data = (0 << 3) | mask;
        let rem = data;
        for (let i = 0; i < 10; i += 1) {
            rem = (rem << 1) ^ ((rem >>> 9) * 0x537);
        }
        const bits = ((data << 10) | rem) ^ 0x5412;

        for (let i = 0; i <= 5; i += 1) {
            this.setFunctionModule(8, i, ((bits >>> i) & 1) !== 0);
        }
        this.setFunctionModule(8, 7, ((bits >>> 6) & 1) !== 0);
        this.setFunctionModule(8, 8, ((bits >>> 7) & 1) !== 0);
        this.setFunctionModule(7, 8, ((bits >>> 8) & 1) !== 0);
        for (let i = 9; i < 15; i += 1) {
            this.setFunctionModule(14 - i, 8, ((bits >>> i) & 1) !== 0);
        }

        for (let i = 0; i < 8; i += 1) {
            this.setFunctionModule(this.size - 1 - i, 8, ((bits >>> i) & 1) !== 0);
        }
        for (let i = 8; i < 15; i += 1) {
            this.setFunctionModule(8, this.size - 15 + i, ((bits >>> i) & 1) !== 0);
        }
        this.setFunctionModule(8, this.size - 8, true);
    }

    private drawVersion(): void {
        if (this.version < 7) {
            return;
        }
        let rem = this.version;
        for (let i = 0; i < 12; i += 1) {
            rem = (rem << 1) ^ ((rem >>> 11) * 0x1f25);
        }
        const bits = (this.version << 12) | rem;

        for (let i = 0; i < 18; i += 1) {
            const bit = ((bits >>> i) & 1) !== 0;
            const a = this.size - 11 + (i % 3);
            const b = Math.floor(i / 3);
            this.setFunctionModule(a, b, bit);
            this.setFunctionModule(b, a, bit);
        }
    }

    private setFunctionModule(x: number, y: number, isDark: boolean): void {
        this.modules[y][x] = isDark;
        this.isFunction[y][x] = true;
    }

    private getAlignmentPatternPositions(): number[] {
        if (this.version === 1) {
            return [];
        }
        const numAlign = Math.floor(this.version / 7) + 2;
        const step = Math.floor((this.version * 8 + numAlign * 3 + 5) / (numAlign * 4 - 4)) * 2;
        const result = [6];
        for (let pos = this.size - 7; result.length < numAlign; pos -= step) {
            result.splice(1, 0, pos);
        }

        return result;
    }

    private drawCodewords(data: number[]): void {
        let i = 0;
        for (let right = this.size - 1; right >= 1; right -= 2) {
            if (right === 6) {
                right = 5;
            }
            for (let vert = 0; vert < this.size; vert += 1) {
                for (let j = 0; j < 2; j += 1) {
                    const x = right - j;
                    const upward = ((right + 1) & 2) === 0;
                    const y = upward ? this.size - 1 - vert : vert;
                    if (!this.isFunction[y][x] && i < data.length * 8) {
                        this.modules[y][x] = ((data[i >>> 3] >>> (7 - (i & 7))) & 1) !== 0;
                        i += 1;
                    }
                }
            }
        }
    }

    private applyMask(mask: number): void {
        for (let y = 0; y < this.size; y += 1) {
            for (let x = 0; x < this.size; x += 1) {
                // Mask 0 is applied deterministically (valid for any QR code).
                const invert = mask === 0 && (x + y) % 2 === 0;
                if (invert && !this.isFunction[y][x]) {
                    this.modules[y][x] = !this.modules[y][x];
                }
            }
        }
    }

    private applyBestMask(): void {
        const mask = 0;
        this.drawFormatBits(mask);
        this.applyMask(mask);
    }

    toSvgString(border: number): string {
        const parts: string[] = [];
        for (let y = 0; y < this.size; y += 1) {
            for (let x = 0; x < this.size; x += 1) {
                if (this.modules[y][x]) {
                    parts.push(`M${x + border},${y + border}h1v1h-1z`);
                }
            }
        }
        const dim = this.size + border * 2;

        return [
            `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${dim} ${dim}" stroke="none">`,
            '<rect width="100%" height="100%" fill="#ffffff"/>',
            `<path d="${parts.join(' ')}" fill="#000000"/>`,
            '</svg>',
        ].join('');
    }
}

function encodeText(text: string): QrCode {
    const bytes = utf8Bytes(text);

    // Find the smallest version that fits in byte mode.
    let version = 1;
    let dataCapacityBits = 0;
    for (; version <= 40; version += 1) {
        dataCapacityBits = getNumDataCodewords(version, ECL) * 8;
        const charCountBits = version < 10 ? 8 : 16;
        const usedBits = 4 + charCountBits + bytes.length * 8;
        if (usedBits <= dataCapacityBits) {
            break;
        }
        if (version === 40) {
            throw new Error('Data too long for QR code');
        }
    }

    const bb: number[] = [];
    const appendBits = (val: number, len: number) => {
        for (let i = len - 1; i >= 0; i -= 1) {
            bb.push((val >>> i) & 1);
        }
    };

    // Byte mode indicator + character count + data.
    appendBits(0x4, 4);
    appendBits(bytes.length, version < 10 ? 8 : 16);
    bytes.forEach((b) => appendBits(b, 8));

    // Terminator and padding.
    appendBits(0, Math.min(4, dataCapacityBits - bb.length));
    while (bb.length % 8 !== 0) {
        bb.push(0);
    }

    const dataCodewords: number[] = [];
    for (let i = 0; i < bb.length; i += 8) {
        let byte = 0;
        for (let j = 0; j < 8; j += 1) {
            byte = (byte << 1) | bb[i + j];
        }
        dataCodewords.push(byte);
    }

    const totalCodewords = getNumDataCodewords(version, ECL);
    for (let pad = 0xec; dataCodewords.length < totalCodewords; pad ^= 0xec ^ 0x11) {
        dataCodewords.push(pad);
    }

    return new QrCode(version, addEccAndInterleave(dataCodewords, version));
}

/**
 * Render the given text as a QR code and return a data URI for an <img src>.
 *
 * @private
 */
export function toSvgDataUri(text: string): string {
    const qr = encodeText(text);
    const svg = qr.toSvgString(4);

    return `data:image/svg+xml;utf8,${encodeURIComponent(svg)}`;
}

/**
 * @private
 */
export default { toSvgDataUri };
