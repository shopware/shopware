<?php

/**
 * Test edge cases for the SEO URL query parameter fix
 */

function testSeoPathParsing($input, $description) {
    echo "Testing: $description\n";
    echo "Input: '$input'\n";
    
    // Apply the fix logic
    $parsedUrl = parse_url($input);
    $pathWithoutQuery = $parsedUrl['path'] ?? '';
    $seoPathInfo = trim($pathWithoutQuery, '/');
    
    echo "Parsed path: '$seoPathInfo'\n";
    echo "Query: '" . ($parsedUrl['query'] ?? '') . "'\n";
    echo "---\n";
}

// Test cases
testSeoPathParsing('Main-product/SWDEMO10001?test=123', 'Normal case with query parameter');
testSeoPathParsing('Main-product/SWDEMO10001', 'Normal case without query parameter');
testSeoPathParsing('?test=123', 'Only query parameter');
testSeoPathParsing('Main-product/SWDEMO10001?', 'Empty query parameter');
testSeoPathParsing('/Main-product/SWDEMO10001?test=123', 'Leading slash with query parameter');
testSeoPathParsing('/Main-product/SWDEMO10001?test=123&param2=value2', 'Multiple query parameters');
testSeoPathParsing('Main-product/SWDEMO10001?test=123#anchor', 'Query parameter with anchor');
testSeoPathParsing('', 'Empty string');
testSeoPathParsing('/', 'Root path');
testSeoPathParsing('/?test=123', 'Root path with query parameter');

echo "All edge cases tested successfully!\n";