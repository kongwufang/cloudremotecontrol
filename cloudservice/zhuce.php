<?php
// 定义允许的目录路径
define('ZHUCE_DIR', __DIR__ . '/zhuce/');
define('USER_DIR', __DIR__ . '/user/');
define('SERVICE_PHP', __DIR__ . '/service.php');

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $sn = trim($_POST['sn'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    // 验证输入
    $errors = [];
    
    // 验证用户名 (4-16位数字字母)
    if (!preg_match('/^[a-zA-Z0-9]{4,16}$/', $username)) {
        $errors[] = '用户名必须是4-16位的字母或数字组合';
    }
    
    // 验证设备序列号 (1-16位，不允许符号)
    if (!preg_match('/^[a-zA-Z0-9]{1,16}$/', $sn)) {
        $errors[] = '设备序列号必须是1-16位的字母或数字组合';
    }
    
    // 验证密码 (1-16位，不允许符号)
    if (!preg_match('/^[a-zA-Z0-9]{1,16}$/', $password)) {
        $errors[] = '密码必须是1-16位的字母或数字组合';
    }
    
    // 检查service.php是否存在
    if (!file_exists(SERVICE_PHP)) {
        $errors[] = '系统错误：service.php不存在';
    }
    
    // 检查目录是否已存在
    $zhuceSnDir = ZHUCE_DIR . $sn . '/';
    if (file_exists($zhuceSnDir)) {
        $errors[] = '设备序列号已被占用，请使用其他序列号';
    }
    
    $userDir = USER_DIR . $username . '/';
    if (file_exists($userDir)) {
        $errors[] = '用户名已被占用，请选择其他用户名';
    }
    
    // 如果没有错误，处理数据
    if (empty($errors)) {
        try {
            // 创建/zhuce/sn/目录
            if (!mkdir($zhuceSnDir, 0755, true)) {
                throw new Exception('无法创建设备注册目录');
            }
            
            // 创建/user/username/目录
            if (!mkdir($userDir, 0755, true)) {
                // 如果用户目录创建失败，尝试删除已创建的设备目录
                if (file_exists($zhuceSnDir)) {
                    rmdir($zhuceSnDir);
                }
                throw new Exception('无法创建用户目录');
            }
            
            // 复制service.php到两个目录
            if (!copy(SERVICE_PHP, $zhuceSnDir . 'service.php')) {
                throw new Exception('无法复制service.php到设备目录');
            }
            
            if (!copy(SERVICE_PHP, $userDir . 'service.php')) {
                throw new Exception('无法复制service.php到用户目录');
            }
            
            // 创建/zhuce/sn/service.txt并写入数据
            $zhuceServiceFile = $zhuceSnDir . 'service.txt';
            $zhuceContent = $username . PHP_EOL . hash('sha256', $password);
            if (file_put_contents($zhuceServiceFile, $zhuceContent) === false) {
                throw new Exception('无法写入设备服务文件');
            }
            
            // 创建/user/username/sn.txt
            $userSnFile = $userDir . 'sn.txt';
            if (file_put_contents($userSnFile, $sn) === false) {
                throw new Exception('无法写入用户SN文件');
            }
            
            // 创建/user/username/password.txt
            $userPasswordFile = $userDir . 'password.txt';
            $doubleHashedPassword = hash('sha256', hash('sha256', $password));
            if (file_put_contents($userPasswordFile, $doubleHashedPassword) === false) {
                throw new Exception('无法写入用户密码文件');
            }
            
            $success = '注册成功！所有文件已创建。';
        } catch (Exception $e) {
            // 清理可能已创建的部分目录和文件
            if (file_exists($zhuceSnDir)) {
                array_map('unlink', glob($zhuceSnDir . "*"));
                rmdir($zhuceSnDir);
            }
            if (file_exists($userDir)) {
                array_map('unlink', glob($userDir . "*"));
                rmdir($userDir);
            }
            $errors[] = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户注册</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --error-color: #f72585;
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
        }
        
        body {
            font-family: 'Poppins', sans-serif;
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
            max-width: 500px;
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
        }
        
        .header h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .content {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
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
        
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            border-radius: var(--border-radius);
            padding: 12px 20px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            width: 100%;
            text-align: center;
        }
        
        .btn:hover {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }
        
        .btn:active {
            transform: translateY(0);
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
        
        @media (max-width: 576px) {
            .content {
                padding: 20px;
            }
            
            .header {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>用户注册</h1>
            <p>创建您的账户</p>
        </div>
        
        <div class="content">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <p><?php echo htmlspecialchars($success); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="post">
                <div class="form-group">
                    <label for="username">用户名</label>
                    <div class="input-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" id="username" name="username" class="form-control" 
                               placeholder="请输入4-16位的字母或数字" required 
                               pattern="[a-zA-Z0-9]{4,16}">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="sn">设备序列号</label>
                    <div class="input-icon">
                        <i class="fas fa-laptop"></i>
                        <input type="text" id="sn" name="sn" class="form-control" 
                               placeholder="请输入1-16位的字母或数字" required 
                               pattern="[a-zA-Z0-9]{1,16}">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">密码</label>
                    <div class="input-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" class="form-control" 
                               placeholder="请输入1-16位的字母或数字" required 
                               pattern="[a-zA-Z0-9]{1,16}">
                    </div>
                </div>
                
                <button type="submit" class="btn">注册</button>
            </form>
        </div>
    </div>
    
    <!-- Font Awesome for icons -->
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>