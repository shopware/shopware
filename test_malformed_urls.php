<?php

/**
 * Test malformed URL handling
 */

$testCases = [
    'Main-product/SWDEMO10001?test=123', // Normal case
    'Main-product/SWDEMO10001?test=123?malformed', // Malformed (double question mark)
    'Main-product/SWDEMO10001?test=123&param=value', // Normal with multiple params
    'Main-product/SWDEMO10001?test=123&param=value&', // Trailing ampersand
    'Main-product/SWDEMO10001?test=123#anchor#malformed', // Malformed anchor
    '://malformed-url', // Very malformed
    '', // Empty
];

function testMalformedUrlHandling($input) {
    echo "Testing: '$input'\n";
    
    $parsedUrl = parse_url($input);
    if ($parsedUrl === false) {
        echo "  parse_url failed, fallback to original: '$input'\n";
        $pathWithoutQuery = $input;
    } else {
        $pathWithoutQuery = $parsedUrl['path'] ?? '';
        echo "  parse_url succeeded, path: '$pathWithoutQuery'\n";
    }
    
    $seoPathInfo = trim($pathWithoutQuery, '/');
    echo "  Final seo path: '$seoPathInfo'\n";
    echo "---\n";
}

foreach ($testCases as $testCase) {
    testMalformedUrlHandling($testCase);
}

echo "All malformed URL tests completed!\n";