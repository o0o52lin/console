环境要求：
系统要求Linux且安装有PHP-CLI,使用PHP 5.5以上版本
PHP扩展要求：pcntl、posix、PDO、PDO_MySQL、bcmath、Stomp(用于发站内信)，soap(一站互联WebService)，建议安装libevent扩展但不是必须

进入定时任务目录
启动
以debug（调试）方式启动
/path/to/bin/php start.php start

以daemon（守护进程）方式启动
/path/to/bin/php start.php start -d

停止
/path/to/bin/php start.php stop

重启
/path/to/bin/php start.php restart

平滑重启
/path/to/bin/php start.php reload

查看状态
/path/to/bin/php start.php status

配置项：
├── Applications
│  ├── Business
│  │  ├── Config
│  │  │  ├── Db.php           业务处理数据配置（主库）
│  │  │  ├── Hulianpay.php    一站互联配置
│  │  │  ├── Stomp.php        发送站内信配置
│  │  │  └── Gateway.php
│  └── Timer
│      ├── Config
│      │  ├── Db.php          定时任务触发数据库配置（建议连接从库）
│      │  ├── Gateway.php
│      │  └── Timer.php       定时任务模块触发周期配置
├── readme.txt
└── start.php                 启动入口文件
