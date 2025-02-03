/**
 * @sw-package framework
 */
import compareVersions from './compare-versions.helper';

describe('compareVersions', () => {
    it('should return true for equal versions with "=" comparator', () => {
        expect(compareVersions('1.0.0', '1.0.0', '=')).toBe(true);
        expect(compareVersions('1.0.0.0', '1.0.0.0', '=')).toBe(true);
    });

    it('should return false for different versions with "=" comparator', () => {
        expect(compareVersions('1.0.0', '1.0.1', '=')).toBe(false);
        expect(compareVersions('1.0.0.0', '1.0.0.1', '=')).toBe(false);
    });

    it('should return true for greater version with ">" comparator', () => {
        expect(compareVersions('1.0.1', '1.0.0', '>')).toBe(true);
        expect(compareVersions('1.0.0.1', '1.0.0.0', '>')).toBe(true);
    });

    it('should return false for lesser version with ">" comparator', () => {
        expect(compareVersions('1.0.0', '1.0.1', '>')).toBe(false);
        expect(compareVersions('1.0.0.0', '1.0.0.1', '>')).toBe(false);
    });

    it('should return true for lesser version with "<" comparator', () => {
        expect(compareVersions('1.0.0', '1.0.1', '<')).toBe(true);
        expect(compareVersions('1.0.0.0', '1.0.0.1', '<')).toBe(true);
    });

    it('should return false for greater version with "<" comparator', () => {
        expect(compareVersions('1.0.1', '1.0.0', '<')).toBe(false);
        expect(compareVersions('1.0.0.1', '1.0.0.0', '<')).toBe(false);
    });

    it('should return true for equal or greater version with ">=" comparator', () => {
        expect(compareVersions('1.0.1', '1.0.0', '>=')).toBe(true);
        expect(compareVersions('1.0.0', '1.0.0', '>=')).toBe(true);
        expect(compareVersions('1.0.0.1', '1.0.0.0', '>=')).toBe(true);
        expect(compareVersions('1.0.0.0', '1.0.0.0', '>=')).toBe(true);
    });

    it('should return false for lesser version with ">=" comparator', () => {
        expect(compareVersions('1.0.0', '1.0.1', '>=')).toBe(false);
        expect(compareVersions('1.0.0.0', '1.0.0.1', '>=')).toBe(false);
    });

    it('should return true for equal or lesser version with "<=" comparator', () => {
        expect(compareVersions('1.0.0', '1.0.1', '<=')).toBe(true);
        expect(compareVersions('1.0.0', '1.0.0', '<=')).toBe(true);
        expect(compareVersions('1.0.0.0', '1.0.0.1', '<=')).toBe(true);
        expect(compareVersions('1.0.0.0', '1.0.0.0', '<=')).toBe(true);
    });

    it('should return false for greater version with "<=" comparator', () => {
        expect(compareVersions('1.0.1', '1.0.0', '<=')).toBe(false);
        expect(compareVersions('1.0.0.1', '1.0.0.0', '<=')).toBe(false);
    });

    it('should handle versions with suffixes correctly', () => {
        expect(compareVersions('1.0.0.0-alpha', '1.0.0.0-beta', '<')).toBe(true);
        expect(compareVersions('1.0.0.0-beta', '1.0.0.0-alpha', '>')).toBe(true);
        expect(compareVersions('1.0.0.0-alpha', '1.0.0.0-alpha', '=')).toBe(true);
    });

    it('should consider versions without suffixes as greater', () => {
        expect(compareVersions('1.0.0.0', '1.0.0.0-alpha', '>')).toBe(true);
        expect(compareVersions('1.0.0.0-alpha', '1.0.0.0', '<')).toBe(true);
    });
});
