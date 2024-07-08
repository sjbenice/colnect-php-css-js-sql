<?php
/**
 * POST request for counting tags in a given URL.
 * 
 * Endpoint: /api/v1/count-tags.php
 * 
 * @param {string} url - The URL for which tags are to be counted.
 * @param {string} tag - The specific tag to be counted in the URL.
 * @param {boolean} statistics flag - Flag to specify if statistics should be returned along with count.
 * 
 * @returns {result, ?statistics, ?error} Response object containing the count of the specified tag in the URL.
 * 
 * NOTE:
 * Adjust CACHE_TIME, CACHE_RET_COUNT, CACHE_HTML_MB for purpose.
 */
// --------------------------------------------
define("CACHE_TIME", 5 * 60);// 5 minutes
define("CACHE_RET_COUNT", 100);
define("CACHE_HTML_MB", 100);
// --------------------------------------------

require_once './../../service/MemoryCache.php';
require_once './../../service/Utils.php';
require_once './../../service/MySQLConnectionPool.php';

define("CACHE_RET", "CACHE_RET");// Creates a global constant that can be accessed from any part.
define("CACHE_HTML", "CACHE_HTML");
define("CONN_POOL", "CONN_POOL");

// header("Content-Type: application/json; charset=UTF-8");
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
// Specify allowed origin
// $allowedOrigins = ["https://example.com", "https://another-example.com"];
// if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins)) {
//     header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
// }

// Allow specific HTTP methods
// header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Methods: POST, OPTIONS");

// Allow specific headers
// header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("HTTP/1.1 200 OK");
    exit();
}

// Function to save result to database
function saveResultToDB($url, $domain, $tag, $count, $time) {
    $err = false;

    $pool = MySQLConnectionPool::getInstance(10);
    if ($pool) {
        $conn = $pool->getConnection();
        if ($conn) {
            if ($conn->connect_error) {
                $err = $conn->connect_error;
            } else {
                $stmt = $conn->prepare("CALL create_log(?, ?, ?, ?, ?)");
                try {
                    $stmt->bind_param("sssii", $url, $domain, $tag, $time, $count);
                
                    // Execute the statement
                    if ($stmt->execute()) {
                    } else {
                        $err = $stmt->error;
                    }
                } catch (Exception $e) {
                    $err = $e->getMessage();
                }
                    
                // Close statement and connection
                $stmt->close();
            }

            $pool->releaseConnection($conn);
        }
    }

    return $err;
}

// Function to get statistics data
function getStatisticsData($domain, $tag) {
    $statisticsData = false;

    $pool = MySQLConnectionPool::getInstance(10);
    if ($pool) {
        $conn = $pool->getConnection();
        if ($conn) {
            if ($conn->connect_error) {
                $statisticsData = $conn->connect_error;
            } else {
                $stmt = $conn->prepare("CALL get_stats(?, ?, ?)");
                try {
                    $hours = 24;
                    $stmt->bind_param("ssi", $domain, $tag, $hours);
                
                    // Execute the statement
                    if ($stmt->execute()) {
                        // Fetch the results
                        $result = $stmt->get_result();
                        if ($result->num_rows > 0) {
                            $row = $result->fetch_assoc();
                            $o_different = $row['different_urls'];
                            $o_average = $row['average_time'];
                            $o_sub_count = $row['sub_count'];
                            $o_total_count = $row['total_count'];

                            $statisticsData = "{$o_different} different URLs from {$domain} have been fetched.<br>
                                Average fetch time from {$domain} during the last {$hours} hours is {$o_average}ms.<br>
                                There was a total of {$o_sub_count} &lt;{$tag}&gt; elements from {$domain}.<br>
                                Total of {$o_total_count} &lt;{$tag}&gt; elements counted in all requests ever made.";
                        } else {
                            $statisticsData = "No results found.";
                        }
                    } else {
                        $statisticsData = $stmt->error;
                    }
                } catch (Exception $e) {
                    $statisticsData = $e->getMessage();
                }
                    
                // Close statement and connection
                $stmt->close();
            }

            $pool->releaseConnection($conn);
        }
    }

    return $statisticsData;
}

function getCacheFromSession($key, $expirable, $itemCountLimit, $totalMemoryLimit) {
    if (!isset($_SESSION[$key]))
        $_SESSION[$key] = new MemoryCache($expirable, $itemCountLimit, $totalMemoryLimit);// Create a single instance of MemoryCache

    return $_SESSION[$key];
}

// Store the cache object in a session or global variable for reuse across requests
session_start();

$cache_ret = getCacheFromSession(CACHE_RET, true, CACHE_RET_COUNT, 0);
$cache_html = getCacheFromSession(CACHE_HTML, true, 0, CACHE_HTML_MB);

$ret = [];
$err = false;

// Main API endpoint handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve POST data
    $requestData = json_decode(file_get_contents('php://input'), true);// JSON objects will be returned as associative arrays
    // $url = filter_input(INPUT_POST, 'url', FILTER_SANITIZE_URL);
    // $tag = filter_input(INPUT_POST, 'tag', FILTER_SANITIZE_STRING);
    // $statistics = filter_input(INPUT_POST, 'statistics', FILTER_VALIDATE_BOOLEAN);

    $valid = false;
    $url = false;
    $tag = false;
    $statistics = false;

    if ($requestData && isset($requestData['url']) && isset($requestData['tag'])) {
        // Validate input parameters
        $url = filter_var(filterValue($requestData['url']), FILTER_VALIDATE_URL);
        $tag = filterValue($requestData['tag']);
        $statistics = isset($requestData['statistics']) ? (bool) $requestData['statistics'] : false;

        if ($url && isValidTagName($tag)) {
            $valid = true;
        }
    }

    if (!$valid) {
        http_response_code(400);
        $err = "Invalid URL or HTML tag.";
    } else {
        $cache_key = "$url $tag";
        $result = false;

        $domain = getDomainFromUrl($url);

        // Check if the same request was made
        if ($cache_ret) {
            $result = $cache_ret->get($cache_key);// Use not allowed character in URL and tag
        }
    
        if ($result) {
        } else {
            // Fetch HTML content from URL
            try {
                $fetchTime = 0;
                $html = $cache_html->get($url);
                if (!$html) {
                    $startTime = microtime(true);
                    $html = fetchHTMLContent($url);
                    $endTime = microtime(true);
                    $fetchTime = round(($endTime - $startTime) * 1000); // in milliseconds

                    if ($html === false)
                        throw new Exception("Inaccessible URL.");
                    else if (!preg_match('/<\s*html.*>/i', $html))
                        throw new Exception("Invalid HTML content.");

                    $cache_html->set($url, $html, CACHE_TIME);
                }

                // Count occurrences of the tag in HTML content
                $tagCount = countTagOccurrences($html, $tag);
        
                // Save result to database
                $err = saveResultToDB($url, $domain, $tag, $tagCount, $fetchTime);
        
                // Prepare result response
                $result = "URL {$url} fetched on " . date('d/m/Y H:i') . ", took {$fetchTime}ms.<br>";
                $result .= "Element &lt;{$tag}&gt; appeared {$tagCount} times in page.";
        
                $cache_ret->set($cache_key, $result, CACHE_TIME);
            } catch (Exception $e) {
                http_response_code(500);
                $err = $e->getMessage();
            }
        }

        if ($result) {
            $ret["result"] = $result;

            // Prepare statistics data if requested
            if ($statistics)
                $ret["statistics"] = getStatisticsData($domain, $tag);
        }
    }
} else {
    http_response_code(405);
    $err = "Method Not Allowed.";
}

if ($err)
    $ret["error"] = $err;

echo json_encode($ret);
