<?php
// 日志文件路径（与脚本同目录）
define('LOG_FILE', __DIR__ . '/service_sh_access.log');
// 日志文件大小限制（2MB）
define('LOG_FILE_SIZE_LIMIT', 2 * 1024 * 1024);

/**
 * 获取客户端IP（支持CDN）
 */
function getClientIP() {
    $ipKeys = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];
    
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                // 验证IP格式
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
    }
    
    return 'unknown';
}

/**
 * 检查并清理过大的日志文件
 */
function checkAndCleanLog() {
    if (file_exists(LOG_FILE)) {
        // 获取日志文件大小
        $logSize = filesize(LOG_FILE);
        
        // 如果超过限制大小，删除日志文件
        if ($logSize > LOG_FILE_SIZE_LIMIT) {
            unlink(LOG_FILE);
            return true;
        }
    }
    return false;
}

/**
 * 记录日志
 */
function logAccess($message, $filePath = null) {
    // 先检查日志文件大小
    $logCleaned = checkAndCleanLog();
    
    $timestamp = date('Y-m-d H:i:s');
    $clientIP = getClientIP();
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
    
    $logMessage = "[{$timestamp}] [{$clientIP}] [{$requestMethod}] {$message}";
    if ($filePath) {
        $logMessage .= " | File: {$filePath}";
    }
    $logMessage .= PHP_EOL;
    
    // 如果日志刚被清理，添加一条说明
    if ($logCleaned) {
        $cleanedMessage = "[{$timestamp}] [SYSTEM] Log file exceeded size limit and was cleaned" . PHP_EOL;
        file_put_contents(LOG_FILE, $cleanedMessage, FILE_APPEND);
    }
    
    // 写入日志文件（追加模式）
    file_put_contents(LOG_FILE, $logMessage, FILE_APPEND);
}

/**
 * 查找service.txt文件
 * 返回数组：['path' => 文件路径, 'in_current_dir' => 是否在当前目录]
 */
function findServiceSh() {
    $filename = 'service.txt';
    $currentDir = __DIR__;
    
    // 检查当前目录
    $currentPath = $currentDir . DIRECTORY_SEPARATOR . $filename;
    if (file_exists($currentPath)) {
        logAccess("Found service.txt in current directory", $currentPath);
        return ['path' => $currentPath, 'in_current_dir' => true];
    }
    
    // 检查上级目录
    $parentDir = dirname($currentDir);
    $parentPath = $parentDir . DIRECTORY_SEPARATOR . $filename;
    if (file_exists($parentPath)) {
        logAccess("Found service.txt in parent directory", $parentPath);
        return ['path' => $parentPath, 'in_current_dir' => false];
    }
    
    logAccess("service.txt not found in current or parent directory");
    return ['path' => false, 'in_current_dir' => false];
}

// 记录请求开始
logAccess("Request started");

try {
    // 检查是否允许再次运行
    $allowRunAgain = file_exists(__DIR__ . '/allowrunagain.txt');
    logAccess("Allow run again: " . ($allowRunAgain ? "YES" : "NO"));

    $serviceShInfo = findServiceSh();
    $serviceShPath = $serviceShInfo['path'];
    $isInCurrentDir = $serviceShInfo['in_current_dir'];

    if ($serviceShPath) {
        // 设置正确的Content-Type
        header('Content-Type: text/plain');
        
        // 记录文件大小
        $fileSize = filesize($serviceShPath);
        logAccess("Serving service.txt file (Size: {$fileSize} bytes)");
        
        // 读取并输出文件内容
        readfile($serviceShPath);
        
        // 如果没有allowrunagain.txt且文件在当前目录，则删除当前目录的service.txt
        if (!$allowRunAgain && $isInCurrentDir) {
            if (unlink($serviceShPath)) {
                logAccess("Deleted service.txt from current directory");
            } else {
                logAccess("Failed to delete service.txt from current directory");
            }
        }
    } else {
        // 文件不存在，返回404
        header('HTTP/1.0 404 Not Found');
        logAccess("service.txt not found - returning 404");
        echo 'service.txt not found in current or parent directory';
    }
} catch (Exception $e) {
    // 记录异常
    logAccess("Error: " . $e->getMessage());
    header('HTTP/1.0 500 Internal Server Error');
    echo 'An error occurred while processing your request';
}

// 记录请求完成
logAccess("Request completed");
?>