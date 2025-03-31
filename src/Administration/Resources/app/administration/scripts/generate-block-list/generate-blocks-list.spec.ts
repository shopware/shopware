import fs from 'fs';
import { updateBlocksList } from './index';

jest.mock('fs');

describe('generate-blocks-list', () => {
    const blocks = [
        'block3',
        'block1',
        'block2',
        'block1',
        'block2',
    ];
    const mockWriteFileSync = jest.spyOn(fs, 'writeFileSync');

    beforeEach(() => {
        jest.clearAllMocks();
    });

    it('should update the blocks list file with unique sorted blocks', () => {
        updateBlocksList(blocks);

        expect(mockWriteFileSync).toHaveBeenCalledWith(expect.any(String), `[\n "block1",\n "block2",\n "block3"\n]`);
    });
});
