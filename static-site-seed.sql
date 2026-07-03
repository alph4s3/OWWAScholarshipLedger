-- Master database script for OWWA Scholarship Ledger.
-- Use this file only; the older helper SQL files have been consolidated into it.
-- NOTE: "Isabela, Basilan" is treated as a single OWWA Region IX province.
-- NOTE: middle_initial column now holds full middle NAMES (e.g. "Pacquiao"),
--       not just initials. The dashboard/report displays auto-truncate to the
--       first letter; the modal shows the full name.

CREATE DATABASE IF NOT EXISTS owwa_scholarship_ledger CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE owwa_scholarship_ledger;

DROP TABLE IF EXISTS owwa_members;
DROP TABLE IF EXISTS scholars_crud;

CREATE TABLE owwa_members (
  member_id VARCHAR(20) NOT NULL PRIMARY KEY,
  last_name VARCHAR(80) NOT NULL,
  first_name VARCHAR(80) NOT NULL,
  middle_initial VARCHAR(60) DEFAULT '',
  province VARCHAR(120) NOT NULL,
  job_site VARCHAR(120) NOT NULL,
  status VARCHAR(30) NOT NULL,
  noa_date DATE NULL,
  dependents TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scholars_crud (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  scholar_id VARCHAR(32) NOT NULL UNIQUE,
  last_name VARCHAR(80) NOT NULL,
  first_name VARCHAR(80) NOT NULL,
  middle_initial VARCHAR(60) DEFAULT '',
  province VARCHAR(120) NOT NULL,
  phone VARCHAR(40) NULL,
  work_mode VARCHAR(3) NOT NULL DEFAULT 'SB',
  program VARCHAR(30) NOT NULL,
  institution VARCHAR(150) NOT NULL DEFAULT 'Not Specified',
  year_level VARCHAR(50) NOT NULL DEFAULT '1st Year',
  gwa DECIMAL(4,2) NOT NULL DEFAULT 0.00,
  status VARCHAR(30) NOT NULL DEFAULT 'Enrolled',
  life_stage VARCHAR(40) NOT NULL DEFAULT 'Student',
  birthdate DATE NULL,
  noa_date DATE NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_name (last_name, first_name),
  INDEX idx_program (program),
  INDEX idx_province (province),
  INDEX idx_work_mode (work_mode),
  INDEX idx_year (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO owwa_members (member_id, last_name, first_name, middle_initial, province, job_site, status, noa_date, dependents, created_at) VALUES
('OWWA-IX-01000','Mendoza','Maria','Dela Cruz','Zamboanga Del Norte','Saudi Arabia','Active','2024-06-26',1,'2024-02-11'),
('OWWA-IX-01001','Pascual','Ramon','Navarro','Isabela, Basilan','Singapore','Active','2025-04-13',4,'2024-01-31'),
('OWWA-IX-01002','Macapagal','Cristina','Espinosa','Zamboanga Del Sur','Japan','Active','2024-09-11',1,'2026-03-06'),
('OWWA-IX-01003','Salonga','Eduardo','Bautista','Pagadian','Singapore','Active','2025-12-16',2,'2023-03-21'),
('OWWA-IX-01004','Castillo','Liezel','Jimenez','Zamboanga Del Sur','Italy','Inactive','2024-09-23',1,'2025-05-28'),
('OWWA-IX-01005','Reyes','Antonio','Santos','Liloy','Italy','Active','2024-03-19',1,'2023-03-27'),
('OWWA-IX-01006','Lacaba','Ronnel','Velasco','Liloy','Saudi Arabia','For Renewal','2023-08-02',3,'2023-11-16'),
('OWWA-IX-01007','Macapagal','Nerissa','Lopez','Zamboanga Del Norte','Japan','Active','2026-04-20',1,'2026-01-22'),
('OWWA-IX-01008','Manalo','Aileen','Castillo','Liloy','Kuwait','Active','2024-10-20',2,'2025-05-28'),
('OWWA-IX-01009','Bautista','Jose','Naval','Liloy','Hong Kong','Inactive','2023-08-11',4,'2024-08-26'),
('OWWA-IX-01010','Bayani','Imelda','Aguilar','Zamboanga Del Sur','Saudi Arabia','Active','2025-04-03',4,'2023-11-28'),
('OWWA-IX-01011','Villanueva','Rhea','Lacson','Zamboanga Sibugay','Qatar','Active','2025-03-23',4,'2025-02-28'),
('OWWA-IX-01012','Ramos','Carlos','Mariano','Zamboanga Sibugay','Kuwait','Active','2025-05-13',4,'2023-02-12'),
('OWWA-IX-01013','Pascual','Nerissa','Javier','Zamboanga Del Norte','Hong Kong','Active','2026-03-07',4,'2023-03-28'),
('OWWA-IX-01014','Bautista','Aileen','Villanueva','Isabela, Basilan','Kuwait','Active','2024-08-22',4,'2025-08-10'),
('OWWA-IX-01015','Salonga','Mary Grace','Jacinto','Liloy','Oman','Active','2024-03-31',4,'2024-07-21'),
('OWWA-IX-01016','Lacaba','Antonio','Cruz','Pagadian','Spain','Active','2025-03-25',4,'2024-01-15'),
('OWWA-IX-01017','Sumampong','Juan','Pacquiao','Zamboanga Del Sur','Qatar','Active','2025-11-21',2,'2024-11-23'),
('OWWA-IX-01018','Reyes','Divine','Mendoza','Zamboanga City','Singapore','For Renewal','2023-11-29',2,'2024-06-11'),
('OWWA-IX-01019','Salonga','Aileen','Catacutan','Zamboanga Del Norte','Qatar','Active','2025-11-24',3,'2024-02-24'),
('OWWA-IX-01020','Villanueva','Jomar','Natividad','Zamboanga Del Norte','Bahrain','Active','2024-12-12',3,'2023-12-08'),
('OWWA-IX-01021','Manalo','Rhea','Domingo','Isabela, Basilan','Singapore','Active','2024-09-03',3,'2025-11-20'),
('OWWA-IX-01022','Mendoza','Maria','Dimaapi','Buug','Spain','For Renewal','2023-02-09',1,'2023-09-08'),
('OWWA-IX-01023','Gonzales','Renato','Ramos','Zamboanga Sibugay','Spain','Active','2025-02-23',2,'2025-12-04'),
('OWWA-IX-01024','Villanueva','Jose','Fernandez','Isabela, Basilan','Italy','Active','2024-07-27',3,'2025-05-28'),
('OWWA-IX-01025','Salonga','Liezel','Estrada','Pagadian','Kuwait','Active','2024-10-14',3,'2023-12-19'),
('OWWA-IX-01026','Dela Cruz','Jomar','Morales','Liloy','Singapore','Active','2024-03-05',4,'2025-03-11'),
('OWWA-IX-01027','Villanueva','Vicente','Reyes','Zamboanga City','Kuwait','Active','2025-07-14',3,'2025-01-14'),
('OWWA-IX-01028','Esperanza','Charmaine','Garcia','Zamboanga Del Sur','Oman','Active','2024-09-06',2,'2024-12-18'),
('OWWA-IX-01029','Tolentino','Jose','Tolentino','Zamboanga Del Norte','Japan','Active','2024-07-03',3,'2023-09-05'),
('OWWA-IX-01030','Lacaba','Lawrence','Velasquez','Buug','UAE','Active','2024-05-09',2,'2024-08-21'),
('OWWA-IX-01031','Esperanza','Carlos','Abad','Buug','Kuwait','Active','2025-05-08',4,'2025-07-22'),
('OWWA-IX-01032','Macapagal','Christian','Encarnacion','Isabela, Basilan','Italy','Active','2025-05-05',2,'2023-08-20'),
('OWWA-IX-01033','Villanueva','Imelda','Hernandez','Liloy','Japan','For Renewal','2026-01-23',1,'2023-12-09'),
('OWWA-IX-01034','Sumampong','Cristina','Andrada','Zamboanga Sibugay','Hong Kong','Active','2024-06-15',2,'2023-08-13'),
('OWWA-IX-01035','Villanueva','Cristina','Pascual','Isabela, Basilan','Italy','Active','2024-09-20',2,'2025-04-17'),
('OWWA-IX-01036','Lacaba','Nerissa','Salvador','Liloy','Bahrain','For Renewal','2023-03-23',1,'2024-01-22'),
('OWWA-IX-01037','Sumampong','Ronnel','Rivera','Zamboanga City','Hong Kong','Active','2024-05-11',2,'2025-11-24'),
('OWWA-IX-01038','Salonga','Vicente','Lazaro','Zamboanga City','UAE','Active','2025-03-06',1,'2024-09-08'),
('OWWA-IX-01039','Cruz','Juan','Ventura','Liloy','Spain','Active','2025-04-04',4,'2025-07-08'),
('OWWA-IX-01040','Sumampong','Vicente','Acosta','Liloy','Qatar','Active','2026-01-26',4,'2023-08-04'),
('OWWA-IX-01041','Tolentino','Rhea','Lim','Isabela, Basilan','Hong Kong','Active','2025-04-17',2,'2024-02-14'),
('OWWA-IX-01042','Manalo','Mary Grace','Jamora','Isabela, Basilan','Qatar','Active','2026-01-27',2,'2023-12-10'),
('OWWA-IX-01043','Estrada','Christian','Enriquez','Zamboanga Sibugay','Japan','Active','2024-01-21',3,'2025-10-04'),
('OWWA-IX-01044','Estrada','Anna','Hidalgo','Isabela, Basilan','Bahrain','Active','2024-08-05',4,'2024-04-11'),
('OWWA-IX-01045','Hernandez','Eduardo','Lorenzo','Buug','Saudi Arabia','Active','2025-10-22',4,'2024-12-17'),
('OWWA-IX-01046','Lacaba','Divine','Cabrera','Zamboanga Sibugay','UAE','Inactive','2025-08-05',2,'2023-10-25'),
('OWWA-IX-01047','Esperanza','Jomar','Bernardo','Buug','Kuwait','Active','2024-12-10',2,'2023-02-05');

INSERT INTO scholars_crud (id, scholar_id, last_name, first_name, middle_initial, province, program, institution, year_level, gwa, status, noa_date, created_at) VALUES
(2000,'SCH-IX-02000','Manalo','Vicente','Pacquiao','Buug','EDSP','Pilar College','Senior HS',1.84,'Enrolled','2026-01-11','2023-08-19'),
(2001,'SCH-IX-02001','Ramos','Imelda','Jimenez','Zamboanga Del Norte','CMWSP','Zamboanga del Norte National HS','3rd Year',1.39,'Enrolled','2025-02-18','2024-01-15'),
(2002,'SCH-IX-02002','Bayani','Angeline','Hernandez','Isabela, Basilan','SUP','Mindanao State University-IIT','3rd Year',1.63,'Enrolled','2026-01-01','2023-03-12'),
(2003,'SCH-IX-02003','Bautista','Joy','Espinosa','Zamboanga Del Sur','SESP','Andres Bonifacio College','Senior HS',1.78,'On Probation','2025-06-17','2023-05-09'),
(2004,'SCH-IX-02004','Estrada','Angeline','Mariano','Buug','ELAP-EA','Universidad de Zamboanga','Senior HS',3.38,'Enrolled','2025-02-03','2023-05-21'),
(2005,'SCH-IX-02005','Esperanza','Miguel','Bautista','Zamboanga Sibugay','ODSP','Andres Bonifacio College','3rd Year',3.36,'Enrolled','2024-02-09','2025-07-18'),
(2006,'SCH-IX-02006','Mendoza','Joy','Javier','Buug','ELAP-EA','Saint Columban College','2nd Year',3.25,'Enrolled','2024-02-04','2025-04-30'),
(2007,'SCH-IX-02007','Aguilar','Mark','Garcia','Liloy','CMWSP','Universidad de Zamboanga','Senior HS',2.29,'On Probation','2024-04-04','2024-12-25'),
(2008,'SCH-IX-02008','Macapagal','Sheila','Hidalgo','Zamboanga Del Norte','ODSP','JH Cerilles State College','1st Year',2.02,'Enrolled','2026-03-08','2023-05-23'),
(2009,'SCH-IX-02009','Bautista','Maria','Gonzales','Buug','SESP','JH Cerilles State College','3rd Year',1.66,'Enrolled','2024-04-26','2026-03-06'),
(2010,'SCH-IX-02010','Pascual','Jocelyn','Ramos','Pagadian','SESP','Zamboanga del Norte National HS','4th Year',2.74,'Enrolled','2025-01-03','2025-06-16'),
(2011,'SCH-IX-02011','Villanueva','Arnel','Honasan','Liloy','SESP','Universidad de Zamboanga','2nd Year',2.21,'Enrolled','2025-01-31','2023-06-27'),
(2012,'SCH-IX-02012','Manalo','Felix','Tolentino','Zamboanga Del Sur','SUP','Western Mindanao State University','2nd Year',1.60,'Enrolled','2026-02-16','2025-11-24'),
(2013,'SCH-IX-02013','Esperanza','Anna','Tan','Zamboanga Del Norte','EDSP','Universidad de Zamboanga','1st Year',2.43,'On Probation','2024-12-19','2024-10-20'),
(2014,'SCH-IX-02014','Estrada','Jocelyn','Bernardo','Zamboanga Del Sur','CMWSP','Zamboanga State College of Marine Sciences','2nd Year',1.64,'Graduated','2025-07-28','2025-11-02'),
(2015,'SCH-IX-02015','Macapagal','Felix','Castillo','Zamboanga City','CMWSP','Universidad de Zamboanga','4th Year',3.07,'Enrolled','2024-10-06','2024-06-29'),
(2016,'SCH-IX-02016','Lacaba','Antonio','Navarro','Zamboanga Del Sur','SESP','Universidad de Zamboanga','1st Year',1.34,'Graduated','2024-02-18','2023-01-09'),
(2017,'SCH-IX-02017','Manalo','Vicente','Herrera','Zamboanga City','CMWSP','Pilar College','Senior HS',2.60,'Enrolled','2025-04-07','2023-09-06'),
(2018,'SCH-IX-02018','Manalo','Maria','Aguilar','Zamboanga City','SUP','Zamboanga del Norte National HS','Senior HS',1.47,'Enrolled','2024-12-26','2025-05-05'),
(2019,'SCH-IX-02019','Manalo','Cristina','Bonifacio','Zamboanga Sibugay','CMWSP','Zamboanga del Norte National HS','Senior HS',1.79,'Enrolled','2024-11-19','2023-07-01'),
(2020,'SCH-IX-02020','Gonzales','Anna','Gomez','Zamboanga Sibugay','ODSP','Saint Columban College','2nd Year',2.79,'Enrolled','2024-03-01','2023-10-12'),
(2021,'SCH-IX-02021','Cruz','Rey','Lopez','Zamboanga Del Norte','SUP','Zamboanga State College of Marine Sciences','4th Year',1.41,'Enrolled','2024-06-01','2025-01-23'),
(2022,'SCH-IX-02022','Dela Cruz','Imelda','Dela Cruz','Isabela, Basilan','ELAP-EA','Universidad de Zamboanga','1st Year',1.48,'Enrolled','2024-11-04','2026-03-23'),
(2023,'SCH-IX-02023','Salonga','Joel','Balagtas','Buug','ELAP-EA','Ateneo de Zamboanga University','1st Year',2.22,'Enrolled','2024-08-26','2026-02-17'),
(2024,'SCH-IX-02024','Mendoza','Rey','Pascual','Zamboanga City','ODSP','Mindanao State University-IIT','2nd Year',2.88,'Enrolled','2025-06-07','2023-08-29'),
(2025,'SCH-IX-02025','Reyes','Vicente','Velasco','Isabela, Basilan','CMWSP','Universidad de Zamboanga','2nd Year',2.69,'Enrolled','2024-01-04','2024-06-10'),
(2026,'SCH-IX-02026','Pascual','Joy','Galang','Pagadian','ELAP-EA','Zamboanga del Norte National HS','4th Year',3.18,'Withdrawn','2024-05-06','2023-12-22'),
(2027,'SCH-IX-02027','Cruz','Carlos','Mendoza','Zamboanga City','ELAP-EA','Pilar College','3rd Year',1.29,'Enrolled','2025-08-28','2024-07-04'),
(2028,'SCH-IX-02028','Macapagal','Divine','Hernando','Buug','SESP','Zamboanga del Norte National HS','Senior HS',3.14,'Enrolled','2024-10-04','2024-11-30'),
(2029,'SCH-IX-02029','Reyes','Jomar','Guzman','Zamboanga Del Sur','CMWSP','Mindanao State University-IIT','1st Year',3.38,'Graduated','2025-08-18','2024-01-27'),
(2030,'SCH-IX-02030','Manalo','Mary Grace','Domingo','Liloy','ELAP-EA','Andres Bonifacio College','2nd Year',1.23,'Enrolled','2025-01-31','2024-03-25'),
(2031,'SCH-IX-02031','Aquino','Mary Grace','Jacinto','Zamboanga Sibugay','EDSP','Universidad de Zamboanga','2nd Year',2.82,'Enrolled','2024-09-19','2024-04-06'),
(2032,'SCH-IX-02032','Castillo','Liezel','Abad','Isabela, Basilan','SUP','Andres Bonifacio College','Senior HS',2.80,'Enrolled','2026-02-12','2023-01-16'),
(2033,'SCH-IX-02033','Macapagal','Mary Grace','Gatchalian','Zamboanga Sibugay','ODSP','Saint Columban College','4th Year',2.70,'Withdrawn','2026-02-07','2026-03-16'),
(2034,'SCH-IX-02034','Gonzales','Ronnel','Estrada','Zamboanga City','SESP','Universidad de Zamboanga','2nd Year',1.74,'Enrolled','2026-04-07','2025-11-11'),
(2035,'SCH-IX-02035','Ramos','Ronnel','Geronimo','Liloy','SESP','JH Cerilles State College','3rd Year',1.47,'Enrolled','2025-10-23','2024-04-07'),
(2036,'SCH-IX-02036','Manalo','Carlos','Encarnacion','Zamboanga Sibugay','CMWSP','JH Cerilles State College','Senior HS',3.40,'Enrolled','2026-04-29','2023-09-04'),
(2037,'SCH-IX-02037','Calingacion','Mark','Padilla','Zamboanga Del Sur','CMWSP','Zamboanga del Norte National HS','1st Year',2.19,'On Probation','2024-08-20','2026-05-03'),
(2038,'SCH-IX-02038','Sumampong','Imelda','Gregorio','Buug','ELAP-EA','Universidad de Zamboanga','Senior HS',1.66,'Enrolled','2024-09-16','2024-05-13'),
(2039,'SCH-IX-02039','Cruz','Mary Grace','Santos','Liloy','CMWSP','Ateneo de Zamboanga University','4th Year',3.34,'Enrolled','2025-10-03','2023-05-25'),
(2040,'SCH-IX-02040','Castillo','Miguel','Salvador','Liloy','ELAP-EA','Universidad de Zamboanga','3rd Year',1.87,'Enrolled','2024-08-05','2025-09-06'),
(2041,'SCH-IX-02041','Aquino','Sheila','Andrada','Zamboanga Del Norte','SESP','Zamboanga State College of Marine Sciences','4th Year',3.39,'Enrolled','2025-03-13','2023-05-03'),
(2042,'SCH-IX-02042','Mendoza','Mary Grace','Acosta','Zamboanga Sibugay','EDSP','Universidad de Zamboanga','2nd Year',2.51,'Enrolled','2024-05-22','2025-05-16'),
(2043,'SCH-IX-02043','Villanueva','Rey','Morales','Liloy','ELAP-EA','Universidad de Zamboanga','3rd Year',3.09,'Enrolled','2026-02-20','2023-11-26'),
(2044,'SCH-IX-02044','Bautista','Anna','Fernandez','Isabela, Basilan','EDSP','Pilar College','2nd Year',2.05,'Enrolled','2024-11-21','2024-07-23'),
(2045,'SCH-IX-02045','Cruz','Rosa','Torres','Zamboanga Sibugay','ODSP','Zamboanga del Norte National HS','2nd Year',3.22,'On Probation','2024-08-12','2026-03-22'),
(2046,'SCH-IX-02046','Bautista','Antonio','Aquino','Isabela, Basilan','EDSP','Zamboanga del Norte National HS','2nd Year',1.24,'Withdrawn','2024-07-05','2024-05-17'),
(2047,'SCH-IX-02047','Padilla','Cristina','Flores','Isabela, Basilan','SUP','Saint Columban College','4th Year',2.24,'Enrolled','2026-03-04','2024-08-28'),
(2048,'SCH-IX-02048','Manalo','Jocelyn','Jamora','Buug','ODSP','Zamboanga State College of Marine Sciences','2nd Year',3.16,'Enrolled','2024-01-16','2025-12-10'),
(2049,'SCH-IX-02049','Salonga','Joel','Cruz','Zamboanga Del Sur','ODSP','Universidad de Zamboanga','4th Year',3.22,'Enrolled','2024-06-20','2023-09-21'),
(2050,'SCH-IX-02050','Hernandez','Vicente','Villanueva','Zamboanga Sibugay','ODSP','Ateneo de Zamboanga University','3rd Year',1.67,'Enrolled','2024-04-12','2023-08-18'),
(2051,'SCH-IX-02051','Macapagal','Jomar','Naval','Isabela, Basilan','SESP','Mindanao State University-IIT','4th Year',2.33,'On Probation','2025-01-22','2024-07-15'),
(2052,'SCH-IX-02052','Bayani','Anna','Trinidad','Pagadian','EDSP','Universidad de Zamboanga','4th Year',2.14,'Enrolled','2026-01-06','2025-04-27'),
(2053,'SCH-IX-02053','Calingacion','Rey','Francisco','Zamboanga Del Sur','ODSP','Western Mindanao State University','1st Year',2.63,'Enrolled','2026-05-10','2024-11-07'),
(2054,'SCH-IX-02054','Manalo','Rosa','Lacson','Isabela, Basilan','ODSP','Pilar College','Senior HS',3.04,'Enrolled','2025-04-13','2025-05-21'),
(2055,'SCH-IX-02055','Mendoza','Vicente','Fajardo','Zamboanga City','ELAP-EA','Zamboanga del Norte National HS','2nd Year',2.59,'Enrolled','2024-05-21','2025-10-17'),
(2056,'SCH-IX-02056','Tolentino','Christian','Fontanilla','Zamboanga Del Norte','SESP','Mindanao State University-IIT','3rd Year',2.82,'Enrolled','2025-04-03','2024-12-13'),
(2057,'SCH-IX-02057','Cruz','Christian','Catacutan','Buug','EDSP','Andres Bonifacio College','4th Year',2.97,'Enrolled','2024-09-17','2025-02-08'),
(2058,'SCH-IX-02058','Estrada','Mark','Hizon','Isabela, Basilan','SUP','Pilar College','4th Year',2.27,'Enrolled','2024-05-06','2025-02-20'),
(2059,'SCH-IX-02059','Esperanza','Joy','Velasquez','Zamboanga Del Sur','CMWSP','Andres Bonifacio College','2nd Year',2.25,'On Probation','2025-09-28','2023-12-03'),
(2060,'SCH-IX-02060','Mendoza','Christian','Fuentes','Isabela, Basilan','SUP','Saint Columban College','1st Year',2.15,'Enrolled','2025-09-23','2023-09-14'),
(2061,'SCH-IX-02061','Padilla','Imelda','Dimaapi','Isabela, Basilan','ELAP-EA','Pilar College','Senior HS',3.49,'Enrolled','2025-11-21','2023-05-12');

-- Populate deterministic dummy birthdates for scholar records.
UPDATE scholars_crud
SET birthdate = DATE_ADD('1995-01-01', INTERVAL MOD(id * 53, 4800) DAY);

-- Populate deterministic dummy phone numbers for scholar records (PH mobile format: 11 digits starting with 09).
-- Uses a simple deterministic formula so values are stable across runs.
UPDATE scholars_crud
SET phone = CONCAT('09', LPAD(MOD(id * 7919, 1000000000), 9, '0'));

-- Defensive normalization: collapse any 'Isabela' or 'Basilan' alone into the combined label.
-- Safe to re-run; no-op once data is clean.
UPDATE owwa_members
  SET province = 'Isabela, Basilan'
  WHERE province IN ('Isabela', 'Basilan');

UPDATE scholars_crud
  SET province = 'Isabela, Basilan'
  WHERE province IN ('Isabela', 'Basilan');

UPDATE scholars_crud
SET life_stage = CASE
  WHEN status IN ('Withdrawn', 'Inactive', 'Dropped', 'Dropped Out') THEN 'Terminated'
  WHEN status IN ('Graduated', 'Graduate') THEN 'Graduated'
  WHEN status IN ('Employed', 'Postgraduate', 'Working', 'Alumni', 'Entrepreneur') THEN 'Working'
  WHEN status IN ('Board Review', 'On Probation', 'Pending', 'Under Review', 'Academic Recovery') THEN 'Student'
  WHEN status IN ('Enrolled', 'Active', 'Active Student') THEN
    CASE MOD(id, 5)
      WHEN 0 THEN 'Student'
      WHEN 1 THEN 'Working'
      WHEN 2 THEN 'Working'
      WHEN 3 THEN 'Working'
      ELSE 'Student'
    END
  ELSE 'Student'
END;

ALTER TABLE scholars_crud
  DROP COLUMN status;

SELECT COUNT(*) AS total_members FROM owwa_members;
SELECT COUNT(*) AS total_scholars FROM scholars_crud;
SELECT program, COUNT(*) AS total FROM scholars_crud GROUP BY program ORDER BY total DESC;
SELECT province, COUNT(*) AS total FROM scholars_crud GROUP BY province ORDER BY total DESC;
SELECT life_stage, COUNT(*) AS total FROM scholars_crud GROUP BY life_stage ORDER BY total DESC;