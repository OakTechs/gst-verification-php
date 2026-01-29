<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Session storage (in production, use a database)
$gstSessions = [];

// Load sessions from file
$sessionFile = __DIR__ . '/sessions.json';
if (file_exists($sessionFile)) {
    $gstSessions = json_decode(file_get_contents($sessionFile), true) ?? [];
}

/**
 * Save sessions to file
 */
function saveSessions($sessions) {
    global $sessionFile;
    file_put_contents($sessionFile, json_encode($sessions, JSON_PRETTY_PRINT));
}

/**
 * Clean up expired sessions (older than 10 minutes)
 */
function cleanupExpiredSessions(&$sessions) {
    $currentTime = time();
    $sessionsToDelete = [];
    
    foreach ($sessions as $sessionId => $sessionData) {
        if ($currentTime - ($sessionData['created_at'] ?? 0) > 600) { // 600 seconds = 10 minutes
            $sessionsToDelete[] = $sessionId;
        }
    }
    
    foreach ($sessionsToDelete as $sessionId) {
        // remove any cookie file associated with the session
        if (!empty($sessions[$sessionId]['cookieFile']) && file_exists($sessions[$sessionId]['cookieFile'])) {
            @unlink($sessions[$sessionId]['cookieFile']);
        }
        unset($sessions[$sessionId]);
    }
    
    return count($sessionsToDelete);
}

/**
 * Get the API endpoint path
 */
function getEndpoint() {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    return trim($uri, '/');
}

/**
 * Send JSON response
 */
function sendJson($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

// Route handling
$endpoint = getEndpoint();
$queryEndpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : null;
$method = $_SERVER['REQUEST_METHOD'];

// Use query parameter if available, otherwise use URL path
$routeEndpoint = $queryEndpoint ?? $endpoint;

try {
    if ($routeEndpoint === 'getCaptcha' && $method === 'GET') {
        handleGetCaptcha($gstSessions);
    } elseif ($routeEndpoint === 'getGSTDetails' && $method === 'POST') {
        handleGetGSTDetails($gstSessions);
    } elseif ($routeEndpoint === 'getGSTDetailsBatch' && $method === 'POST') {
        handleGetGSTDetailsBatch($gstSessions);
    } elseif ($routeEndpoint === 'getGSTDetailsSimple' && $method === 'POST') {
        handleGetGSTDetailsSimple();
    } elseif ($endpoint === 'api/v1/getCaptcha' && $method === 'GET') {
        handleGetCaptcha($gstSessions);
    } elseif ($endpoint === 'api/v1/getGSTDetails' && $method === 'POST') {
        handleGetGSTDetails($gstSessions);
    } elseif ($endpoint === 'api/v1/getGSTDetailsBatch' && $method === 'POST') {
        handleGetGSTDetailsBatch($gstSessions);
    } elseif ($endpoint === 'api/v1/getGSTDetailsSimple' && $method === 'POST') {
        handleGetGSTDetailsSimple();
    } else {
        sendJson(['error' => 'Endpoint not found'], 404);
    }
} catch (Exception $e) {
    error_log('Exception: ' . $e->getMessage());
    sendJson(['error' => 'Internal server error'], 500);
}

/**
 * Handle GET /api/v1/getCaptcha
 */
function handleGetCaptcha(&$sessions) {
    global $sessionFile;
    
    try {
        $sessionId = bin2hex(random_bytes(16)); // Generate unique session ID
        // Create a cookie file for this session so server session is preserved
        $cookieFile = sys_get_temp_dir() . '/gst_cookies_' . $sessionId . '.txt';

        // Use cURL to fetch CAPTCHA from GST website
        $ch = curl_init();
        
        // First, get the search page to establish session and store cookies
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://services.gst.gov.in/services/searchtp',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_COOKIEJAR => $cookieFile,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ]);
        curl_exec($ch);
        
        // Get CAPTCHA image using the same cookie jar
        curl_setopt($ch, CURLOPT_URL, 'https://services.gst.gov.in/services/captcha');
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        $captchaResponse = curl_exec($ch);
        curl_close($ch);
        
        if (!$captchaResponse) {
            throw new Exception('Failed to fetch CAPTCHA');
        }
        
        // Encode captcha to base64
        $captchaBase64 = base64_encode($captchaResponse);
        
        // Store session with timestamp and cookieFile path
        $sessions[$sessionId] = [
            'created_at' => time(),
            'cookieFile' => $cookieFile
        ];
        
        // Save sessions to file
        file_put_contents($sessionFile, json_encode($sessions, JSON_PRETTY_PRINT));
        
        $response = [
            'sessionId' => $sessionId,
            'image' => 'data:image/png;base64,' . $captchaBase64
        ];
        
        sendJson($response, 200);
        
    } catch (Exception $e) {
        error_log('Error in getCaptcha: ' . $e->getMessage());
        sendJson(['error' => 'Error in fetching captcha'], 500);
    }
}

/**
 * Handle POST /api/v1/getGSTDetails
 */
function handleGetGSTDetails(&$sessions) {
    global $sessionFile;
    
    // Clean up expired sessions
    cleanupExpiredSessions($sessions);
    
    // Read raw input and parse JSON
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (!$input) {
        sendJson(['error' => 'No JSON data provided'], 400);
    }

    $sessionId = $input['sessionId'] ?? null;
    $gstin = $input['GSTIN'] ?? null;
    $captcha = $input['captcha'] ?? null;
    
    if (!$sessionId || !$gstin || !$captcha) {
        sendJson(['error' => 'sessionId, GSTIN, and captcha are required'], 400);
    }
    
    if (!isset($sessions[$sessionId])) {
        sendJson(['error' => 'Invalid session id'], 400);
    }
    
    try {
        // Reuse the cookie file from getCaptcha - the server session was established there
        // DO NOT make another searchtp request as it can invalidate the session
        $cookieFile = $sessions[$sessionId]['cookieFile'] ?? (sys_get_temp_dir() . '/gst_cookies_' . $sessionId . '.txt');
        
        $postData = json_encode([
            'gstin' => $gstin,
            'captcha' => $captcha
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://services.gst.gov.in/services/api/search/taxpayerDetails',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: */*',
                'Accept-Encoding: gzip, deflate, br',
                'Referer: https://services.gst.gov.in/services/searchtp',
                'X-Requested-With: XMLHttpRequest',
                'Origin: https://services.gst.gov.in',
                'Connection: keep-alive',
                'Expect:'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_COOKIEJAR => $cookieFile,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ]);
        
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        $httpCode = $info['http_code'] ?? null;
        $curlError = curl_error($ch);
        curl_close($ch);

        // Keep cookie file until session cleanup to preserve server session for subsequent requests

        if (!$response && $curlError) {
            throw new Exception('cURL Error: ' . $curlError);
        }

        if (!$response) {
            throw new Exception('Failed to get GST details - Empty response');
        }

        // If response appears to be HTML (Tomcat error), save full response to a file for inspection
        if (strpos(trim($response), '<') === 0) {
            $dumpFile = __DIR__ . '/debug_response_' . $sessionId . '_' . time() . '.html';
            @file_put_contents($dumpFile, $response);
            sendJson(['error' => 'GST API returned an unexpected HTML response, which usually indicates a server-side error. The request entity format may be unsupported.', 'debugFile' => basename($dumpFile), 'httpCode' => $httpCode], 502);
        }
        
        // Update session timestamp
        $sessions[$sessionId]['created_at'] = time();
        file_put_contents($sessionFile, json_encode($sessions, JSON_PRETTY_PRINT));
        
        // Return the response from GST API
        $responseData = json_decode($response, true);

        // If response contains error, still return it (SWEB_9000 is a valid GST API error)
        if ($responseData !== null) {
            sendJson($responseData, 200);
        } else {
            sendJson(['error' => 'Invalid JSON response from GST API', 'statusCode' => $httpCode], $httpCode);
        }
        
    } catch (Exception $e) {
        error_log('Error in getGSTDetails: ' . $e->getMessage());
        sendJson(['error' => 'Error in fetching GST Details: ' . $e->getMessage()], 500);
    }
}

/**
 * Handle POST /api/v1/getGSTDetailsBatch
 */
function handleGetGSTDetailsBatch(&$sessions) {
    global $sessionFile;
    
    // Clean up expired sessions
    cleanupExpiredSessions($sessions);
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendJson(['error' => 'No JSON data provided'], 400);
    }
    
    $sessionId = $input['sessionId'] ?? null;
    $gstins = $input['GSTINS'] ?? null;
    $captcha = $input['captcha'] ?? null;
    
    if (!$sessionId || !$gstins || !$captcha) {
        sendJson(['error' => 'sessionId, GSTINS (array), and captcha are required'], 400);
    }
    
    if (!is_array($gstins)) {
        sendJson(['error' => 'GSTINS must be an array of GST numbers'], 400);
    }
    
    if (!isset($sessions[$sessionId])) {
        sendJson(['error' => 'Invalid session id'], 400);
    }
    
    $results = [];
    
    try {
        foreach ($gstins as $gstin) {
            try {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => 'https://services.gst.gov.in/services/api/search/taxpayerDetails',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode([
                        'gstin' => $gstin,
                        'captcha' => $captcha
                    ]),
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json'
                    ],
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                ]);
                
                $response = curl_exec($ch);
                curl_close($ch);
                
                if ($response) {
                    $results[$gstin] = json_decode($response, true);
                } else {
                    $results[$gstin] = ['error' => 'Failed to fetch data'];
                }
            } catch (Exception $e) {
                $results[$gstin] = ['error' => 'Error processing GSTIN: ' . $e->getMessage()];
            }
        }
        
        // Update session timestamp
        $sessions[$sessionId]['created_at'] = time();
        file_put_contents($sessionFile, json_encode($sessions, JSON_PRETTY_PRINT));
        
        sendJson($results, 200);
        
    } catch (Exception $e) {
        error_log('Error in getGSTDetailsBatch: ' . $e->getMessage());
        sendJson(['error' => 'Error in fetching GST Details'], 500);
    }
}

/**
 * Handle POST /api/v1/getGSTDetailsSimple
 */
function handleGetGSTDetailsSimple() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendJson(['error' => 'No JSON data provided'], 400);
    }
    
    $gstin = $input['GSTIN'] ?? null;
    
    if (!$gstin) {
        sendJson(['error' => 'GSTIN is required'], 400);
    }
    
    // Automatic CAPTCHA solving is not possible due to security measures
    sendJson([
        'error' => 'Automatic CAPTCHA solving is not possible due to security measures. Please use the standard API workflow with manual CAPTCHA entry.'
    ], 400);
}
?>
