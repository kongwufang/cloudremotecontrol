<?php
session_start();

// 定义用户目录
define('USER_DIR', __DIR__ . '/user/');

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 处理登录
    if (isset($_POST['login'])) {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        
        if (empty($username) || empty($password)) {
            $error = "用户名和密码不能为空";
        } else {
            $passwordFile = USER_DIR . $username . '/password.txt';
            if (!file_exists($passwordFile)) {
                $error = "用户不存在";
            } else {
                $storedHash = trim(file_get_contents($passwordFile));
                $inputHash = hash('sha256', hash('sha256', $password));
                
                if ($inputHash === $storedHash) {
                    $_SESSION['username'] = $username;
                    $_SESSION['password_hash'] = hash('sha256', $password);
                    // 检查是否是开发用户
                    $_SESSION['is_developer'] = file_exists(USER_DIR . $username . '/developallow.txt');
                    // 登录成功，刷新页面
                    header("Location: ".$_SERVER['PHP_SELF']);
                    exit();
                } else {
                    $error = "密码错误";
                }
            }
        }
    }
    // 处理控制台操作
    elseif (isset($_POST['action'])) {
        if (!isset($_SESSION['username'])) {
            die("请先登录");
        }
        
        $username = $_SESSION['username'];
        $userDir = USER_DIR . $username . '/';
        
        if ($_POST['action'] === 'reset') {
            // 复位操作 - 保留关键文件
            $preservedFiles = ['service.php', 'sn.txt', 'password.txt', 'developallow.txt'];
            $files = glob($userDir . '*');
            foreach ($files as $file) {
                if (!in_array(basename($file), $preservedFiles)) {
                    unlink($file);
                }
            }
            $_SESSION['message'] = "系统已复位（保留关键文件）";
        } 
        elseif ($_POST['action'] === 'submit') {
            // 处理命令
            $commands = [
                'reboot' => 'reboot',
                'reboot_recovery' => 'reboot recovery',
                'reboot_fastboot' => 'reboot bootloader',
                'factory_reset' => 'setprop'
            ];
            
            $command = '';
            
            if ($_SESSION['is_developer'] && isset($_POST['custom_command']) && !empty(trim($_POST['custom_command']))) {
                // 开发用户使用自定义命令 - 处理多行命令
                $inputCommands = explode("\n", $_POST['custom_command']);
                $filteredCommands = array_map('trim', $inputCommands);
                $filteredCommands = array_filter($filteredCommands); // 移除空行
                $command = implode("\n", $filteredCommands); // 保留换行符
            } else {
                // 普通用户使用预设命令
                if (!isset($_POST['command']) || !isset($commands[$_POST['command']])) {
                    die("无效的命令");
                }
                $command = $commands[$_POST['command']];
            }
            
            // 创建 service.txt（重复执行标志固定为 true）
            $content = "60\n";
            $content .= "true\n"; // 固定为 true
            $content .= $_SESSION['password_hash'] . "\n";
            $content .= "*\n";
            $content .= $command;
            
            file_put_contents($userDir . 'service.txt', $content);
            
            // 通过 allowrunagain.txt 控制是否允许重复执行
            $repeatFile = $userDir . 'allowrunagain.txt';
            if (isset($_POST['repeat'])) {
                touch($repeatFile); // 允许重复执行
            } elseif (file_exists($repeatFile)) {
                unlink($repeatFile); // 禁止重复执行
            }
            
            $_SESSION['message'] = "命令已提交: ".htmlspecialchars(
                $_SESSION['is_developer'] ? "多行命令" : $command
            );
        }
        
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    }
}

// 处理退出登录
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>设备远程控制台</title>
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --error-color: #f72585;
            --warning-color: #f8961e;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --gray-color: #6c757d;
            --border-radius: 8px;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: var(--dark-color);
            line-height: 1.6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            width: 100%;
            max-width: 800px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 25px;
            text-align: center;
            position: relative;
        }
        
        .header h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .user-type {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: rgba(255, 255, 255, 0.2);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .developer-type {
            background-color: var(--warning-color);
        }
        
        .normal-type {
            background-color: var(--success-color);
        }
        
        .content {
            padding: 30px;
        }
        
        .panel {
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 25px;
            background: white;
            box-shadow: var(--box-shadow);
        }
        
        .panel h2 {
            margin-bottom: 15px;
            color: var(--primary-color);
            font-size: 18px;
            font-weight: 600;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .status-item {
            display: flex;
            margin-bottom: 10px;
        }
        
        .status-label {
            font-weight: 500;
            width: 120px;
            color: var(--gray-color);
        }
        
        .status-value {
            flex: 1;
        }
        
        .online {
            color: var(--success-color);
            font-weight: 500;
        }
        
        .offline {
            color: var(--error-color);
            font-weight: 500;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
        }
        
        select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-family: inherit;
            font-size: 14px;
            transition: var(--transition);
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 1em;
        }
        
        select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-family: monospace;
            font-size: 14px;
            min-height: 120px;
            resize: vertical;
            white-space: pre;
            overflow-x: auto;
        }
        
        textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            margin: 15px 0;
        }
        
        .checkbox-group input {
            margin-right: 10px;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 20px;
            border: none;
            border-radius: var(--border-radius);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            flex: 1;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #f72585, #b5179e);
            color: white;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #b5179e, #f72585);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }
        
        .btn-logout {
            background: var(--gray-color);
            color: white;
        }
        
        .btn-logout:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
            font-size: 14px;
        }
        
        .alert-error {
            background-color: rgba(247, 37, 133, 0.1);
            border-left: 4px solid var(--error-color);
            color: var(--error-color);
        }
        
        .alert-success {
            background-color: rgba(76, 201, 240, 0.1);
            border-left: 4px solid var(--success-color);
            color: var(--success-color);
        }
        
        .login-form {
            max-width: 400px;
            margin: 0 auto;
        }
        
        .input-icon {
            position: relative;
        }
        
        .input-icon i {
            position: absolute;
            top: 50%;
            left: 15px;
            transform: translateY(-50%);
            color: var(--gray-color);
        }
        
        .input-icon input {
            padding-left: 40px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-family: inherit;
            font-size: 14px;
            transition: var(--transition);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        @media (max-width: 576px) {
            .content {
                padding: 20px;
            }
            
            .header {
                padding: 20px;
            }
            
            .btn-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!isset($_SESSION['username'])): ?>
            <!-- 登录表单 -->
            <div class="header">
                <h1>设备远程控制台</h1>
                <p>请登录您的账户</p>
            </div>
            
            <div class="content">
                <?php if (isset($error)): ?>
                    <div class="alert alert-error">
                        <p><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>
                
                <div class="panel login-form">
                    <form method="post">
                        <div class="form-group">
                            <label for="username">用户名</label>
                            <div class="input-icon">
                                <input type="text" id="username" name="username" class="form-control" placeholder="请输入用户名" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">密码</label>
                            <div class="input-icon">
                                <input type="password" id="password" name="password" class="form-control" placeholder="请输入密码" required>
                            </div>
                        </div>
                        
                        <button type="submit" name="login" class="btn btn-primary">登录</button>
                    </form>
                </div>
            </div>

        <?php else: ?>
            <!-- 控制台面板 -->
            <?php
            $username = $_SESSION['username'];
            $userDir = USER_DIR . $username . '/';
            $isDeveloper = $_SESSION['is_developer'] ?? false;
            
            // 获取设备信息
            $sn = '未设置';
            $snFile = $userDir . 'sn.txt';
            if (file_exists($snFile)) {
                $sn = trim(file_get_contents($snFile));
            }
            
            // 检查在线状态
            $status = '离线';
            $statusClass = 'offline';
            $lastReportTime = '从未上报';
            $logFile = $userDir . 'service_sh_access.log';
            if (file_exists($logFile)) {
                $modTime = filemtime($logFile);
                $lastReportTime = date('Y-m-d H:i:s', $modTime);
                if (time() - $modTime < 200) {
                    $status = '在线';
                    $statusClass = 'online';
                }
            }
            ?>
            
            <div class="header">
                <div class="user-type <?php echo $isDeveloper ? 'developer-type' : 'normal-type'; ?>">
                    <?php echo $isDeveloper ? '开发者用户' : '普通用户'; ?>
                </div>
                <h1>设备远程控制台</h1>
                <p>欢迎回来，<?php echo htmlspecialchars($username); ?></p>
            </div>
            
            <div class="content">
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-success">
                        <p><?php echo htmlspecialchars($_SESSION['message']); ?></p>
                    </div>
                    <?php unset($_SESSION['message']); ?>
                <?php endif; ?>
                
                <div class="panel">
                    <h2>设备状态</h2>
                    <div class="status-item">
                        <div class="status-label">用户类型</div>
                        <div class="status-value"><?php echo $isDeveloper ? '<span style="color: var(--warning-color);">开发者用户</span>' : '<span style="color: var(--success-color);">普通用户</span>'; ?></div>
                    </div>
                    <div class="status-item">
                        <div class="status-label">序列号</div>
                        <div class="status-value"><?php echo htmlspecialchars($sn); ?></div>
                    </div>
                    <div class="status-item">
                        <div class="status-label">状态</div>
                        <div class="status-value <?php echo $statusClass; ?>"><?php echo $status; ?></div>
                    </div>
                    <div class="status-item">
                        <div class="status-label">最后上报时间</div>
                        <div class="status-value"><?php echo htmlspecialchars($lastReportTime); ?></div>
                    </div>
                </div>
                
                <div class="panel">
                    <h2>设备控制</h2>
                    <form method="post" id="commandForm">
                        <?php if (!$isDeveloper): ?>
                            <div class="form-group">
                                <label for="command">选择操作</label>
                                <select id="command" name="command" size="4" required>
                                    <option value="reboot">重启设备</option>
                                    <option value="reboot_recovery">重启到Recovery模式</option>
                                    <option value="reboot_fastboot">重启到Fastboot模式</option>
                                    <option value="factory_reset">恢复出厂设置</option>
                                </select>
                            </div>
                        <?php else: ?>
                            <div class="form-group">
                                <label for="custom_command">自定义命令</label>
                                <textarea 
                                    id="custom_command" 
                                    name="custom_command" 
                                    class="form-control" 
                                    placeholder="输入多行命令，每行一个命令..."
                                    required
                                ></textarea>
                                <p style="font-size: 12px; color: var(--gray-color); margin-top: 5px;">
                                    开发者权限允许执行多行命令，请谨慎操作<br>
                                    每行将作为一个单独命令依次执行
                                </p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="repeat" name="repeat">
                            <label for="repeat">重复执行此命令</label>
                        </div>
                        
                        <div class="btn-group">
                            <button type="submit" name="action" value="submit" class="btn btn-primary">执行命令</button>
                            <button type="button" onclick="confirmReset()" class="btn btn-danger">复位系统</button>
                            <a href="?logout" class="btn btn-logout">退出登录</a>
                        </div>
                    </form>
                </div>
            </div>

            <script>
            function confirmReset() {
                if(confirm('确定要复位系统吗？这将清除所有配置（保留关键文件）！')) {
                    // 创建隐藏表单提交复位请求
                    let form = document.createElement('form');
                    form.method = 'post';
                    form.action = '';
                    
                    let input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'action';
                    input.value = 'reset';
                    
                    form.appendChild(input);
                    document.body.appendChild(form);
                    form.submit();
                }
            }
            </script>
        <?php endif; ?>
    </div>
</body>
</html>