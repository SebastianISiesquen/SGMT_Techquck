-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: sgmt_techquk
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `archivos`
--

DROP TABLE IF EXISTS `archivos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `archivos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `archivable_type` varchar(255) NOT NULL,
  `archivable_id` bigint(20) unsigned NOT NULL,
  `categoria` varchar(30) NOT NULL,
  `nombre_original` varchar(255) NOT NULL,
  `ruta` varchar(255) NOT NULL,
  `mime` varchar(120) NOT NULL,
  `tamano` bigint(20) unsigned NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `subido_por` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `archivos_archivable_type_archivable_id_index` (`archivable_type`,`archivable_id`),
  KEY `archivos_subido_por_foreign` (`subido_por`),
  CONSTRAINT `archivos_subido_por_foreign` FOREIGN KEY (`subido_por`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `archivos`
--

LOCK TABLES `archivos` WRITE;
/*!40000 ALTER TABLE `archivos` DISABLE KEYS */;
INSERT INTO `archivos` VALUES (1,'atencion',1,'foto','camaraiop.webp','archivos/atencion/2026/10/7afec451-eb4d-42dc-8cae-8c8bf925dac8.webp','image/webp',16440,NULL,4,'2026-10-12 00:23:09','2026-10-12 00:23:09'),(2,'hoja_servicio',1,'foto','camaraiop.webp','archivos/hoja_servicio/2026/10/9ea81ed5-2f01-4884-ba60-fc10e3dca74d.webp','image/webp',16440,NULL,4,'2026-10-12 00:29:51','2026-10-12 00:29:51'),(3,'hoja_servicio',2,'foto','camaraiop.webp','archivos/hoja_servicio/2026/10/616366e2-cc82-4e60-b350-03672920ca81.webp','image/webp',16440,NULL,4,'2026-10-12 00:29:53','2026-10-12 00:29:53');
/*!40000 ALTER TABLE `archivos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `atenciones`
--

DROP TABLE IF EXISTS `atenciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `atenciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `incidencia_id` bigint(20) unsigned NOT NULL,
  `tecnico_id` bigint(20) unsigned DEFAULT NULL,
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `diagnostico` text NOT NULL,
  `actividades` text NOT NULL,
  `observaciones` text DEFAULT NULL,
  `resultado` varchar(30) NOT NULL,
  `latitud` decimal(10,7) DEFAULT NULL,
  `longitud` decimal(10,7) DEFAULT NULL,
  `origen` varchar(10) NOT NULL DEFAULT 'web',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `atenciones_incidencia_id_foreign` (`incidencia_id`),
  KEY `atenciones_tecnico_id_foreign` (`tecnico_id`),
  CONSTRAINT `atenciones_incidencia_id_foreign` FOREIGN KEY (`incidencia_id`) REFERENCES `incidencias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `atenciones_tecnico_id_foreign` FOREIGN KEY (`tecnico_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `atenciones`
--

LOCK TABLES `atenciones` WRITE;
/*!40000 ALTER TABLE `atenciones` DISABLE KEYS */;
INSERT INTO `atenciones` VALUES (1,1,4,'2026-10-11 19:20:00','2026-10-11 19:22:00','Cables quemados dentro de la camara','Cambio de nuevos equipos de hackvision 10',NULL,'resuelto',NULL,NULL,'web','2026-10-12 00:23:09','2026-10-12 00:23:09');
/*!40000 ALTER TABLE `atenciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `auditorias`
--

DROP TABLE IF EXISTS `auditorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auditorias` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `accion` varchar(40) NOT NULL,
  `modulo` varchar(40) NOT NULL,
  `auditable_type` varchar(255) DEFAULT NULL,
  `auditable_id` bigint(20) unsigned DEFAULT NULL,
  `descripcion` varchar(255) NOT NULL,
  `datos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`datos`)),
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `auditorias_user_id_foreign` (`user_id`),
  KEY `auditorias_auditable_type_auditable_id_index` (`auditable_type`,`auditable_id`),
  KEY `auditorias_modulo_created_at_index` (`modulo`,`created_at`),
  CONSTRAINT `auditorias_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auditorias`
--

LOCK TABLES `auditorias` WRITE;
/*!40000 ALTER TABLE `auditorias` DISABLE KEYS */;
INSERT INTO `auditorias` VALUES (1,1,'login','acceso',NULL,NULL,'Inicio de sesión (web)',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-11 23:58:57'),(2,1,'exportar','reportes',NULL,NULL,'Generó Reporte de proyectos ejecutados en PDF (8 registros)',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-12 00:02:55'),(3,1,'crear','garantias','garantia',1,'Registró la garantía GAR-2026-00001',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-12 00:05:27'),(4,1,'exportar','reportes',NULL,NULL,'Generó Reporte de proyectos ejecutados en PDF (8 registros)',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-12 00:06:13'),(5,1,'login','acceso',NULL,NULL,'Inicio de sesión (web)',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/154.0.0.0 Safari/537.36','2026-10-12 00:12:03'),(6,1,'login','acceso',NULL,NULL,'Inicio de sesión (web)',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/154.0.0.0 Safari/537.36','2026-10-12 00:12:37'),(7,1,'crear','incidencias','incidencia',1,'Registró la incidencia INC-2026-00001',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-12 00:17:14'),(8,1,'asignar','incidencias','incidencia',1,'INC-2026-00001: Reasignada a Jorge Vílchez Santisteban',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-12 00:17:14'),(9,1,'logout','acceso',NULL,NULL,'Cierre de sesión (web)',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-12 00:18:05'),(10,4,'login','acceso',NULL,NULL,'Inicio de sesión (web)',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-12 00:18:23'),(11,4,'atender','incidencias','incidencia',1,'Registró atención en INC-2026-00001 (Resultado: Resuelto · 1 evidencia(s))',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-12 00:23:09'),(12,4,'crear','hojas_servicio','hoja_servicio',1,'Registró la hoja de servicio HS-2026-00001',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-12 00:29:51'),(13,4,'firmar','hojas_servicio','hoja_servicio',1,'Hoja HS-2026-00001 firmada por Jefe de la Entidad (con conformidad)',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-12 00:29:51'),(14,4,'crear','hojas_servicio','hoja_servicio',2,'Registró la hoja de servicio HS-2026-00002',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-12 00:29:53'),(15,4,'firmar','hojas_servicio','hoja_servicio',2,'Hoja HS-2026-00002 firmada por Jefe de la Entidad (con conformidad)',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-12 00:29:53'),(16,4,'logout','acceso',NULL,NULL,'Cierre de sesión (web)',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-12 00:43:28'),(17,1,'login','acceso',NULL,NULL,'Inicio de sesión (web)',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-12 00:44:05'),(18,1,'cerrar','incidencias','incidencia',1,'Cerró la incidencia INC-2026-00001',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-12 00:54:15'),(19,1,'logout','acceso',NULL,NULL,'Cierre de sesión (web)',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-12 00:58:42'),(20,2,'login','acceso',NULL,NULL,'Inicio de sesión (web)',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-12 00:59:07'),(21,1,'login','acceso',NULL,NULL,'Inicio de sesión (web)',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-12 01:15:05'),(22,1,'exportar','reportes',NULL,NULL,'Generó Reporte de proyectos ejecutados en PDF (8 registros)',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-12 03:00:01'),(23,1,'editar','configuracion',NULL,NULL,'Actualizó la configuración general',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-12 03:38:00'),(24,1,'exportar','reportes',NULL,NULL,'Generó Reporte de proyectos ejecutados en PDF (8 registros)',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-12 03:38:08'),(25,1,'editar','configuracion',NULL,NULL,'Actualizó la configuración general',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-12 03:38:25'),(26,1,'exportar','reportes',NULL,NULL,'Generó Reporte de proyectos ejecutados en PDF (8 registros)',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-12 03:38:36'),(27,1,'login','acceso',NULL,NULL,'Inicio de sesión (web)',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-12 04:42:48'),(28,1,'login','acceso',NULL,NULL,'Inicio de sesión (web)',NULL,'181.67.205.44','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36','2026-10-12 05:03:29'),(29,1,'login','acceso',NULL,NULL,'Inicio de sesión (web)',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/154.0.0.0 Safari/537.36','2026-10-12 05:05:06'),(30,1,'exportar','garantias','garantia',1,'Emitió la carta de garantía GAR-2026-00001',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/154.0.0.0 Safari/537.36','2026-10-12 05:05:41'),(31,1,'login','acceso',NULL,NULL,'Inicio de sesión (web)',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-12 05:20:29');
/*!40000 ALTER TABLE `auditorias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('sggp-techquk-cache-5c785c036466adea360111aa28563bfd556b5fba','i:1;',1791782488),('sggp-techquk-cache-5c785c036466adea360111aa28563bfd556b5fba:timer','i:1791782488;',1791782488),('sggp-techquk-cache-sggp.ajustes','a:11:{s:15:\"anios_historial\";s:1:\"5\";s:15:\"canales_reporte\";s:71:\"Teléfono\r\nCorreo electrónico\r\nOficio\r\nWhatsApp\r\nVisita técnica\r\nOtro\";s:20:\"dias_alerta_garantia\";s:2:\"60\";s:27:\"dias_incidencia_sin_atender\";s:1:\"3\";s:17:\"empresa_direccion\";s:55:\"Cal. Los Sauces Nro. 787 Urb. Santa Victoria - Chiclayo\";s:14:\"empresa_nombre\";s:14:\"TECHQUK S.A.C.\";s:11:\"empresa_ruc\";s:11:\"20487913791\";s:14:\"max_archivo_mb\";s:2:\"20\";s:20:\"requerir_acta_cierre\";s:1:\"1\";s:14:\"tipos_garantia\";s:122:\"Garantía de equipos\r\nGarantía de instalación\r\nSoporte técnico y mantenimiento\r\nGarantía de software / licencias\r\nOtro\";s:14:\"tipos_proyecto\";s:151:\"Videovigilancia\r\nRedes y cableado estructurado\r\nServidores y centro de datos\r\nTelecomunicaciones / Radioenlaces\r\nSeguridad electrónica\r\nSoftware\r\nOtro\";}',2107141471),('sggp-techquk-cache-spatie.permission.cache','a:3:{s:5:\"alias\";a:4:{s:1:\"a\";s:2:\"id\";s:1:\"b\";s:4:\"name\";s:1:\"c\";s:10:\"guard_name\";s:1:\"r\";s:5:\"roles\";}s:11:\"permissions\";a:25:{i:0;a:4:{s:1:\"a\";i:1;s:1:\"b\";s:13:\"dashboard.ver\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:1;a:4:{s:1:\"a\";i:2;s:1:\"b\";s:13:\"entidades.ver\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:2;a:4:{s:1:\"a\";i:3;s:1:\"b\";s:19:\"entidades.gestionar\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:3;a:4:{s:1:\"a\";i:4;s:1:\"b\";s:13:\"proyectos.ver\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:4;a:4:{s:1:\"a\";i:5;s:1:\"b\";s:19:\"proyectos.gestionar\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:5;a:4:{s:1:\"a\";i:6;s:1:\"b\";s:13:\"garantias.ver\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:6;a:4:{s:1:\"a\";i:7;s:1:\"b\";s:19:\"garantias.gestionar\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:7;a:4:{s:1:\"a\";i:8;s:1:\"b\";s:15:\"incidencias.ver\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:8;a:4:{s:1:\"a\";i:9;s:1:\"b\";s:21:\"incidencias.gestionar\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:9;a:4:{s:1:\"a\";i:10;s:1:\"b\";s:19:\"incidencias.asignar\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:10;a:4:{s:1:\"a\";i:11;s:1:\"b\";s:18:\"incidencias.cerrar\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:11;a:4:{s:1:\"a\";i:12;s:1:\"b\";s:20:\"atenciones.registrar\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:3;}}i:12;a:4:{s:1:\"a\";i:13;s:1:\"b\";s:12:\"reportes.ver\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:13;a:4:{s:1:\"a\";i:14;s:1:\"b\";s:18:\"usuarios.gestionar\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:14;a:4:{s:1:\"a\";i:15;s:1:\"b\";s:13:\"auditoria.ver\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:15;a:4:{s:1:\"a\";i:16;s:1:\"b\";s:23:\"configuracion.gestionar\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:16;a:4:{s:1:\"a\";i:19;s:1:\"b\";s:9:\"hojas.ver\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:17;a:4:{s:1:\"a\";i:20;s:1:\"b\";s:15:\"hojas.registrar\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:1;i:1;i:2;i:2;i:3;}}i:18;a:4:{s:1:\"a\";i:21;s:1:\"b\";s:12:\"hojas.anular\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:19;a:4:{s:1:\"a\";i:22;s:1:\"b\";s:11:\"equipos.ver\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:1;i:1;i:2;i:2;i:3;}}i:20;a:4:{s:1:\"a\";i:23;s:1:\"b\";s:17:\"equipos.gestionar\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:21;a:4:{s:1:\"a\";i:24;s:1:\"b\";s:18:\"mantenimientos.ver\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:1;i:1;i:2;i:2;i:3;}}i:22;a:4:{s:1:\"a\";i:25;s:1:\"b\";s:24:\"mantenimientos.gestionar\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:23;a:4:{s:1:\"a\";i:26;s:1:\"b\";s:16:\"cotizaciones.ver\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:24;a:4:{s:1:\"a\";i:27;s:1:\"b\";s:22:\"cotizaciones.gestionar\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}}s:5:\"roles\";a:3:{i:0;a:3:{s:1:\"a\";i:1;s:1:\"b\";s:13:\"Administrador\";s:1:\"c\";s:3:\"web\";}i:1;a:3:{s:1:\"a\";i:2;s:1:\"b\";s:10:\"Supervisor\";s:1:\"c\";s:3:\"web\";}i:2;a:3:{s:1:\"a\";i:3;s:1:\"b\";s:8:\"Técnico\";s:1:\"c\";s:3:\"web\";}}}',1791867907);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `configuraciones`
--

DROP TABLE IF EXISTS `configuraciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `configuraciones` (
  `clave` varchar(60) NOT NULL,
  `valor` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `configuraciones`
--

LOCK TABLES `configuraciones` WRITE;
/*!40000 ALTER TABLE `configuraciones` DISABLE KEYS */;
INSERT INTO `configuraciones` VALUES ('anios_historial','5','2026-10-12 03:38:00','2026-10-12 03:38:00'),('canales_reporte','Teléfono\r\nCorreo electrónico\r\nOficio\r\nWhatsApp\r\nVisita técnica\r\nOtro','2026-10-12 03:38:00','2026-10-12 03:38:00'),('dias_alerta_garantia','60','2026-10-12 03:38:00','2026-10-12 03:38:00'),('dias_incidencia_sin_atender','3','2026-10-12 03:38:00','2026-10-12 03:38:00'),('empresa_direccion','Cal. Los Sauces Nro. 787 Urb. Santa Victoria - Chiclayo','2026-10-12 03:38:00','2026-10-12 03:38:00'),('empresa_nombre','TECHQUK S.A.C.','2026-10-12 03:38:00','2026-10-12 03:38:25'),('empresa_ruc','20487913791','2026-10-12 03:38:00','2026-10-12 03:38:00'),('max_archivo_mb','20','2026-10-12 03:38:00','2026-10-12 03:38:00'),('requerir_acta_cierre','1','2026-10-12 03:38:00','2026-10-12 03:38:00'),('tipos_garantia','Garantía de equipos\r\nGarantía de instalación\r\nSoporte técnico y mantenimiento\r\nGarantía de software / licencias\r\nOtro','2026-10-12 03:38:00','2026-10-12 03:38:00'),('tipos_proyecto','Videovigilancia\r\nRedes y cableado estructurado\r\nServidores y centro de datos\r\nTelecomunicaciones / Radioenlaces\r\nSeguridad electrónica\r\nSoftware\r\nOtro','2026-10-12 03:38:00','2026-10-12 03:38:00');
/*!40000 ALTER TABLE `configuraciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cotizacion_items`
--

DROP TABLE IF EXISTS `cotizacion_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cotizacion_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cotizacion_id` bigint(20) unsigned NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unidad` varchar(20) NOT NULL DEFAULT 'und',
  `precio_unitario` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `cotizacion_items_cotizacion_id_foreign` (`cotizacion_id`),
  CONSTRAINT `cotizacion_items_cotizacion_id_foreign` FOREIGN KEY (`cotizacion_id`) REFERENCES `cotizaciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cotizacion_items`
--

LOCK TABLES `cotizacion_items` WRITE;
/*!40000 ALTER TABLE `cotizacion_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `cotizacion_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cotizaciones`
--

DROP TABLE IF EXISTS `cotizaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cotizaciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(30) DEFAULT NULL,
  `entidad_id` bigint(20) unsigned NOT NULL,
  `proyecto_id` bigint(20) unsigned DEFAULT NULL,
  `incidencia_id` bigint(20) unsigned DEFAULT NULL,
  `equipo_id` bigint(20) unsigned DEFAULT NULL,
  `fecha` date NOT NULL,
  `validez_dias` smallint(5) unsigned NOT NULL DEFAULT 15,
  `moneda` varchar(3) NOT NULL DEFAULT 'PEN',
  `asunto` varchar(255) NOT NULL,
  `diagnostico` text DEFAULT NULL,
  `condiciones` text DEFAULT NULL,
  `subtotal` decimal(14,2) NOT NULL DEFAULT 0.00,
  `igv` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `estado` varchar(15) NOT NULL DEFAULT 'borrador',
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cotizaciones_numero_unique` (`numero`),
  KEY `cotizaciones_entidad_id_foreign` (`entidad_id`),
  KEY `cotizaciones_proyecto_id_foreign` (`proyecto_id`),
  KEY `cotizaciones_incidencia_id_foreign` (`incidencia_id`),
  KEY `cotizaciones_equipo_id_foreign` (`equipo_id`),
  KEY `cotizaciones_user_id_foreign` (`user_id`),
  CONSTRAINT `cotizaciones_entidad_id_foreign` FOREIGN KEY (`entidad_id`) REFERENCES `entidades` (`id`),
  CONSTRAINT `cotizaciones_equipo_id_foreign` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cotizaciones_incidencia_id_foreign` FOREIGN KEY (`incidencia_id`) REFERENCES `incidencias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cotizaciones_proyecto_id_foreign` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cotizaciones_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cotizaciones`
--

LOCK TABLES `cotizaciones` WRITE;
/*!40000 ALTER TABLE `cotizaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `cotizaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `entidades`
--

DROP TABLE IF EXISTS `entidades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `entidades` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) NOT NULL,
  `ruc` varchar(11) DEFAULT NULL,
  `naturaleza` varchar(10) NOT NULL DEFAULT 'publico',
  `sector` varchar(60) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `departamento` varchar(60) DEFAULT NULL,
  `contacto_nombre` varchar(255) DEFAULT NULL,
  `contacto_cargo` varchar(255) DEFAULT NULL,
  `contacto_telefono` varchar(20) DEFAULT NULL,
  `contacto_email` varchar(255) DEFAULT NULL,
  `correos_alertas` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `entidades_ruc_unique` (`ruc`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `entidades`
--

LOCK TABLES `entidades` WRITE;
/*!40000 ALTER TABLE `entidades` DISABLE KEYS */;
INSERT INTO `entidades` VALUES (1,'Municipalidad Provincial de Chiclayo','20175639391','publico','Gobierno local / Municipalidades','Av. Balta 820, Chiclayo','Lambayeque','Ing. María Távara','Gerente de Seguridad Ciudadana','992223951','contacto95@gob.pe',NULL,1,'2026-10-11 17:52:57','2026-10-11 17:52:57',NULL),(2,'Gobierno Regional de Lambayeque','20479569861','publico','Gobierno regional','Av. Juan Tomis Stack 975, Chiclayo','Lambayeque','Lic. Carlos Ruiz','Jefe de Tecnologías de la Información','953130236','contacto45@gob.pe',NULL,1,'2026-10-11 17:52:57','2026-10-11 17:52:57',NULL),(3,'Hospital Regional Lambayeque','20479397904','publico','Salud','Prolong. Augusto B. Leguía, Chiclayo','Lambayeque','Ing. Rosa Delgado','Jefa de Informática','980163580','contacto54@gob.pe',NULL,1,'2026-10-11 17:52:57','2026-10-11 17:52:57',NULL),(4,'Universidad Nacional Pedro Ruiz Gallo','20148238641','publico','Educación','Calle Juan XXIII 391, Lambayeque','Lambayeque','Mg. Pedro Sánchez','Director de TIC','976185084','contacto23@gob.pe',NULL,1,'2026-10-11 17:52:57','2026-10-11 17:52:57',NULL),(5,'Municipalidad Distrital de José Leonardo Ortiz','20175637704','publico','Gobierno local / Municipalidades','Av. Sáenz Peña 2151, JLO','Lambayeque','Sr. Juan Cieza','Sub Gerente de Serenazgo','990430607','contacto43@gob.pe',NULL,1,'2026-10-11 17:52:57','2026-10-11 17:52:57',NULL);
/*!40000 ALTER TABLE `entidades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `equipo_eventos`
--

DROP TABLE IF EXISTS `equipo_eventos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipo_eventos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `equipo_id` bigint(20) unsigned NOT NULL,
  `tipo` varchar(30) NOT NULL,
  `fecha` datetime NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `detalle` text DEFAULT NULL,
  `componente` varchar(100) DEFAULT NULL,
  `estado_equipo` varchar(20) DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `incidencia_id` bigint(20) unsigned DEFAULT NULL,
  `hoja_servicio_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `equipo_eventos_user_id_foreign` (`user_id`),
  KEY `equipo_eventos_incidencia_id_foreign` (`incidencia_id`),
  KEY `equipo_eventos_hoja_servicio_id_foreign` (`hoja_servicio_id`),
  KEY `equipo_eventos_equipo_id_fecha_index` (`equipo_id`,`fecha`),
  CONSTRAINT `equipo_eventos_equipo_id_foreign` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `equipo_eventos_hoja_servicio_id_foreign` FOREIGN KEY (`hoja_servicio_id`) REFERENCES `hojas_servicio` (`id`) ON DELETE CASCADE,
  CONSTRAINT `equipo_eventos_incidencia_id_foreign` FOREIGN KEY (`incidencia_id`) REFERENCES `incidencias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `equipo_eventos_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipo_eventos`
--

LOCK TABLES `equipo_eventos` WRITE;
/*!40000 ALTER TABLE `equipo_eventos` DISABLE KEYS */;
/*!40000 ALTER TABLE `equipo_eventos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `equipos`
--

DROP TABLE IF EXISTS `equipos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) DEFAULT NULL,
  `entidad_id` bigint(20) unsigned NOT NULL,
  `proyecto_id` bigint(20) unsigned DEFAULT NULL,
  `garantia_id` bigint(20) unsigned DEFAULT NULL,
  `categoria` varchar(60) NOT NULL,
  `marca` varchar(80) DEFAULT NULL,
  `modelo` varchar(80) DEFAULT NULL,
  `serie` varchar(80) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `ubicacion` varchar(255) DEFAULT NULL,
  `area` varchar(100) DEFAULT NULL,
  `responsable` varchar(255) DEFAULT NULL,
  `fecha_instalacion` date DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'operativo',
  `observaciones` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `equipos_codigo_unique` (`codigo`),
  KEY `equipos_proyecto_id_foreign` (`proyecto_id`),
  KEY `equipos_garantia_id_foreign` (`garantia_id`),
  KEY `equipos_entidad_id_categoria_index` (`entidad_id`,`categoria`),
  KEY `equipos_serie_index` (`serie`),
  CONSTRAINT `equipos_entidad_id_foreign` FOREIGN KEY (`entidad_id`) REFERENCES `entidades` (`id`),
  CONSTRAINT `equipos_garantia_id_foreign` FOREIGN KEY (`garantia_id`) REFERENCES `garantias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `equipos_proyecto_id_foreign` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipos`
--

LOCK TABLES `equipos` WRITE;
/*!40000 ALTER TABLE `equipos` DISABLE KEYS */;
/*!40000 ALTER TABLE `equipos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `garantia_alertas`
--

DROP TABLE IF EXISTS `garantia_alertas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `garantia_alertas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `garantia_id` bigint(20) unsigned NOT NULL,
  `dias` smallint(6) NOT NULL,
  `destinatarios` text DEFAULT NULL,
  `enviada_en` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `garantia_alertas_garantia_id_dias_unique` (`garantia_id`,`dias`),
  CONSTRAINT `garantia_alertas_garantia_id_foreign` FOREIGN KEY (`garantia_id`) REFERENCES `garantias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `garantia_alertas`
--

LOCK TABLES `garantia_alertas` WRITE;
/*!40000 ALTER TABLE `garantia_alertas` DISABLE KEYS */;
/*!40000 ALTER TABLE `garantia_alertas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `garantias`
--

DROP TABLE IF EXISTS `garantias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `garantias` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(30) DEFAULT NULL,
  `proyecto_id` bigint(20) unsigned NOT NULL,
  `tipo` varchar(40) NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `cobertura` text DEFAULT NULL,
  `condiciones` text DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `proveedor` varchar(255) DEFAULT NULL,
  `numero_documento` varchar(60) DEFAULT NULL,
  `orden_compra` varchar(60) DEFAULT NULL,
  `monto` decimal(14,2) DEFAULT NULL,
  `fecha_inicio` date NOT NULL,
  `duracion_meses` smallint(5) unsigned DEFAULT NULL,
  `fecha_vencimiento` date NOT NULL,
  `anulada` tinyint(1) NOT NULL DEFAULT 0,
  `notificar_cliente` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `garantias_codigo_unique` (`codigo`),
  KEY `garantias_proyecto_id_foreign` (`proyecto_id`),
  KEY `garantias_fecha_vencimiento_index` (`fecha_vencimiento`),
  CONSTRAINT `garantias_proyecto_id_foreign` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `garantias`
--

LOCK TABLES `garantias` WRITE;
/*!40000 ALTER TABLE `garantias` DISABLE KEYS */;
INSERT INTO `garantias` VALUES (1,'GAR-2026-00001',1,'Garantía de equipos','Garantia',NULL,NULL,NULL,NULL,NULL,NULL,200000.00,'2023-01-15',NULL,'2028-01-15',0,1,'2026-10-12 00:05:27','2026-10-12 00:05:27',NULL);
/*!40000 ALTER TABLE `garantias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hoja_servicio_equipos`
--

DROP TABLE IF EXISTS `hoja_servicio_equipos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hoja_servicio_equipos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `hoja_servicio_id` bigint(20) unsigned NOT NULL,
  `equipo_id` bigint(20) unsigned DEFAULT NULL,
  `equipo` varchar(255) NOT NULL,
  `marca` varchar(80) DEFAULT NULL,
  `modelo` varchar(80) DEFAULT NULL,
  `serie` varchar(80) DEFAULT NULL,
  `ubicacion` varchar(255) DEFAULT NULL,
  `accion` varchar(20) NOT NULL,
  `estado_final` varchar(20) NOT NULL,
  `observacion` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hoja_servicio_equipos_hoja_servicio_id_foreign` (`hoja_servicio_id`),
  KEY `hoja_servicio_equipos_equipo_id_foreign` (`equipo_id`),
  CONSTRAINT `hoja_servicio_equipos_equipo_id_foreign` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hoja_servicio_equipos_hoja_servicio_id_foreign` FOREIGN KEY (`hoja_servicio_id`) REFERENCES `hojas_servicio` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hoja_servicio_equipos`
--

LOCK TABLES `hoja_servicio_equipos` WRITE;
/*!40000 ALTER TABLE `hoja_servicio_equipos` DISABLE KEYS */;
INSERT INTO `hoja_servicio_equipos` VALUES (6,1,NULL,'hackvision',NULL,NULL,NULL,'hackvision.r','reemplazado','inoperativo',NULL),(7,1,NULL,'hackvision.',NULL,NULL,NULL,'hackvision.r','reemplazado','inoperativo',NULL),(8,1,NULL,'hackvision.',NULL,NULL,NULL,'hackvision.r','reemplazado','inoperativo',NULL),(9,1,NULL,'hackvision.',NULL,NULL,NULL,'hackvision.r','reemplazado','inoperativo',NULL),(10,1,NULL,'hackvision.',NULL,NULL,NULL,'hackvision.r','reemplazado','inoperativo',NULL),(11,1,NULL,'hackvision.',NULL,NULL,NULL,'hackvision.r','reemplazado','inoperativo',NULL),(12,1,NULL,'hackvision.',NULL,NULL,NULL,'hackvision.r','reemplazado','inoperativo',NULL),(13,1,NULL,'hackvision.',NULL,NULL,NULL,'hackvision.r','reemplazado','inoperativo',NULL),(14,2,NULL,'hackvision',NULL,NULL,NULL,'hackvision.r','reemplazado','inoperativo',NULL),(15,2,NULL,'hackvision.',NULL,NULL,NULL,'hackvision.r','reemplazado','inoperativo',NULL),(16,2,NULL,'hackvision.',NULL,NULL,NULL,'hackvision.r','reemplazado','inoperativo',NULL),(17,2,NULL,'hackvision.',NULL,NULL,NULL,'hackvision.r','reemplazado','inoperativo',NULL),(18,2,NULL,'hackvision.',NULL,NULL,NULL,'hackvision.r','reemplazado','inoperativo',NULL),(19,2,NULL,'hackvision.',NULL,NULL,NULL,'hackvision.r','reemplazado','inoperativo',NULL),(20,2,NULL,'hackvision.',NULL,NULL,NULL,'hackvision.r','reemplazado','inoperativo',NULL),(21,2,NULL,'hackvision.',NULL,NULL,NULL,'hackvision.r','reemplazado','inoperativo',NULL);
/*!40000 ALTER TABLE `hoja_servicio_equipos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hoja_servicio_materiales`
--

DROP TABLE IF EXISTS `hoja_servicio_materiales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hoja_servicio_materiales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `hoja_servicio_id` bigint(20) unsigned NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unidad` varchar(20) NOT NULL DEFAULT 'und',
  PRIMARY KEY (`id`),
  KEY `hoja_servicio_materiales_hoja_servicio_id_foreign` (`hoja_servicio_id`),
  CONSTRAINT `hoja_servicio_materiales_hoja_servicio_id_foreign` FOREIGN KEY (`hoja_servicio_id`) REFERENCES `hojas_servicio` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hoja_servicio_materiales`
--

LOCK TABLES `hoja_servicio_materiales` WRITE;
/*!40000 ALTER TABLE `hoja_servicio_materiales` DISABLE KEYS */;
/*!40000 ALTER TABLE `hoja_servicio_materiales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hojas_servicio`
--

DROP TABLE IF EXISTS `hojas_servicio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hojas_servicio` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(30) DEFAULT NULL,
  `proyecto_id` bigint(20) unsigned NOT NULL,
  `garantia_id` bigint(20) unsigned DEFAULT NULL,
  `incidencia_id` bigint(20) unsigned DEFAULT NULL,
  `mantenimiento_id` bigint(20) unsigned DEFAULT NULL,
  `tecnico_id` bigint(20) unsigned DEFAULT NULL,
  `tipo_servicio` varchar(20) NOT NULL,
  `fecha` date NOT NULL,
  `hora_llegada` time NOT NULL,
  `hora_salida` time DEFAULT NULL,
  `trabajo_realizado` text NOT NULL,
  `observaciones` text DEFAULT NULL,
  `recomendaciones` text DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'pendiente_firma',
  `conforme` tinyint(1) NOT NULL DEFAULT 0,
  `receptor_nombre` varchar(255) DEFAULT NULL,
  `receptor_dni` varchar(15) DEFAULT NULL,
  `receptor_cargo` varchar(255) DEFAULT NULL,
  `firma_receptor` varchar(255) DEFAULT NULL,
  `firma_tecnico` varchar(255) DEFAULT NULL,
  `fecha_firma` datetime DEFAULT NULL,
  `latitud` decimal(10,7) DEFAULT NULL,
  `longitud` decimal(10,7) DEFAULT NULL,
  `origen` varchar(10) NOT NULL DEFAULT 'web',
  `motivo_anulacion` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hojas_servicio_numero_unique` (`numero`),
  KEY `hojas_servicio_proyecto_id_foreign` (`proyecto_id`),
  KEY `hojas_servicio_garantia_id_foreign` (`garantia_id`),
  KEY `hojas_servicio_incidencia_id_foreign` (`incidencia_id`),
  KEY `hojas_servicio_tecnico_id_fecha_index` (`tecnico_id`,`fecha`),
  KEY `hojas_servicio_estado_fecha_index` (`estado`,`fecha`),
  KEY `hojas_servicio_mantenimiento_id_foreign` (`mantenimiento_id`),
  CONSTRAINT `hojas_servicio_garantia_id_foreign` FOREIGN KEY (`garantia_id`) REFERENCES `garantias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hojas_servicio_incidencia_id_foreign` FOREIGN KEY (`incidencia_id`) REFERENCES `incidencias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hojas_servicio_mantenimiento_id_foreign` FOREIGN KEY (`mantenimiento_id`) REFERENCES `mantenimientos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hojas_servicio_proyecto_id_foreign` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`),
  CONSTRAINT `hojas_servicio_tecnico_id_foreign` FOREIGN KEY (`tecnico_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hojas_servicio`
--

LOCK TABLES `hojas_servicio` WRITE;
/*!40000 ALTER TABLE `hojas_servicio` DISABLE KEYS */;
INSERT INTO `hojas_servicio` VALUES (1,'HS-2026-00001',1,1,1,NULL,4,'instalacion','2026-10-11','19:20:00','19:22:00','Diagnóstico: Cables quemados dentro de la camara\r\n\r\nCambio de nuevos equipos de hackvision 10',NULL,NULL,'firmada',1,'Jefe de la Entidad','75894033','Jefe de la Entidad','firmas/2026/10/25bc57ef-98d6-4552-89f6-e7479fc9c9b6.png','firmas/2026/10/e63e8405-d42b-4c31-bebe-112eef318cbe.png','2026-10-11 19:29:51',NULL,NULL,'web',NULL,'2026-10-12 00:29:51','2026-10-12 00:29:51'),(2,'HS-2026-00002',1,1,1,NULL,4,'instalacion','2026-10-11','19:20:00','19:22:00','Diagnóstico: Cables quemados dentro de la camara\r\n\r\nCambio de nuevos equipos de hackvision 10',NULL,NULL,'firmada',1,'Jefe de la Entidad','75894033','Jefe de la Entidad','firmas/2026/10/7fd58b8e-106c-4c59-9f70-670636f4826d.png','firmas/2026/10/f0a02585-6a4b-42f3-beda-0c90f06fb9c7.png','2026-10-11 19:29:53',NULL,NULL,'web',NULL,'2026-10-12 00:29:53','2026-10-12 00:29:53');
/*!40000 ALTER TABLE `hojas_servicio` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `incidencia_historial`
--

DROP TABLE IF EXISTS `incidencia_historial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `incidencia_historial` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `incidencia_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `accion` varchar(30) NOT NULL,
  `estado_anterior` varchar(20) DEFAULT NULL,
  `estado_nuevo` varchar(20) DEFAULT NULL,
  `detalle` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `incidencia_historial_incidencia_id_foreign` (`incidencia_id`),
  KEY `incidencia_historial_user_id_foreign` (`user_id`),
  CONSTRAINT `incidencia_historial_incidencia_id_foreign` FOREIGN KEY (`incidencia_id`) REFERENCES `incidencias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `incidencia_historial_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `incidencia_historial`
--

LOCK TABLES `incidencia_historial` WRITE;
/*!40000 ALTER TABLE `incidencia_historial` DISABLE KEYS */;
INSERT INTO `incidencia_historial` VALUES (50,1,1,'registro',NULL,'registrada','Incidencia registrada','2026-10-12 00:17:14'),(51,1,1,'asignacion','registrada','asignada','Reasignada a Jorge Vílchez Santisteban','2026-10-12 00:17:14'),(52,1,4,'cambio_estado','asignada','en_proceso','El técnico inició la atención','2026-10-12 00:20:41'),(53,1,4,'atencion','en_proceso','resuelta','Resultado: Resuelto · 1 evidencia(s)','2026-10-12 00:23:09'),(54,1,4,'hoja_servicio',NULL,NULL,'Hoja de servicio HS-2026-00001 registrada','2026-10-12 00:29:51'),(55,1,4,'hoja_servicio',NULL,NULL,'Hoja HS-2026-00001 firmada por Jefe de la Entidad (con conformidad)','2026-10-12 00:29:51'),(56,1,4,'hoja_servicio',NULL,NULL,'Hoja de servicio HS-2026-00002 registrada','2026-10-12 00:29:53'),(57,1,4,'hoja_servicio',NULL,NULL,'Hoja HS-2026-00002 firmada por Jefe de la Entidad (con conformidad)','2026-10-12 00:29:53'),(58,1,1,'cierre','resuelta','cerrada','Incidencia cerrada','2026-10-12 00:54:15');
/*!40000 ALTER TABLE `incidencia_historial` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `incidencias`
--

DROP TABLE IF EXISTS `incidencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `incidencias` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(30) DEFAULT NULL,
  `proyecto_id` bigint(20) unsigned NOT NULL,
  `garantia_id` bigint(20) unsigned DEFAULT NULL,
  `equipo_id` bigint(20) unsigned DEFAULT NULL,
  `cobertura` varchar(20) NOT NULL DEFAULT 'garantia',
  `titulo` varchar(255) NOT NULL,
  `descripcion` text NOT NULL,
  `prioridad` varchar(15) NOT NULL DEFAULT 'media',
  `estado` varchar(20) NOT NULL DEFAULT 'registrada',
  `canal` varchar(20) DEFAULT NULL,
  `reportado_por` varchar(255) DEFAULT NULL,
  `ubicacion_detalle` varchar(255) DEFAULT NULL,
  `fecha_reporte` datetime NOT NULL,
  `fecha_limite` datetime DEFAULT NULL,
  `tecnico_id` bigint(20) unsigned DEFAULT NULL,
  `asignado_por` bigint(20) unsigned DEFAULT NULL,
  `fecha_asignacion` datetime DEFAULT NULL,
  `fecha_resolucion` datetime DEFAULT NULL,
  `fecha_cierre` datetime DEFAULT NULL,
  `cerrado_por` bigint(20) unsigned DEFAULT NULL,
  `registrado_por` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `incidencias_codigo_unique` (`codigo`),
  KEY `incidencias_proyecto_id_foreign` (`proyecto_id`),
  KEY `incidencias_garantia_id_foreign` (`garantia_id`),
  KEY `incidencias_asignado_por_foreign` (`asignado_por`),
  KEY `incidencias_cerrado_por_foreign` (`cerrado_por`),
  KEY `incidencias_registrado_por_foreign` (`registrado_por`),
  KEY `incidencias_estado_prioridad_index` (`estado`,`prioridad`),
  KEY `incidencias_tecnico_id_estado_index` (`tecnico_id`,`estado`),
  KEY `incidencias_equipo_id_foreign` (`equipo_id`),
  CONSTRAINT `incidencias_asignado_por_foreign` FOREIGN KEY (`asignado_por`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incidencias_cerrado_por_foreign` FOREIGN KEY (`cerrado_por`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incidencias_equipo_id_foreign` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incidencias_garantia_id_foreign` FOREIGN KEY (`garantia_id`) REFERENCES `garantias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incidencias_proyecto_id_foreign` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`),
  CONSTRAINT `incidencias_registrado_por_foreign` FOREIGN KEY (`registrado_por`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incidencias_tecnico_id_foreign` FOREIGN KEY (`tecnico_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `incidencias`
--

LOCK TABLES `incidencias` WRITE;
/*!40000 ALTER TABLE `incidencias` DISABLE KEYS */;
INSERT INTO `incidencias` VALUES (1,'INC-2026-00001',1,1,NULL,'garantia','Fallo de camaras','10 camaras no graban','alta','cerrada','Teléfono','Jefe de la Entidad','Centro Chiclayo','2026-10-11 19:14:00',NULL,4,1,'2026-10-11 19:17:14','2026-10-11 19:23:09','2026-10-11 19:54:15',1,1,'2026-10-12 00:17:14','2026-10-12 00:54:15',NULL);
/*!40000 ALTER TABLE `incidencias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mantenimientos`
--

DROP TABLE IF EXISTS `mantenimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mantenimientos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `plan_id` bigint(20) unsigned NOT NULL,
  `numero` smallint(5) unsigned NOT NULL,
  `fecha_programada` date NOT NULL,
  `estado` varchar(15) NOT NULL DEFAULT 'programado',
  `tecnico_id` bigint(20) unsigned DEFAULT NULL,
  `hoja_servicio_id` bigint(20) unsigned DEFAULT NULL,
  `fecha_ejecucion` date DEFAULT NULL,
  `observacion` text DEFAULT NULL,
  `alerta_enviada` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mantenimientos_plan_id_foreign` (`plan_id`),
  KEY `mantenimientos_tecnico_id_foreign` (`tecnico_id`),
  KEY `mantenimientos_hoja_servicio_id_foreign` (`hoja_servicio_id`),
  KEY `mantenimientos_estado_fecha_programada_index` (`estado`,`fecha_programada`),
  CONSTRAINT `mantenimientos_hoja_servicio_id_foreign` FOREIGN KEY (`hoja_servicio_id`) REFERENCES `hojas_servicio` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mantenimientos_plan_id_foreign` FOREIGN KEY (`plan_id`) REFERENCES `planes_mantenimiento` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mantenimientos_tecnico_id_foreign` FOREIGN KEY (`tecnico_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mantenimientos`
--

LOCK TABLES `mantenimientos` WRITE;
/*!40000 ALTER TABLE `mantenimientos` DISABLE KEYS */;
/*!40000 ALTER TABLE `mantenimientos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_10_11_124636_create_notifications_table',1),(5,'2026_10_11_174538_create_personal_access_tokens_table',1),(6,'2026_10_11_174541_create_permission_tables',1),(7,'2026_10_11_180000_create_dominio_garantias_tables',1),(8,'2026_10_12_090000_create_hojas_servicio_tables',2),(9,'2026_10_13_090000_eliminar_modulo_sedes',3),(10,'2026_10_14_090000_clientes_proyectos_y_garantias',4),(11,'2026_10_14_091000_create_equipos_tables',4),(12,'2026_10_14_092000_create_mantenimientos_y_cotizaciones_tables',4);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_permissions`
--

DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_permissions`
--

LOCK TABLES `model_has_permissions` WRITE;
/*!40000 ALTER TABLE `model_has_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `model_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_roles`
--

DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_roles`
--

LOCK TABLES `model_has_roles` WRITE;
/*!40000 ALTER TABLE `model_has_roles` DISABLE KEYS */;
INSERT INTO `model_has_roles` VALUES (1,'user',1),(2,'user',2),(3,'user',3),(3,'user',4);
/*!40000 ALTER TABLE `model_has_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(255) NOT NULL,
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) unsigned NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES ('09f677ab-ad39-4069-9289-ee110ee9f5e2','App\\Notifications\\HojaServicioFirmada','user',1,'{\"tipo\":\"hoja\",\"titulo\":\"Hoja de servicio HS-2026-00001 firmada\",\"mensaje\":\"Instalaci\\u00f3n \\/ reposici\\u00f3n \\u00b7 Municipalidad Provincial de Chiclayo \\u00b7 Jorge V\\u00edlchez Santisteban\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/hojas-servicio\\/1\",\"incidencia_id\":\"1\"}','2026-10-12 00:56:46','2026-10-12 00:29:51','2026-10-12 00:56:46'),('1a8e2dd0-376a-4d22-aa9d-08424c8376d3','App\\Notifications\\HojaServicioFirmada','user',2,'{\"tipo\":\"hoja\",\"titulo\":\"Hoja de servicio HS-2026-00002 firmada\",\"mensaje\":\"Instalaci\\u00f3n \\/ reposici\\u00f3n \\u00b7 Municipalidad Provincial de Chiclayo \\u00b7 Jorge V\\u00edlchez Santisteban\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/hojas-servicio\\/2\",\"incidencia_id\":\"1\"}','2026-10-12 01:02:06','2026-10-12 00:29:53','2026-10-12 01:02:06'),('34f42870-e718-49cc-828e-7e882d3dbc03','App\\Notifications\\IncidenciaAsignada','user',4,'{\"tipo\":\"incidencia\",\"titulo\":\"Nueva incidencia asignada INC-2026-00001\",\"mensaje\":\"Fallo de camaras \\u00b7 Sistema de videovigilancia urbana \\u2013 Centro de Chiclayo (120 c\\u00e1maras) \\u00b7 Prioridad Alta\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/mis-incidencias\\/1\",\"incidencia_id\":1}','2026-10-12 00:33:29','2026-10-12 00:17:14','2026-10-12 00:33:29'),('6b256813-997f-45f2-84b6-93bae7e10bce','App\\Notifications\\HojaServicioFirmada','user',2,'{\"tipo\":\"hoja\",\"titulo\":\"Hoja de servicio HS-2026-00001 firmada\",\"mensaje\":\"Instalaci\\u00f3n \\/ reposici\\u00f3n \\u00b7 Municipalidad Provincial de Chiclayo \\u00b7 Jorge V\\u00edlchez Santisteban\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/hojas-servicio\\/1\",\"incidencia_id\":\"1\"}','2026-10-12 01:02:06','2026-10-12 00:29:51','2026-10-12 01:02:06'),('a118fd83-f33f-4645-87cc-e1eb80d938d3','App\\Notifications\\HojaServicioFirmada','user',1,'{\"tipo\":\"hoja\",\"titulo\":\"Hoja de servicio HS-2026-00002 firmada\",\"mensaje\":\"Instalaci\\u00f3n \\/ reposici\\u00f3n \\u00b7 Municipalidad Provincial de Chiclayo \\u00b7 Jorge V\\u00edlchez Santisteban\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/hojas-servicio\\/2\",\"incidencia_id\":\"1\"}','2026-10-12 00:56:46','2026-10-12 00:29:53','2026-10-12 00:56:46'),('fb294566-b328-47e6-8a43-fc2013f7b982','App\\Notifications\\IncidenciaResuelta','user',1,'{\"tipo\":\"resuelta\",\"titulo\":\"Incidencia INC-2026-00001 resuelta, pendiente de cierre\",\"mensaje\":\"Fallo de camaras \\u00b7 atendida por Jorge V\\u00edlchez Santisteban\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/incidencias\\/1\",\"incidencia_id\":1}','2026-10-12 00:56:46','2026-10-12 00:23:09','2026-10-12 00:56:46');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'dashboard.ver','web','2026-10-11 17:52:56','2026-10-11 17:52:56'),(2,'entidades.ver','web','2026-10-11 17:52:56','2026-10-11 17:52:56'),(3,'entidades.gestionar','web','2026-10-11 17:52:56','2026-10-11 17:52:56'),(4,'proyectos.ver','web','2026-10-11 17:52:56','2026-10-11 17:52:56'),(5,'proyectos.gestionar','web','2026-10-11 17:52:56','2026-10-11 17:52:56'),(6,'garantias.ver','web','2026-10-11 17:52:56','2026-10-11 17:52:56'),(7,'garantias.gestionar','web','2026-10-11 17:52:56','2026-10-11 17:52:56'),(8,'incidencias.ver','web','2026-10-11 17:52:56','2026-10-11 17:52:56'),(9,'incidencias.gestionar','web','2026-10-11 17:52:56','2026-10-11 17:52:56'),(10,'incidencias.asignar','web','2026-10-11 17:52:56','2026-10-11 17:52:56'),(11,'incidencias.cerrar','web','2026-10-11 17:52:56','2026-10-11 17:52:56'),(12,'atenciones.registrar','web','2026-10-11 17:52:56','2026-10-11 17:52:56'),(13,'reportes.ver','web','2026-10-11 17:52:56','2026-10-11 17:52:56'),(14,'usuarios.gestionar','web','2026-10-11 17:52:56','2026-10-11 17:52:56'),(15,'auditoria.ver','web','2026-10-11 17:52:56','2026-10-11 17:52:56'),(16,'configuracion.gestionar','web','2026-10-11 17:52:56','2026-10-11 17:52:56'),(19,'hojas.ver','web','2026-10-11 20:32:46','2026-10-11 20:32:46'),(20,'hojas.registrar','web','2026-10-11 20:32:46','2026-10-11 20:32:46'),(21,'hojas.anular','web','2026-10-11 20:32:46','2026-10-11 20:32:46'),(22,'equipos.ver','web','2026-10-12 04:37:29','2026-10-12 04:37:29'),(23,'equipos.gestionar','web','2026-10-12 04:37:29','2026-10-12 04:37:29'),(24,'mantenimientos.ver','web','2026-10-12 04:37:29','2026-10-12 04:37:29'),(25,'mantenimientos.gestionar','web','2026-10-12 04:37:29','2026-10-12 04:37:29'),(26,'cotizaciones.ver','web','2026-10-12 04:37:29','2026-10-12 04:37:29'),(27,'cotizaciones.gestionar','web','2026-10-12 04:37:29','2026-10-12 04:37:29');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
INSERT INTO `personal_access_tokens` VALUES (1,'user',3,'app-movil','ffa48d59734a5d6243462b7df0d31a1fe784af70fdbf19cc5f02c2a74431e166','[\"tecnico\"]','2026-10-11 18:25:57','2026-11-10 18:25:56','2026-10-11 18:25:56','2026-10-11 18:25:57'),(2,'user',3,'app-movil','cd1b0c012ae90bafcf799f4aff8a7b30e52f5aba9274f8a2b14c8645832c86c0','[\"tecnico\"]','2026-10-11 18:26:19','2026-11-10 18:26:18','2026-10-11 18:26:18','2026-10-11 18:26:19'),(3,'user',4,'app-movil','bca6d56f0e15d9ba33c249d57273d1e3526847cc95a5a315fd0a1f5f07b69807','[\"tecnico\"]','2026-10-11 18:26:37','2026-11-10 18:26:36','2026-10-11 18:26:36','2026-10-11 18:26:37'),(4,'user',4,'app-movil','1b09d46d1480f75c275983668ff0534d1bd1779780d4da2a25e71f6c1726931f','[\"tecnico\"]','2026-10-11 18:26:44','2026-11-10 18:26:43','2026-10-11 18:26:43','2026-10-11 18:26:44'),(5,'user',4,'app-movil','c1e0fc6cee69d79c0be31e19418373a23aadb0d318a9e691d0b8b07e8ef181fd','[\"tecnico\"]','2026-10-11 18:27:00','2026-11-10 18:26:57','2026-10-11 18:26:57','2026-10-11 18:27:00'),(6,'user',4,'app-movil','d422ef7b9c36a3f973e665345b2e9e7ff18c8b22fcc99f860f4423fb1b704f14','[\"tecnico\"]','2026-10-11 21:54:41','2026-11-10 21:54:38','2026-10-11 21:54:38','2026-10-11 21:54:41');
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `planes_mantenimiento`
--

DROP TABLE IF EXISTS `planes_mantenimiento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `planes_mantenimiento` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `proyecto_id` bigint(20) unsigned NOT NULL,
  `garantia_id` bigint(20) unsigned DEFAULT NULL,
  `nombre` varchar(255) NOT NULL,
  `frecuencia_meses` tinyint(3) unsigned NOT NULL,
  `total_visitas` smallint(5) unsigned NOT NULL,
  `fecha_inicio` date NOT NULL,
  `tecnico_id` bigint(20) unsigned DEFAULT NULL,
  `alcance` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `planes_mantenimiento_proyecto_id_foreign` (`proyecto_id`),
  KEY `planes_mantenimiento_garantia_id_foreign` (`garantia_id`),
  KEY `planes_mantenimiento_tecnico_id_foreign` (`tecnico_id`),
  CONSTRAINT `planes_mantenimiento_garantia_id_foreign` FOREIGN KEY (`garantia_id`) REFERENCES `garantias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `planes_mantenimiento_proyecto_id_foreign` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `planes_mantenimiento_tecnico_id_foreign` FOREIGN KEY (`tecnico_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `planes_mantenimiento`
--

LOCK TABLES `planes_mantenimiento` WRITE;
/*!40000 ALTER TABLE `planes_mantenimiento` DISABLE KEYS */;
/*!40000 ALTER TABLE `planes_mantenimiento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proyectos`
--

DROP TABLE IF EXISTS `proyectos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proyectos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(30) DEFAULT NULL,
  `nombre` varchar(255) NOT NULL,
  `clase` varchar(15) NOT NULL DEFAULT 'implementacion',
  `entidad_id` bigint(20) unsigned NOT NULL,
  `cliente_final_id` bigint(20) unsigned DEFAULT NULL,
  `tipo` varchar(40) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `numero_contrato` varchar(60) DEFAULT NULL,
  `orden_compra` varchar(60) DEFAULT NULL,
  `monto_contrato` decimal(14,2) DEFAULT NULL,
  `ubicacion` varchar(255) DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_entrega` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `responsable_id` bigint(20) unsigned DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'operativo',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `proyectos_codigo_unique` (`codigo`),
  KEY `proyectos_responsable_id_foreign` (`responsable_id`),
  KEY `proyectos_entidad_id_estado_index` (`entidad_id`,`estado`),
  KEY `proyectos_cliente_final_id_foreign` (`cliente_final_id`),
  CONSTRAINT `proyectos_cliente_final_id_foreign` FOREIGN KEY (`cliente_final_id`) REFERENCES `entidades` (`id`) ON DELETE SET NULL,
  CONSTRAINT `proyectos_entidad_id_foreign` FOREIGN KEY (`entidad_id`) REFERENCES `entidades` (`id`),
  CONSTRAINT `proyectos_responsable_id_foreign` FOREIGN KEY (`responsable_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proyectos`
--

LOCK TABLES `proyectos` WRITE;
/*!40000 ALTER TABLE `proyectos` DISABLE KEYS */;
INSERT INTO `proyectos` VALUES (1,'PRY-2026-00001','Sistema de videovigilancia urbana – Centro de Chiclayo (120 cámaras)','implementacion',1,NULL,'Videovigilancia','Suministro, instalación y puesta en funcionamiento: Sistema de videovigilancia urbana – Centro de Chiclayo (120 cámaras).','CONTRATO N° 010-2022',NULL,1368000.00,'Av. Balta 820, Chiclayo','2022-03-11','2022-08-11',NULL,2,'operativo','2026-10-11 17:52:57','2026-10-11 17:52:57',NULL),(2,'PRY-2026-00002','Central de monitoreo de Serenazgo y enlaces de fibra óptica','implementacion',1,NULL,'Telecomunicaciones / Radioenlaces','Suministro, instalación y puesta en funcionamiento: Central de monitoreo de Serenazgo y enlaces de fibra óptica.','CONTRATO N° 011-2024',NULL,418000.00,'Av. Balta 820, Chiclayo','2024-07-11','2024-12-11',NULL,2,'operativo','2026-10-11 17:52:57','2026-10-11 17:52:57',NULL),(3,'PRY-2026-00003','Renovación del centro de datos de la sede regional','implementacion',2,NULL,'Servidores y centro de datos','Suministro, instalación y puesta en funcionamiento: Renovación del centro de datos de la sede regional.','CONTRATO N° 012-2023',NULL,482000.00,'Av. Juan Tomis Stack 975, Chiclayo','2023-07-11','2023-12-11',NULL,2,'operativo','2026-10-11 17:52:57','2026-10-11 17:52:57',NULL),(4,'PRY-2026-00004','Red WAN de radioenlaces para agencias agrarias','implementacion',2,NULL,'Telecomunicaciones / Radioenlaces','Suministro, instalación y puesta en funcionamiento: Red WAN de radioenlaces para agencias agrarias.','CONTRATO N° 013-2022',NULL,2309000.00,'Av. Juan Tomis Stack 975, Chiclayo','2021-08-11','2022-01-11',NULL,2,'operativo','2026-10-11 17:52:57','2026-10-11 17:52:57',NULL),(5,'PRY-2026-00005','Cableado estructurado Cat 6A y red inalámbrica hospitalaria','implementacion',3,NULL,'Redes y cableado estructurado','Suministro, instalación y puesta en funcionamiento: Cableado estructurado Cat 6A y red inalámbrica hospitalaria.','CONTRATO N° 014-2025',NULL,1152000.00,'Prolong. Augusto B. Leguía, Chiclayo','2025-04-11','2025-09-11',NULL,2,'operativo','2026-10-11 17:52:57','2026-10-11 17:52:57',NULL),(6,'PRY-2026-00006','Videovigilancia de áreas críticas y control de accesos','implementacion',3,NULL,'Seguridad electrónica','Suministro, instalación y puesta en funcionamiento: Videovigilancia de áreas críticas y control de accesos.','CONTRATO N° 015-2024',NULL,649000.00,'Prolong. Augusto B. Leguía, Chiclayo','2024-01-11','2024-06-11',NULL,2,'operativo','2026-10-11 17:52:57','2026-10-11 17:52:57',NULL),(7,'PRY-2026-00007','Red de campus y equipamiento de laboratorios de cómputo','implementacion',4,NULL,'Redes y cableado estructurado','Suministro, instalación y puesta en funcionamiento: Red de campus y equipamiento de laboratorios de cómputo.','CONTRATO N° 016-2026',NULL,1135000.00,'Calle Juan XXIII 391, Lambayeque','2025-08-11','2026-01-11',NULL,2,'operativo','2026-10-11 17:52:57','2026-10-11 17:52:57',NULL),(8,'PRY-2026-00008','Sistema de videovigilancia distrital (64 cámaras PTZ)','implementacion',5,NULL,'Videovigilancia','Suministro, instalación y puesta en funcionamiento: Sistema de videovigilancia distrital (64 cámaras PTZ).','CONTRATO N° 017-2023',NULL,1018000.00,'Av. Sáenz Peña 2151, JLO','2023-01-11','2023-06-11',NULL,2,'operativo','2026-10-11 17:52:57','2026-10-11 17:52:57',NULL);
/*!40000 ALTER TABLE `proyectos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_has_permissions`
--

DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_has_permissions`
--

LOCK TABLES `role_has_permissions` WRITE;
/*!40000 ALTER TABLE `role_has_permissions` DISABLE KEYS */;
INSERT INTO `role_has_permissions` VALUES (1,1),(1,2),(2,1),(2,2),(3,1),(3,2),(4,1),(4,2),(5,1),(5,2),(6,1),(6,2),(7,1),(7,2),(8,1),(8,2),(9,1),(9,2),(10,1),(10,2),(11,1),(11,2),(12,1),(12,3),(13,1),(13,2),(14,1),(15,1),(16,1),(19,1),(19,2),(20,1),(20,2),(20,3),(21,1),(21,2),(22,1),(22,2),(22,3),(23,1),(23,2),(24,1),(24,2),(24,3),(25,1),(25,2),(26,1),(26,2),(27,1),(27,2);
/*!40000 ALTER TABLE `role_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Administrador','web','2026-10-11 17:52:56','2026-10-11 17:52:56'),(2,'Supervisor','web','2026-10-11 17:52:56','2026-10-11 17:52:56'),(3,'Técnico','web','2026-10-11 17:52:56','2026-10-11 17:52:56');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('9LcqT8u1dlrKpFT7xrgbCRoINewq91BnibSgVlAV',NULL,'181.176.117.119','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiRTNGcWkyOTExOTJIbFBUQ3lGTHFMbVk0RktnYUFxMFV2NmhpMHdvdSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDU6Imh0dHBzOi8vNDQ5dDh0MHMtODAwMC5icnMuZGV2dHVubmVscy5tcy9sb2dpbiI7czo1OiJyb3V0ZSI7czo1OiJsb2dpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1791782589),('9ZKLRX1CEYS8fS9JiRlfXuy6IYGn13x86sqXyJaf',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.141.0 Chrome/150.0.7871.250 Electron/43.7.7 Safari/537.36','YTo0OntzOjY6Il90b2tlbiI7czo0MDoiN0hUWjFZSGpxS3REYzJNQmZDQU5pSGhadE0xbkdGNHQ4bkRxbUMzciI7czozOiJ1cmwiO2E6MTp7czo4OiJpbnRlbmRlZCI7czoyMToiaHR0cDovL2xvY2FsaG9zdDo4MDAwIjt9czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjc6Imh0dHA6Ly9sb2NhbGhvc3Q6ODAwMC9sb2dpbiI7czo1OiJyb3V0ZSI7czo1OiJsb2dpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1791782818),('ccIWz43k6Hiw9KGI3OUQ2KtT3wVRm2aDYijIAPYw',1,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','YTo2OntzOjY6Il90b2tlbiI7czo0MDoiWXBuajhiemI5UW52c3VmV2xFRGswckROZjBtWnozOFhoQVlSUFA2cCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzM6Imh0dHA6Ly9sb2NhbGhvc3Q6ODAwMC9pbmNpZGVuY2lhcyI7czo1OiJyb3V0ZSI7czoxNzoiaW5jaWRlbmNpYXMuaW5kZXgiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjM6InVybCI7YTowOnt9czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjE3OiJwYXNzd29yZF9oYXNoX3dlYiI7czo2NDoiYTM1YzgxNGQ4NTcyMDU1ZDE3NDg4MWEyMDk4YmY2ZWYxODMxNGViZDU2NTJhMzBjMjc1NjEzMWYxMThjOTc1ZSI7fQ==',1791782831),('fCJSjMe66uJrUsy88Xm2pMPwBccLKXlPIuWTIdnM',1,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','YTo1OntzOjY6Il90b2tlbiI7czo0MDoib2k0VmZVSHlpTjBkYmFPWUdTejRKNEtJY2ltWVFyVW42YUFxUFA1YiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9lbnRpZGFkZXMiO3M6NToicm91dGUiO3M6MTU6ImVudGlkYWRlcy5pbmRleCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjE7czoxNzoicGFzc3dvcmRfaGFzaF93ZWIiO3M6NjQ6ImEzNWM4MTRkODU3MjA1NWQxNzQ4ODFhMjA5OGJmNmVmMTgzMTRlYmQ1NjUyYTMwYzI3NTYxMzFmMTE4Yzk3NWUiO30=',1791778932),('jkiSWhzVu6tzdUOMLhWncjuqkcHtU3fRuLZNhE9E',NULL,'181.67.205.44','Mozilla/5.0 (Linux; Android 14; SM-A556B) AppleWebKit/537.36 (KHTML, like Gecko) Edg/155.0.0.0 Mobile Safari/537.36','YTo0OntzOjY6Il90b2tlbiI7czo0MDoibk80RlV2bmtwR3g1S2lGSm96c0N4eFlTd2l5YTVkRkNNcVNYTjlEMCI7czozOiJ1cmwiO2E6MTp7czo4OiJpbnRlbmRlZCI7czozOToiaHR0cHM6Ly80NDl0OHQwcy04MDAwLmJycy5kZXZ0dW5uZWxzLm1zIjt9czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDU6Imh0dHBzOi8vNDQ5dDh0MHMtODAwMC5icnMuZGV2dHVubmVscy5tcy9sb2dpbiI7czo1OiJyb3V0ZSI7czo1OiJsb2dpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1791781480),('LxhMirf29j9DZRwEULjiSDDP34NdvUtMWxPf2qgE',NULL,'181.67.205.44','WhatsApp/2.23.20.0','YTozOntzOjY6Il90b2tlbiI7czo0MDoiNzNkSXdNMEZpSWJrc0E4RnlPUGJvRDlKcEJKb082VDhKSnBDbXR6OSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDU6Imh0dHBzOi8vNDQ5dDh0MHMtODAwMC5icnMuZGV2dHVubmVscy5tcy9sb2dpbiI7czo1OiJyb3V0ZSI7czo1OiJsb2dpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1791781133),('n3ovIETk35PgAUgM8ZN0FjSrrQuBGx1gv7tRBnVV',1,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','YTo1OntzOjY6Il90b2tlbiI7czo0MDoid3owd3hrOElLWk93b09OWTdYMEU3cXVaems5UXZrQ3hpZElscTUxZiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzY6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9ob2phcy1zZXJ2aWNpbyI7czo1OiJyb3V0ZSI7czoxMToiaG9qYXMuaW5kZXgiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToxO3M6MTc6InBhc3N3b3JkX2hhc2hfd2ViIjtzOjY0OiJhMzVjODE0ZDg1NzIwNTVkMTc0ODgxYTIwOThiZjZlZjE4MzE0ZWJkNTY1MmEzMGMyNzU2MTMxZjExOGM5NzVlIjt9',1791781047),('PiWlAAoBeR1G9ej06aNexA9InXm8ovmWUBQgDEMs',NULL,'181.67.205.44','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiUHFCYVh2RzhDaXJWdjEweENVbWx5MjFrUXYzWFV5Tldtc0ZUYVNDMSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDU6Imh0dHBzOi8vNDQ5dDh0MHMtODAwMC5icnMuZGV2dHVubmVscy5tcy9sb2dpbiI7czo1OiJyb3V0ZSI7czo1OiJsb2dpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1791781228),('vxPFODFvu2QECrpMjkxRGyCXPkkLiaHlNsrkacwK',1,'181.67.205.44','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36','YTo1OntzOjY6Il90b2tlbiI7czo0MDoiYVlIS0Jua2gzZ1ZlQXAyU3NaRndqTGZ0UDl0SkIwTlNWY0xiWFI5MSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDk6Imh0dHBzOi8vNDQ5dDh0MHMtODAwMC5icnMuZGV2dHVubmVscy5tcy9kYXNoYm9hcmQiO3M6NToicm91dGUiO3M6OToiZGFzaGJvYXJkIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjE3OiJwYXNzd29yZF9oYXNoX3dlYiI7czo2NDoiYTM1YzgxNGQ4NTcyMDU1ZDE3NDg4MWEyMDk4YmY2ZWYxODMxNGViZDU2NTJhMzBjMjc1NjEzMWYxMThjOTc1ZSI7fQ==',1791781529),('ZGdJh3AoDfaRFdyzvpOmEYSUytzC9Tgaul38Alis',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.141.0 Chrome/150.0.7871.250 Electron/43.7.7 Safari/537.36','YTo0OntzOjY6Il90b2tlbiI7czo0MDoiamI5amdVZGM5Sm1zV3dhb1plN1hTNFpacmRydzRoMnBRYXM4MVVHYSI7czozOiJ1cmwiO2E6MTp7czo4OiJpbnRlbmRlZCI7czoyMToiaHR0cDovLzEyNy4wLjAuMTo4MDAwIjt9czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjc6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9sb2dpbiI7czo1OiJyb3V0ZSI7czo1OiJsb2dpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1791779940),('zYEw2H59eOpk4II4lPEbFBRQaMnZV3ciH6mWwmzn',1,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/154.0.0.0 Safari/537.36','YTo1OntzOjY6Il90b2tlbiI7czo0MDoiWXBGR3dLRTl2Z3Exd1FMUU5CTExmVUozZWFSZUhka1NzZkt0MDZ0biI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDE6Imh0dHA6Ly9sb2NhbGhvc3Q6ODAwMC9hZG1pbi9jb25maWd1cmFjaW9uIjtzOjU6InJvdXRlIjtzOjE4OiJjb25maWd1cmFjaW9uLmVkaXQiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToxO3M6MTc6InBhc3N3b3JkX2hhc2hfd2ViIjtzOjY0OiJhMzVjODE0ZDg1NzIwNTVkMTc0ODgxYTIwOThiZjZlZjE4MzE0ZWJkNTY1MmEzMGMyNzU2MTMxZjExOGM5NzVlIjt9',1791781586);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `dni` varchar(15) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `cargo` varchar(100) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `ultimo_acceso` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Administrador del Sistema','admin@techquk.com.pe',NULL,'$2y$12$JFRPMUEo6PXddCIrW3S0ouYVPqpkYA7BfK0gXybi6GM3mCcNdwkdS',NULL,NULL,'Administrador',1,'2026-10-12 05:20:29',NULL,'2026-10-11 17:52:56','2026-10-12 05:20:29'),(2,'Robert Ordóñez Bautista','supervisor@techquk.com.pe',NULL,'$2y$12$Bzj8TNxrQ4RiWEgzUVzuaepMXFz6xlUJRo3kMJcWewb3S2Xt1JRz.',NULL,'988860342','Jefe de Proyectos',1,'2026-10-12 00:59:07',NULL,'2026-10-11 17:52:57','2026-10-12 00:59:07'),(3,'Luis Paredes Chapoñán','tecnico1@techquk.com.pe',NULL,'$2y$12$yOV.p0GO2yRWIopeQafwGePmwSRz8RWGwOqK7Ra4vzXjpJkfkI2NW',NULL,'930448497','Técnico de campo',1,'2026-10-11 22:51:45',NULL,'2026-10-11 17:52:57','2026-10-11 22:51:45'),(4,'Jorge Vílchez Santisteban','tecnico2@techquk.com.pe',NULL,'$2y$12$iUrj1CQhBWUlFTeIXKbbt.p.HV3edhI3htzr/cPs2xDtGwUkJSXxa',NULL,'966637913','Técnico especialista en redes',1,'2026-10-12 00:18:23',NULL,'2026-10-11 17:52:57','2026-10-12 00:18:23');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-12  0:27:16
