
DROP DATABASE IF EXISTS datashot;
CREATE DATABASE datashot;

USE datashot;

CREATE TABLE tenants (
  id integer not null auto_increment primary key,
  handle varchar(24) not null,
  name varchar(128) not null
);

CREATE TABLE users (
  id integer not null auto_increment primary key,
  login varchar(24) not null,
  active boolean not null default true,
  tenant_id integer REFERENCES tenants(id) ON UPDATE CASCADE
);

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

DELIMITER ;;
CREATE TRIGGER users_AFTER_UPDATE AFTER UPDATE ON users FOR EACH ROW
BEGIN
  UPDATE users SET login=new.login WHERE id=new.id;
END
;;
DELIMITER ;

INSERT INTO tenants VALUES
  (1, 'tenant01', 'Test Tenant 01'),
  (2, 'tenant02', 'Test Tenant 02'),
  (3, 'tenant03', 'Test Tenant 03'),
  (4, 'tenant04', 'Test Tenant 04');

INSERT INTO users VALUES
  (101, 'usr101', true, 1),
  (102, 'usr102', true, 1),
  (103, 'usr103', true, 1),

  (201, 'usr201', false, 2),
  (202, 'usr202', true, 2),
  (203, 'usr203', false, 2),
  (204, 'usr204', true, 2),

  (301, 'usr301', false, 3),
  (302, 'usr302', true, 3),
  (303, 'usr303', false, 3),
  (304, 'usr304', true, 3);

INSERT INTO logs VALUES
  (101, 'log101', '2018-01-01 13:30:35'),
  (102, 'log102', '2018-02-03 13:30:35'),
  (103, 'log103', '2018-03-21 13:30:35'),
  (104, 'log104', '2018-04-01 13:30:35');

INSERT INTO news VALUES
  (101, 'news 101', false, '2018-01-01 13:30:35'),
  (102, 'news 102', true,  '2018-02-03 13:30:35'),
  (103, 'news 103', true,  '2018-03-21 13:30:35'),
  (104, 'news 104', true,  '2018-04-01 13:30:35');

create view datashot.user_log AS
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
