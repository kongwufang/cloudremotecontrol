#!/data/adb/magisk/busybox sh

# 获取设备SN
SN=$(getprop ro.serialno) || {
    echo "无法获取设备序列号"
    exit 1
}

echo "设备序列号: $SN"

# 创建目标目录
mkdir -p /data/adb/cloudremotecontrol || {
    echo "无法创建目录"
    exit 1
}

# 请求URL
URL="https://service.houlangs.cn/cloudremotecontrol/qingbai/user/zhuce/${SN}/service.php"

# 使用wget获取数据并直接处理
if ! /data/adb/magisk/busybox wget -qO- "$URL" > /data/adb/cloudremotecontrol/response.txt 2>/dev/null; then
    echo "网络请求失败或你的注册信息无效/未注册"
    exit 1
fi

# 检查文件是否为空
if [ ! -s /data/adb/cloudremotecontrol/response.txt ]; then
    echo "获取到的响应为空"
    exit 1
fi

# 直接提取前两行到目标文件
head -n 1 /data/adb/cloudremotecontrol/response.txt > /data/adb/cloudremotecontrol/id.txt
head -n 2 /data/adb/cloudremotecontrol/response.txt | tail -n 1 > /data/adb/cloudremotecontrol/password.txt

# 清理临时文件
rm -f /data/adb/cloudremotecontrol/response.txt

echo "操作成功完成"
echo "ID已写入: /data/adb/cloudremotecontrol/id.txt"
echo "密码已写入: /data/adb/cloudremotecontrol/password.txt"
