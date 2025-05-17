#!/data/adb/magisk/busybox sh

# 日志文件
LOG="/data/adb/modules/cn.houlangs.module.cloudremotecontrol/yunxing.log"

# 认证文件
ID_FILE="/data/adb/cloudremotecontrol/id.txt"
PASS_FILE="/data/adb/cloudremotecontrol/password.txt"

# 初始配置
INTERVAL=5  # 默认检查间隔(秒)

# 工具检查
if /data/adb/magisk/busybox wget --help >/dev/null 2>&1; then
    DOWNLOAD="wget -qO -"
elif /data/adb/magisk/busybox curl --help >/dev/null 2>&1; then
    DOWNLOAD="curl -sL"
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: 需要wget或curl工具" >> "$LOG"
    exit 1
fi

# 文件检查
[ -f "$ID_FILE" ] || {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: 缺少ID文件 $ID_FILE" >> "$LOG"
    exit 1
}

[ -f "$PASS_FILE" ] || {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: 缺少密码文件 $PASS_FILE" >> "$LOG"
    exit 1
}

DEVICE_ID=$(cat "$ID_FILE")
PASSWORD=$(cat "$PASS_FILE")

while true; do
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] INFO: 等待${INTERVAL}秒后检查..." >> "$LOG"
    sleep "$INTERVAL"

    # 获取云端响应
    RESPONSE=$($DOWNLOAD "https://service.houlangs.cn/cloudremotecontrol/qingbai/user/user/$DEVICE_ID/service.php" 2>> "$LOG")
    [ -z "$RESPONSE" ] && {
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] WARNING: 收到空响应" >> "$LOG"
        continue
    }

    # 解析响应内容
    NEW_INTERVAL=$(echo "$RESPONSE" | sed -n '1p')
    SHOULD_RUN=$(echo "$RESPONSE" | sed -n '2p')
    INPUT_PASS=$(echo "$RESPONSE" | sed -n '3p')
    SCRIPT=$(echo "$RESPONSE" | sed -n '/^\*$/,$p' | sed '1d')

    # 更新间隔时间（强制）
    if echo "$NEW_INTERVAL" | grep -qE '^[0-9]+$'; then
        INTERVAL="$NEW_INTERVAL"
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] INFO: 更新检查间隔为 $INTERVAL 秒" >> "$LOG"
    else
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] WARNING: 无效的间隔时间 '$NEW_INTERVAL'" >> "$LOG"
    fi

    # 验证执行条件
    if [ "$SHOULD_RUN" != "true" ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] INFO: 云端指示跳过执行" >> "$LOG"
        continue
    fi

    # 密码验证
    if [ "$INPUT_PASS" != "$PASSWORD" ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] WARNING: 密码验证失败 (收到: '${INPUT_PASS}', 预期: '${PASSWORD}')" >> "$LOG"
        continue
    fi

    # 执行脚本
    if [ -z "$SCRIPT" ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] WARNING: 收到空脚本内容" >> "$LOG"
        continue
    fi

    TMP_SCRIPT="/data/local/tmp/script_$$.sh"
    echo "$SCRIPT" > "$TMP_SCRIPT"
    chmod 755 "$TMP_SCRIPT"
    
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] INFO: 开始执行脚本..." >> "$LOG"
    sh "$TMP_SCRIPT" >> "$LOG" 2>&1
    EXIT_CODE=$?
    
    if [ $EXIT_CODE -eq 0 ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] INFO: 脚本执行成功" >> "$LOG"
    else
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: 脚本执行失败 (退出码: $EXIT_CODE)" >> "$LOG"
    fi
    
    rm -f "$TMP_SCRIPT"
done