#!/bin/bash

curl -o php-8.1.23.tar.gz https://www.php.net/distributions/php-8.1.23.tar.gz
tar -xzf php-8.1.23.tar.gz
cd php-8.1.23/ext/pcntl && /usr/local/bin/phpize
./configure--with-php-config=/usr/local/bin/php-config
make && make install

echo 'ok'