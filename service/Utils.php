<?php
// Function to fetch HTML content from a URL
function fetchHTMLContent($url) {
    $html = false;
    // $options = [
    //     'http' => [
    //         'method' => 'GET',
    //         'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36'
    //     ]
    // ];

    // $context = stream_context_create($options);
    // $html = file_get_contents($url, false, $context);

    try {
        $curl_handle = curl_init();
        curl_setopt($curl_handle, CURLOPT_URL, $url);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_handle, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36');
        $html = curl_exec($curl_handle);
        curl_close($curl_handle);
    } catch (Exception $e) {
    }
    
    return $html;
}

// Function to count occurrences of a tag in HTML content
function countTagOccurrences($html, $tag) {
    // Construct the regex pattern for the tag
    $tagPattern = "/<\s*{$tag}\b[^>]*>/i";//(.*?)<\/{$tag}\s*>/i";
    preg_match_all($tagPattern, $html, $matches);

    // Count occurrences
    $count = count($matches[0]);

    return $count;
}

function isValidTagName($tagName) {
    // Regular expression pattern for validating HTML tag names
    $pattern = '/^[a-zA-Z][a-zA-Z0-9._-]*$/';

    // Check if the tag name matches the pattern
    return preg_match($pattern, $tagName);
}

function filterValue($value) {
    return strtolower(trim($value));
}

function getDomainFromUrl($url, $removeWww = true) {
    $parsedUrl = parse_url($url);
    if (isset($parsedUrl['host'])) {
        $domain = $parsedUrl['host'];
        if ($removeWww && substr($domain, 0, 4) == 'www.') {
            $domain = substr($domain, 4);
        }
        return $domain;
    }
    return false;
}
