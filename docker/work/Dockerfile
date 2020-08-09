FROM ubuntu:20.04

RUN apt-get update -qq

RUN apt-get install -qq mysql-client
RUN DEBIAN_FRONTEND=noninteractive apt-get install -qq php7.4
RUN apt-get install -qq php7.4-bcmath
RUN apt-get install -qq php7.4-bz2
RUN apt-get install -qq php7.4-cgi
RUN apt-get install -qq php7.4-cli
RUN apt-get install -qq php7.4-common
RUN apt-get install -qq php7.4-curl
RUN apt-get install -qq php7.4-dba
RUN apt-get install -qq php7.4-dev
RUN apt-get install -qq php7.4-enchant
RUN apt-get install -qq php7.4-gd
RUN apt-get install -qq php7.4-gmp
RUN apt-get install -qq php7.4-imap
RUN apt-get install -qq php7.4-intl
RUN apt-get install -qq php7.4-json
RUN apt-get install -qq php7.4-ldap
RUN apt-get install -qq php7.4-mbstring
RUN apt-get install -qq php7.4-mysql
RUN apt-get install -qq php7.4-odbc
RUN apt-get install -qq php7.4-opcache
RUN apt-get install -qq php7.4-pgsql
RUN apt-get install -qq php7.4-pspell
RUN apt-get install -qq php7.4-readline
RUN apt-get install -qq php7.4-snmp
RUN apt-get install -qq php7.4-soap
RUN apt-get install -qq php7.4-sqlite3
RUN apt-get install -qq php7.4-sybase
RUN apt-get install -qq php7.4-tidy
RUN apt-get install -qq php7.4-xml
RUN apt-get install -qq php7.4-xmlrpc
RUN apt-get install -qq php7.4-xsl
RUN apt-get install -qq php7.4-zip
RUN apt-get install -qq php-pear
RUN pecl install xdebug

RUN echo "zend_extension=/usr/lib/php/20190902/xdebug.so" > /etc/php/7.4/mods-available/xdebug.ini
RUN echo "xdebug.remote_enable=1" >> /etc/php/7.4/mods-available/xdebug.ini
RUN echo "xdebug.remote_autostart=1" >> /etc/php/7.4/mods-available/xdebug.ini
RUN echo "xdebug.remote_host=host.docker.internal" >> /etc/php/7.4/mods-available/xdebug.ini

RUN phpenmod xdebug

WORKDIR /datashot
