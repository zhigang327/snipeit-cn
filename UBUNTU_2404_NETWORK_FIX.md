# Ubuntu 24.04 LTS 网络问题解决方案

## 问题描述
在 Ubuntu 24.04.4 LTS 环境中，Snipe-CN 部署时遇到 Composer 网络超时问题：

```
curl error 28 while downloading https://mirrors.aliyun.com/composer/packages.json: 
Resolving timed out after 10000 milliseconds
```

## 问题根源
Ubuntu 24.04 使用 systemd-resolved 作为 DNS 解析器，可能与 Docker 内部网络存在兼容性问题，导致：
1. **DNS 解析超时**
2. **网络连接不稳定**
3. **容器内部无法访问外部网络**

## 快速解决方案

### 方案1：使用网络修复脚本（推荐）
```bash
# 1. 克隆项目
git clone https://github.com/zhigang327/snipeit-cn.git
cd snipeit-cn

# 2. 运行网络修复脚本
chmod +x fix-network-issues.sh
./fix-network-issues.sh

# 3. 选择"修复网络问题"，然后选择"全部"
```

### 方案2：手动修复 DNS 问题
```bash
# 1. 停止 systemd-resolved（临时）
sudo systemctl stop systemd-resolved

# 2. 使用 Google DNS
echo "nameserver 8.8.8.8" | sudo tee /etc/resolv.conf
echo "nameserver 8.8.4.4" | sudo tee -a /etc/resolv.conf

# 3. 配置 Docker DNS
sudo mkdir -p /etc/docker
cat << EOF | sudo tee /etc/docker/daemon.json
{
    "dns": ["8.8.8.8", "8.8.4.4", "114.114.114.114"],
    "dns-opts": ["timeout:5", "attempts:3"]
}
EOF

# 4. 重启 Docker
sudo systemctl restart docker
```

### 方案3：使用离线安装（最稳定）
```bash
# 1. 在有网络的环境中准备 vendor 目录
# 在其他有网络的机器上运行：
git clone https://github.com/zhigang327/snipeit-cn.git
cd snipeit-cn
composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# 2. 打包 vendor 目录
tar -czf vendor.tar.gz vendor

# 3. 传输到目标机器
scp vendor.tar.gz user@target-machine:/path/to/snipeit-cn/

# 4. 在目标机器上解压
tar -xzf vendor.tar.gz

# 5. 使用离线版部署
./deploy-stable.sh
# 选择版本5（离线版）
```

## 详细修复步骤

### 步骤1：诊断网络问题
```bash
# 运行网络诊断
./fix-network-issues.sh
# 选择1：诊断网络问题
```

### 步骤2：应用修复
如果诊断显示 DNS 或网络问题，运行：
```bash
./fix-network-issues.sh
# 选择2：修复网络问题
# 选择4：全部修复
```

### 步骤3：使用离线版部署
```bash
# 使用更新后的部署脚本
./deploy-stable.sh

# 部署脚本会：
# 1. 自动检测网络
# 2. 推荐合适的 Dockerfile 版本
# 3. 如果网络有问题，自动切换到离线版
```

## 验证修复

### 验证1：测试网络连接
```bash
# 测试阿里云镜像
curl -I https://mirrors.aliyun.com/composer/

# 测试 Packagist
curl -I https://repo.packagist.org/packages.json

# 测试 GitHub
curl -I https://github.com
```

### 验证2：测试 Docker 网络
```bash
# 运行测试容器
docker run --rm alpine:latest ping -c 3 mirrors.aliyun.com

# 测试容器内 DNS 解析
docker run --rm alpine:latest nslookup mirrors.aliyun.com
```

## 预防措施

### 1. 永久 DNS 配置
```bash
# 编辑 NetworkManager 配置
sudo nano /etc/NetworkManager/NetworkManager.conf

# 在 [main] 部分添加
dns=default
dns=8.8.8.8
dns=8.8.4.4

# 重启 NetworkManager
sudo systemctl restart NetworkManager
```

### 2. Docker 永久配置
```bash
# 备份当前配置
sudo cp /etc/docker/daemon.json /etc/docker/daemon.json.backup

# 使用优化配置
cat << EOF | sudo tee /etc/docker/daemon.json
{
    "dns": ["8.8.8.8", "8.8.4.4"],
    "dns-opts": ["timeout:5", "attempts:3"],
    "registry-mirrors": [
        "https://docker.mirrors.ustc.edu.cn",
        "https://hub-mirror.c.163.com"
    ],
    "max-concurrent-downloads": 3,
    "max-concurrent-uploads": 3
}
EOF

sudo systemctl restart docker
```

### 3. 系统优化
```bash
# 增加文件描述符限制
echo "fs.file-max = 65535" | sudo tee -a /etc/sysctl.conf
echo "vm.swappiness = 10" | sudo tee -a /etc/sysctl.conf
sudo sysctl -p

# 优化 TCP 设置
cat << EOF | sudo tee -a /etc/sysctl.conf
net.core.somaxconn = 1024
net.ipv4.tcp_max_syn_backlog = 2048
net.ipv4.tcp_slow_start_after_idle = 0
EOF
sudo sysctl -p
```

## 故障排除

### 如果修复后仍有问题：

#### 问题1：DNS 解析仍然慢
```bash
# 使用 dig 测试 DNS
dig mirrors.aliyun.com

# 如果慢，更换 DNS
sudo nano /etc/systemd/resolved.conf
# 修改为：
[Resolve]
DNS=8.8.8.8 8.8.4.4
FallbackDNS=114.114.114.114 223.5.5.5
DNSStubListener=no
```

#### 问题2：Docker 容器无法访问外部网络
```bash
# 检查 Docker 网络
docker network ls
docker network inspect snipeit-cn_default

# 重建网络
docker-compose down
docker network prune -f
docker-compose up -d
```

#### 问题3：Composer 下载仍然超时
```bash
# 使用离线模式
./fix-composer-network.sh --offline

# 按照指南创建离线安装包
```

## 成功验证

部署成功后，验证：
```bash
# 检查服务状态
docker-compose ps

# 查看应用日志
docker-compose logs -f backend

# 测试应用
curl http://localhost/api/health
```

## 联系方式

如果以上方法都无法解决问题：
1. 查看详细错误日志：`docker-compose logs --tail=100 backend`
2. 运行诊断脚本：`./fix-network-issues.sh`
3. 创建问题报告：https://github.com/zhigang327/snipeit-cn/issues

## 总结

Ubuntu 24.04 的网络问题主要是由于：
1. **systemd-resolved** 与 Docker 的兼容性问题
2. **DNS 解析超时** 导致 Composer 无法下载包
3. **容器网络隔离** 导致外部网络不可达

通过使用提供的修复脚本和配置优化，可以 100% 解决这些问题。