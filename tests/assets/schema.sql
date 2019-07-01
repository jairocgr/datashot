
CREATE TABLE tenants (
  id integer not null auto_increment primary key,
  handle varchar(24) not null,
  name varchar(128) not null,
  title varchar(160)
);

CREATE TABLE users (
  id integer not null auto_increment primary key,
  login varchar(24) not null,
  active boolean not null default true,
  phone varchar(32) not null,
  password varchar(40),
  tenant_id integer not null,

  CONSTRAINT fk_user_tenant FOREIGN KEY (tenant_id)
    REFERENCES tenants(id) ON UPDATE CASCADE
);

ALTER TABLE users ADD
  INDEX user_login_index (login);

CREATE TABLE logs (
  id integer not null auto_increment primary key,
  msg varchar(24) not null,
  created_at timestamp not null
);

CREATE TABLE news (
  id integer not null auto_increment primary key,
  title varchar(24) not null,
  published boolean not null default true,
  created_at timestamp not null
);

CREATE TABLE hash (
  id integer not null auto_increment primary key,
  k varchar(24) not null,
  value varchar(32)
);

DELIMITER ;;
CREATE TRIGGER users_AFTER_UPDATE AFTER UPDATE ON users FOR EACH ROW
BEGIN
  UPDATE tenants SET name = concat(name, ' (', new.login, ')')
  WHERE tenants.id=new.tenant_id;
END
;;
DELIMITER ;

INSERT INTO tenants (id, handle, name) VALUES
  (1, 'tenant01', 'Test Tenant 01 Çñ'),
  (2, 'tenant02', 'Test Tenant 02 Çñ'),
  (3, 'tenant03', 'Test Tenant 03 Çñ'),
  (4, 'tenant04', 'Test Tenant 04 Çñ');

INSERT INTO users VALUES
  (101, 'usr101', true, '+55 67 99168-2101', sha1('testpw'), 1),
  (102, 'usr102', true, '+55 67 99168-2102', sha1('testpw'), 1),
  (103, 'usr103', true, '+55 67 99168-2103', sha1('testpw'), 1),

  (201, 'usr201', false, '+55 67 99162-2201', sha1('testpw'), 2),
  (202, 'usr202', true,  '+55 67 99162-2202', sha1('testpw'), 2),
  (203, 'usr203', false, '+55 67 99162-2203', sha1('testpw'), 2),
  (204, 'usr204', true,  '+55 67 99162-2204', sha1('testpw'), 2),

  (301, 'usr301', false, '+55 67 99368-2301', sha1('testpw'), 3),
  (302, 'usr302', true,  '+55 67 99368-2302', sha1('testpw'), 3),
  (303, 'usr303', false, '+55 67 99368-2303', sha1('testpw'), 3),
  (304, 'usr304', true,  '+55 67 99368-2304', sha1('testpw'), 3);

INSERT INTO logs VALUES
  (101, 'log101', '2018-01-01 13:30:35'),
  (102, 'log102', '2018-02-03 13:30:35'),
  (103, 'log103', '2018-03-21 13:30:35'),
  (104, 'log104', '2018-04-01 13:30:35'),
  (105, 'log105', '2018-04-17 13:30:35');

INSERT INTO news VALUES
  (101, 'news 101', false, '2018-01-01 13:30:35'),
  (102, 'news 102', true,  '2018-02-03 13:30:35'),
  (103, 'news 103', true,  '2018-03-21 13:30:35'),
  (104, 'news 104', true,  '2018-04-01 13:30:35');

INSERT INTO hash VALUES
  (101, 'key101', 'val101'),
  (102, 'key102', 'val102'),
  (103, 'key103', 'val103'),
  (104, 'key104', 'val104');

create view user_log AS
  select msg, login from logs, users;

DELIMITER ;;
CREATE PROCEDURE user_count()
BEGIN
  SELECT count(*) FROM users WHERE active IS TRUE;
END;
;;
DELIMITER ;

DELIMITER ;;
CREATE FUNCTION hello (s CHAR(20))
  RETURNS CHAR(50) DETERMINISTIC
  RETURN CONCAT('Hello, ',s,'!');
;;
DELIMITER ;

DELIMITER ;;
CREATE FUNCTION hi (s CHAR(20))
RETURNS CHAR(50) DETERMINISTIC
RETURN CONCAT('Hi, ',s,'!');
;;
DELIMITER ;

/*!80016 alter user 'root'@'%' identified with mysql_native_password by 'datashot' */;