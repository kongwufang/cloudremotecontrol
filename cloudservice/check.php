<?php
// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 定义用户目录路径
$userDir = __DIR__ . '/user/';

// 获取所有用户文件夹
function getAllUsers($dir) {
    $users = [];
    if (is_dir($dir)) {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item != '.' && $item != '..' && is_dir($dir . $item)) {
                $users[] = $item;
            }
        }
    }
    return $users;
}

// 获取设备SN
function getDeviceSN($username, $userDir) {
    $snFile = $userDir . $username . '/sn.txt';
    if (file_exists($snFile)) {
        return trim(file_get_contents($snFile));
    }
    return 'N/A';
}

// 获取最后上报时间并检查是否在线
function checkOnlineStatus($username, $userDir) {
    $logFile = $userDir . $username . '/service_sh_access.log';
    if (!file_exists($logFile)) {
        return ['status' => false, 'last_seen' => '从未上报'];
    }
    
    // 获取文件最后修改时间
    $lastModified = filemtime($logFile);
    $currentTime = time();
    $diff = $currentTime - $lastModified;
    
    $lastSeen = date('Y-m-d H:i:s', $lastModified);
    
    return [
        'status' => ($diff < 200),
        'last_seen' => $lastSeen,
        'seconds_ago' => $diff
    ];
}

// 主处理逻辑
$users = getAllUsers($userDir);
$devices = [];

foreach ($users as $username) {
    $sn = getDeviceSN($username, $userDir);
    $statusInfo = checkOnlineStatus($username, $userDir);
    
    $devices[] = [
        'username' => $username,
        'sn' => $sn,
        'is_online' => $statusInfo['status'],
        'last_seen' => $statusInfo['last_seen'],
        'seconds_ago' => $statusInfo['seconds_ago']
    ];
}

// 按在线状态排序 - 在线设备排前面
usort($devices, function($a, $b) {
    if ($a['is_online'] == $b['is_online']) {
        return $a['seconds_ago'] - $b['seconds_ago'];
    }
    return $a['is_online'] ? -1 : 1;
});
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>设备在线状态监控</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        h1 {
            color: #333;
            text-align: center;
        }
        .status-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .status-table th, .status-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .status-table th {
            background-color: #f2f2f2;
        }
        .status-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .online {
            color: green;
            font-weight: bold;
        }
        .offline {
            color: red;
            font-weight: bold;
        }
        .last-seen {
            font-size: 0.9em;
            color: #666;
        }
        .timestamp {
            font-family: monospace;
        }
    </style>
</head>
<body>
    <h1>设备在线状态监控</h1>
    <p>最后更新时间: <?php echo date('Y-m-d H:i:s'); ?></p>
    
    <table class="status-table">
        <thead>
            <tr>
                <th>用户名</th>
                <th>设备SN</th>
                <th>状态</th>
                <th>最后上报时间</th>
                <th>时间差(秒)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($devices as $device): ?>
            <tr>
                <td><?php echo htmlspecialchars($device['username']); ?></td>
                <td><?php echo htmlspecialchars($device['sn']); ?></td>
                <td class="<?php echo $device['is_online'] ? 'online' : 'offline'; ?>">
                    <?php echo $device['is_online'] ? '在线' : '离线'; ?>
                </td>
                <td>
                    <span class="timestamp"><?php echo $device['last_seen']; ?></span>
                </td>
                <td><?php echo $device['seconds_ago']; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>