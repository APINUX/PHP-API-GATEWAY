FROM php:7.2-cli

RUN apt-get update && apt-get install vim -y && \
    apt-get install openssl -y && \
    apt-get install libssl-dev -y && \
    apt-get install wget -y && \
    apt-get install git -y && \
    apt-get install procps -y && \
    apt-get install htop -y && \
    apt-get install ca-certificates -y && \
    apt-get install gnupg -y && \
    apt-get install libpq-dev -y

RUN docker-php-ext-install pdo && docker-php-ext-enable pdo

ENV ACCEPT_EULA=Y

# Microsoft SQL Server Prerequisites
RUN curl https://packages.microsoft.com/keys/microsoft.asc | apt-key add - \
    && curl https://packages.microsoft.com/config/debian/9/prod.list \
        > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get install -y --no-install-recommends \
        locales \
        apt-transport-https \
    && echo "en_US.UTF-8 UTF-8" > /etc/locale.gen \
    && locale-gen \
    && apt-get update \
    && apt-get -y --no-install-recommends install \
        unixodbc-dev \
        msodbcsql17
#MSSQL
RUN docker-php-ext-install mbstring pdo \
    && pecl install sqlsrv pdo_sqlsrv xdebug \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv xdebug

#ORACLE
# RUN mkdir /opt/oracle \
#     && cd /opt/oracle \
#     && wget https://s3.amazonaws.com/merofile/instantclient-basic-linux.x64-12.1.0.2.0.zip \
#     && wget https://s3.amazonaws.com/merofile/instantclient-sdk-linux.x64-12.1.0.2.0.zip \
#     && unzip /opt/oracle/instantclient-basic-linux.x64-12.1.0.2.0.zip -d /opt/oracle \
#     && unzip /opt/oracle/instantclient-sdk-linux.x64-12.1.0.2.0.zip -d /opt/oracle \
#     && ln -s /opt/oracle/instantclient_12_1/libclntsh.so.12.1 /opt/oracle/instantclient_12_1/libclntsh.so \
#     && ln -s /opt/oracle/instantclient_12_1/libclntshcore.so.12.1 /opt/oracle/instantclient_12_1/libclntshcore.so \
#     && ln -s /opt/oracle/instantclient_12_1/libocci.so.12.1 /opt/oracle/instantclient_12_1/libocci.so \
#     && rm -rf /opt/oracle/*.zip

# Install Oracle extensions
# RUN docker-php-ext-configure pdo_oci --with-pdo-oci=instantclient,/opt/oracle/instantclient_12_1,12.1 \
#         && echo 'instantclient,/opt/oracle/instantclient_12_1/' | pecl install oci8 \
#         && docker-php-ext-install \
#                 pdo_oci \
#         && docker-php-ext-enable \
#                 oci8

#POSTGRESQL
RUN docker-php-ext-install pdo_pgsql && docker-php-ext-enable pdo_pgsql

#MYSQL
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli
RUN docker-php-ext-install pdo_mysql && docker-php-ext-enable pdo_mysql

#REDIS
RUN pecl install -o -f redis \
    &&  rm -rf /tmp/pear \
    &&  docker-php-ext-enable redis

RUN cd /tmp && git clone https://github.com/swoole/swoole-src.git && \
    cd swoole-src && \
    git checkout v4.4.15 && \
    phpize  && \
    ./configure  --enable-openssl && \
    make && make install

RUN touch /usr/local/etc/php/conf.d/swoole.ini && \
    echo 'extension=swoole.so' > /usr/local/etc/php/conf.d/swoole.ini

RUN wget -O /usr/local/bin/dumb-init https://github.com/Yelp/dumb-init/releases/download/v1.2.2/dumb-init_1.2.2_amd64
RUN chmod +x /usr/local/bin/dumb-init

RUN apt-get autoremove -y && rm -rf /var/lib/apt/lists/*

ENV TZ=Asia/Jakarta
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

ENTRYPOINT ["/usr/local/bin/dumb-init", "--", "php"]