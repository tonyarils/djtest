FROM php:8.4-apache

ENV ORACLE_INSTANTCLIENT_DIR=/opt/oracle/instantclient_21_21

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    unzip libaio1t64 libzip-dev zlib1g-dev zip git curl build-essential libaio-dev libjpeg62-turbo-dev libssl-dev \
    libpng-dev libfreetype6-dev wget libzip-dev git libpq-dev default-libmysqlclient-dev \
    libxml2 libxml2-dev apache2 \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd \
    && docker-php-ext-install zip \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql mysqli pgsql\
    && a2enmod rewrite
RUN ln -s /lib/x86_64-linux-gnu/libaio.so.1t64 /lib/x86_64-linux-gnu/libaio.so.1
# Place Oracle Instant Client zip files in ./docker/ as:
# - instantclient-basiclite.zip
# - instantclient-sdk.zip
COPY docker/instantclient-basiclite.zip /tmp/instantclient-basiclite.zip
COPY docker/instantclient-sdk.zip /tmp/instantclient-sdk.zip

RUN mkdir -p /opt/oracle \
    && if [ -f /tmp/instantclient-basiclite.zip ]; then unzip /tmp/instantclient-basiclite.zip -d /opt/oracle ; fi \
    && if [ -f /tmp/instantclient-sdk.zip ]; then unzip /tmp/instantclient-sdk.zip -d /opt/oracle ; fi \
    && if [ -d /opt/oracle/instantclient_21_21 ]; then ln -s /opt/oracle/instantclient_21_21 /opt/oracle/instantclient; fi \
    && ldconfig || true

ENV LD_LIBRARY_PATH=/opt/oracle/instantclient:$ORACLE_INSTANTCLIENT_DIR
ENV PATH=$PATH:/opt/oracle/instantclient:$ORACLE_INSTANTCLIENT_DIR:$ORACLE_INSTANTCLIENT_DIR/bin:.
# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install oci8 when instantclient exists
RUN if [ -d /opt/oracle/instantclient ] || [ -d /opt/oracle/instantclient_21_21 ]; then \
      if [ -d /opt/oracle/instantclient_21_21 ]; then IC_DIR=/opt/oracle/instantclient_21_21; else IC_DIR=/opt/oracle/instantclient_21_21; fi && \
      printf "instantclient,%s\n" "$IC_DIR" | pecl install oci8 && docker-php-ext-enable oci8 ; \
    else echo "Oracle Instant Client not provided; oci8 not installed."; fi

# OceanBase 兼容 MySQL，只要 pdo_mysql 即可
# Kingbase 兼容 PostgreSQL，只要 pdo_pgsql 即可

# 达梦 dm 需安装 dm 客户端 + pdo_dm 扩展 (依赖 dm 的 Linux 客户端库)
# 这里假设你已经把 dm 的 rpm/so 拷贝到 build 目录
# 如果没有正式 driver，注释以下两行
COPY dmclient /opt/dmclient
RUN ln -s /opt/dmclient/php84_pdo_dm.so /usr/local/lib/php/extensions/no-debug-non-zts-20240924/php84_pdo_dm.so || true
RUN ln -s /opt/dmclient/libphp84_dm.so /usr/local/lib/php/extensions/no-debug-non-zts-20240924/libphp84_dm.so || true
ENV DM_HOME=/opt/dmclient/dm8
ENV PATH=$PATH:$DM_HOME/bin
ENV LD_LIBRARY_PATH=$LD_LIBRARY_PATH:$DM_HOME/bin
# 编译 pecl pdo_dm 如果可用
COPY kdbclient /opt/kdbclient
RUN ln -s /opt/kdbclient/pdo_kdb.so /usr/local/lib/php/extensions/no-debug-non-zts-20240924/php84_pdo_kdb.so || true
RUN ln -s /opt/kdbclient/libkci.so.5 /usr/local/lib/php/extensions/no-debug-non-zts-20240924/libkci.so.5 || true
# 编译 pecl pdo_kdb 如果可用
# httpd 配置：允许 .htaccess 等
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
#RUN echo "extension=pdo_mysql" > /usr/local/etc/php/conf.d/30-pdo_mysql.ini
#RUN echo "extension=pdo_pgsql" > /usr/local/etc/php/conf.d/30-pdo_pgsql.ini
RUN echo "extension=php84_pdo_dm.so" > /usr/local/etc/php/conf.d/30-pdo_dm.ini
RUN echo "extension=libphp84_dm.so" > /usr/local/etc/php/conf.d/30-dm.ini
RUN echo "extension=php84_pdo_kdb.so" > /usr/local/etc/php/conf.d/30-pdo_kdb.ini

# Copy application files
COPY . /var/www/html/
WORKDIR /var/www/html

# Install PHP deps
RUN composer install --no-dev --optimize-autoloader || true

EXPOSE 80

CMD ["apache2-foreground"]
