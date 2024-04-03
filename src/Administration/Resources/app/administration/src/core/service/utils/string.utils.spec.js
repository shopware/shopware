/**
 * @package admin
 */

import string from 'src/core/service/utils/string.utils';

describe('src/core/service/utils/string.utils.js', () => {
    it('should be true if valid CIDR is detected', async () => {
        expect(string.isValidCidr('1200:0000:AB00:1234:0000:2552:7777:1313/56')).toBe(true);
        expect(string.isValidCidr('21DA:D3:0:2F3B:2AA:FF:FE28:9C5A/128')).toBe(true);
        expect(string.isValidCidr('FE80:0000:0000:0000:0202:B3FF:FE1E:8329/128')).toBe(true);
        expect(string.isValidCidr('0.0.0.0/0')).toBe(true);
        expect(string.isValidCidr('11.0.0.0/1')).toBe(true);
        expect(string.isValidCidr('126.255.255.255/2')).toBe(true);
        expect(string.isValidCidr('129.0.0.0/3')).toBe(true);
        expect(string.isValidCidr('169.253.255.255/4')).toBe(true);
        expect(string.isValidCidr('169.255.0.0/5')).toBe(true);
        expect(string.isValidCidr('172.15.255.255/12')).toBe(true);
        expect(string.isValidCidr('172.32.0.0/10')).toBe(true);
        expect(string.isValidCidr('191.0.1.255/20')).toBe(true);
        expect(string.isValidCidr('192.88.98.255/24')).toBe(true);
        expect(string.isValidCidr('192.88.100.0/28')).toBe(true);
        expect(string.isValidCidr('192.167.255.255/31')).toBe(true);
        expect(string.isValidCidr('192.169.0.0/32')).toBe(true);
        expect(string.isValidCidr('223.255.255.255/24')).toBe(true);
    });

    it('should be false if invalid CIDR is detected', async () => {
        expect(string.isValidCidr('1200:0000:AB00:1234:O000:2552:7777:1313/64')).toBe(false); // invalid characters present
        expect(string.isValidCidr('FE80:0000:0000:0000:0202:B3FF:FE1E:8329/129')).toBe(false); // invalid, out of range subnet
        expect(string.isValidCidr('[2001:db8:0:1]:80')).toBe(false); // valid, no support for port numbers
        expect(string.isValidCidr('http://[2001:db8:0:1]:80')).toBe(false); // valid, no support for IP address in a URL
        expect(string.isValidCidr('9.255.255.255/33')).toBe(false); // out of range subnet
        expect(string.isValidCidr('198.17.255.255/-1')).toBe(false); // out of range subnet
    });
});
