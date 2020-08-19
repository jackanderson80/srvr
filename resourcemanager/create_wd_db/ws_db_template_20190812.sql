-- MySQL dump 10.13  Distrib 5.7.12, for Win64 (x86_64)
--
-- Host: localhost    Database: ws_db
-- ------------------------------------------------------
-- Server version	5.7.16-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `document`
--

DROP TABLE IF EXISTS `document`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hash` varchar(255) DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `keywords` varchar(255) DEFAULT NULL,
  `author_user_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `org_unit_id` int(11) NOT NULL,
  `created_date` datetime NOT NULL,
  `modified_date` datetime NOT NULL,
  `content` longtext,
  `allow_inherit_parent_permissions` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`,`account_id`,`org_unit_id`),
  UNIQUE KEY `id_UNIQUE` (`id`),
  KEY `Index on hash` (`hash`)
) ENGINE=InnoDB AUTO_INCREMENT=1130 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `document_security`
--

DROP TABLE IF EXISTS `document_security`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_security` (
  `relationship_id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) DEFAULT NULL,
  `security_group_id` int(11) DEFAULT NULL,
  `permissions_mask` varchar(15) DEFAULT NULL,
  `granted_by_userid` int(11) DEFAULT NULL,
  `inherited_by_relationship_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`relationship_id`),
  UNIQUE KEY `relationship_id_UNIQUE` (`relationship_id`),
  KEY `FK_docid__idx` (`document_id`),
  CONSTRAINT `FK_docsec_doc` FOREIGN KEY (`document_id`) REFERENCES `document` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=2897 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `document_tree`
--

DROP TABLE IF EXISTS `document_tree`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_tree` (
  `document_id` int(11) DEFAULT NULL,
  `parent_document_id` int(11) DEFAULT NULL,
  `path` varchar(4096) DEFAULT NULL,
  KEY `fk_doc_id_idx` (`document_id`),
  KEY `fk_parent_doc_id_idx` (`parent_document_id`),
  CONSTRAINT `fk_doc_id` FOREIGN KEY (`document_id`) REFERENCES `document` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `fk_parent_doc_id` FOREIGN KEY (`parent_document_id`) REFERENCES `document` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_security_group_cache`
--

DROP TABLE IF EXISTS `user_security_group_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_security_group_cache` (
  `user_id` int(11) DEFAULT NULL,
  `security_group_id` int(11) DEFAULT NULL,
  `is_primary_security_group` bit(1) DEFAULT b'0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping events for database 'ws_db'
--

--
-- Dumping routines for database 'ws_db'
--
/*!50003 DROP FUNCTION IF EXISTS `fn_CheckUserDocPermission` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_CheckUserDocPermission`(
	user_id int,
    document_id int,
    permission varchar(255)
    ) RETURNS varchar(255) CHARSET utf8
BEGIN
	DECLARE has_permissions_itself INTEGER DEFAULT (
		SELECT count(*)
		FROM document d
		INNER JOIN document_security ds ON d.id = ds.document_id
		
		INNER JOIN user_security_group_cache usg on usg.security_group_id = ds.security_group_id
		WHERE d.id = document_id AND
		usg.user_id = user_id AND
		(ds.permissions_mask LIKE CONCAT('%', UPPER(permission), '%'))
	);
    
    DECLARE allow_inherit_parent_permissions BINARY(1) DEFAULT (SELECT d.allow_inherit_parent_permissions FROM document d WHERE d.id = document_id);
    
    DECLARE parent_doc_id INTEGER DEFAULT -1;
    
    DECLARE has_inherited_permission INTEGER DEFAULT 0;
    
    DECLARE path VARCHAR(4096) DEFAULT (SELECT path FROM document_tree dt WHERE dt.document_id = document_id);
	DECLARE i INTEGER DEFAULT 2;
    DECLARE len INTEGER DEFAULT LENGTH(path);
	DECLARE tokenEnd INTEGER DEFAULT 0;
    
    DECLARE token varchar(200) DEFAULT '';
    DECLARE target varchar(255) DEFAULT '';
    
    DECLARE output varchar(4096) DEFAULT '';
    
    IF has_permissions_itself > 0 THEN
		RETURN 'self';
	ELSEIF path IS NOT NULL AND allow_inherit_parent_permissions = 1 THEN
    
		label1: LOOP
			IF i >= len THEN
				LEAVE label1;
			END IF;
			
			SET tokenEnd = LOCATE('/', path, i);
			
			IF tokenEnd = 0 THEN
			   SET tokenEnd = len + 1;
			END IF;
			
			SET token = SUBSTRING(path, i, tokenEnd - i);
            SET parent_doc_id = (SELECT CAST(token as SIGNED));
            
			SET has_inherited_permission = (SELECT count(*)
				FROM document d
				INNER JOIN document_security ds ON d.id = ds.document_id
				
				INNER JOIN user_security_group_cache usg on usg.security_group_id = ds.security_group_id
				WHERE d.id = parent_doc_id
                AND	usg.user_id = user_id AND
				(ds.permissions_mask LIKE CONCAT('%', UPPER(permission), '%'))
            );
            
            IF has_inherited_permission > 0 THEN
				SET output = CONCAT('inherited from: ', token);
                LEAVE label1;
			ELSE            
				IF LENGTH(output) > 0 THEN
					SET output = CONCAT(output, ',');
				END IF;
				
				SET output = CONCAT(output, token);
				  
				SET i = tokenEnd + 1;
            END IF;
			
			ITERATE label1;
		  
		END LOOP label1;

		IF has_inherited_permission > 0 THEN
			RETURN output;
		ELSE
			RETURN 'none';
		END IF;
    ELSE
		RETURN 'none';
    END IF;

RETURN 'none';
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_GetUserDocPermissionsMask` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_GetUserDocPermissionsMask`(
	user_id int,
    document_id int
    ) RETURNS varchar(255) CHARSET utf8
BEGIN
    DECLARE permission varchar(255) DEFAULT '';
    
    IF fn_CheckUserDocPermission(user_id, document_id, 'R') <> 'none' THEN
		SET permission = CONCAT(permission, 'R');
    END IF;

    IF fn_CheckUserDocPermission(user_id, document_id, 'W') <> 'none' THEN
		SET permission = CONCAT(permission, 'W');
    END IF;

    IF fn_CheckUserDocPermission(user_id, document_id, 'D') <> 'none' THEN
		SET permission = CONCAT(permission, 'D');
    END IF;

    IF fn_CheckUserDocPermission(user_id, document_id, 'S') <> 'none' THEN
		SET permission = CONCAT(permission, 'S');
    END IF;

	RETURN permission;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `prc_AddDocToParentDoc` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `prc_AddDocToParentDoc`(
	user_id int,
    account_id int,
	document_id int,
	parent_document_id int
)
BEGIN
	DECLARE parent_doc_path VARCHAR(4096) DEFAULT (
		SELECT path from document_tree WHERE document_tree.document_id = parent_document_id
    );
    
    DECLARE doc_is_in_account INT DEFAULT (SELECT count(*) FROM document where document.id=document_id and document.account_id = account_id);
    DECLARE parent_doc_is_in_account INT DEFAULT (SELECT count(*) FROM document where document.id=parent_document_id and document.account_id = account_id);
    
    DECLARE already_has_parent INTEGER DEFAULT (SELECT count(*) FROM document_tree dt WHERE dt.document_id = document_id);

    DECLARE already_exist_inverse_or_other_relationship INTEGER DEFAULT 
    (
		SELECT count(*) FROM document_tree dt
        WHERE (dt.document_id = parent_document_id AND dt.parent_document_id = document_id)
        OR
        -- the document is one of the parent document's parents
        (dt.document_id = parent_document_id AND dt.path LIKE CONCAT('%/', document_id, '%'))
        OR
        -- double chech if this rule is overrestrictive
        (dt.path LIKE CONCAT('%/', document_id, '%') AND dt.path LIKE CONCAT('%/', parent_document_id, '%'))
	);
           
	IF fn_CheckUserDocPermission(user_id, document_id, 'S') = 'none' THEN
		SELECT -1 as 'result';
	ELSEIF fn_CheckUserDocPermission(user_id, parent_document_id, 'W') = 'none' THEN
		SELECT -2 as 'result';
	ELSEIF document_id IS NULL OR parent_document_id IS NULL OR parent_document_id = document_id THEN
		SELECT -3 as 'result';  
	ELSEIF already_has_parent > 0 THEN
		-- enfore that a doc may have only 1 parent
		SELECT -4 as 'result';
	ELSEIF already_exist_inverse_or_other_relationship > 0 THEN
		SELECT -5 as 'result';
	ELSEIF doc_is_in_account = 0 OR parent_doc_is_in_account = 0 THEN
		SELECT -6 as 'result';
	ELSE	       
        IF parent_doc_path IS NULL THEN
			SET parent_doc_path = '';
		END IF;
        
		SET parent_doc_path = CONCAT(parent_doc_path, '/', parent_document_id);
        
		START TRANSACTION;

        UPDATE document_tree SET path = CONCAT(parent_doc_path, path)
        WHERE path LIKE CONCAT('%/', document_id, '%');
        
        INSERT INTO document_tree VALUES (document_id, parent_document_id, parent_doc_path);        
        
        COMMIT;
	    
		/*
        --------------------------------------------
        IMPORTANT:
        
        CALL prc_PropagateSecurityGroupsPermissionsToParentDocs(user_id, document_id);
        
        --------------------------
        This procedure is removed due to a MySQL bug with cursors and mysqli driver.
        The procedure used to select all groups of the child doc with 'R' or 'I' mask,
        open a cursor on the result set
        and call prc_SetImplicitReadPermissionsToParentDocs for each of them.
        
        Instead the PHP code which calls AddDocToParentDoc is calling 
        GetDocSecurityInfo of the child document and then for each securty group
        that has 'I' or 'R' permission it calls prc_SetImplicitReadPermissionsToParentDoc
        
        This not ideal bug works.
        
        The respective cleanup in prc_RemoveDocFromParentDocs is calling 
        prc_ClearImplicitReadPermissionFromParentDocs. It works from within MySQL because
        it is not using cursors.
        */
        
        
        SELECT 1 as 'result';	
    END IF;
    
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `prc_ClearDocSecurityGroupPermissions` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `prc_ClearDocSecurityGroupPermissions`(
	grantor_user_id int,
	security_group_id int,
	document_id int
)
BEGIN
	IF fn_CheckUserDocPermission(grantor_user_id, document_id, 'S') = 'none' THEN
		SELECT -1 as 'result';
	ELSE
		IF (security_group_id IS NULL) THEN
			SELECT -2 as 'result';
		ELSE
			-- remove 'I' permissins to this security group passed from this doc to parent docs
			CALL prc_ClearImplicitReadPermissionFromParentDocs(security_group_id, document_id);
            
			DELETE FROM document_security
			WHERE 
				document_security.document_id = document_id AND
                document_security.security_group_id = security_group_id;

			SELECT 1 as 'result';
		END IF;
	END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `prc_ClearImplicitReadPermissionFromParentDocs` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `prc_ClearImplicitReadPermissionFromParentDocs`(
    security_group_id int,
    document_id int
)
BEGIN
	DECLARE relationship_id INTEGER DEFAULT (
			SELECT ds.relationship_id FROM document_security ds
            WHERE 
				ds.document_id AND 
                ds.security_group_id = security_group_id AND
                ds.inherited_by_relationship_id IS NULL AND
                ds.permissions_mask LIKE '%R%'
			LIMIT 1
	);
    
	DELETE FROM document_security
	WHERE
		document_security.security_group_id = security_group_id AND
		document_security.inherited_by_relationship_id = relationship_id AND
		document_security.permissions_mask = 'I';
	
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `prc_ClearUserSecurityGroupsCache` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `prc_ClearUserSecurityGroupsCache`(
user_id int
)
BEGIN
	DELETE FROM user_security_group_cache WHERE user_security_group_cache.user_id = user_id;
    SELECT 1 as 'status';
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `prc_DeleteDocSelf` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `prc_DeleteDocSelf`(
	user_id int,    
    account_id int,
	delete_doc_id int
)
BEGIN
    DECLARE doc_exists INT DEFAULT (SELECT count(*) FROM document WHERE id = delete_doc_id);
    DECLARE parent_doc_id INT DEFAULT (SELECT parent_document_id FROM document_tree dt WHERE dt.document_id = delete_doc_id);
    DECLARE doc_is_in_account INT DEFAULT (SELECT count(*) FROM document where document.id=delete_doc_id and document.account_id = account_id);
    
	IF doc_exists = 0 THEN
		SELECT -1 as 'deleteCount'; -- document does not exist
    ELSEIF doc_is_in_account = 0 THEN
		SELECT -2 as 'deleteCount'; 
	ELSEIF fn_CheckUserDocPermission(user_id, delete_doc_id, 'D') = 'none' THEN
		SELECT -3 as 'deleteCount'; -- insufficient permissions
	ELSE
		IF parent_doc_id IS NOT NULL THEN
			CALL prc_RemoveDocFromParentDoc(user_id, account_id, delete_doc_id, parent_doc_id);
        END IF;
		
		START TRANSACTION;

        DELETE FROM document_security WHERE document_security.document_id = delete_doc_id;
        
        DELETE FROM document_tree WHERE document_tree.document_id = delete_doc_id;
		
		DELETE FROM document WHERE id = delete_doc_id;
		
		SELECT ROW_COUNT() 'deletedCount';

        COMMIT;
        
	END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `prc_GetChildDocs` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `prc_GetChildDocs`(
	user_id int,
	parent_document_id int
)
BEGIN
	DECLARE anon_user_id INT DEFAULT -1;
    
	IF fn_CheckUserDocPermission(user_id, parent_document_id, 'R') = 'none' THEN
		SELECT
			d.id, 
			d.hash,
			d.type,        
			d.name,
			d.description,
			d.keywords,
			d.created_date,
			d.modified_date,
			'' as 'permissions_mask',
			d.author_user_id as 'author_id',
            d.account_id,
            d.org_unit_id
		FROM document d
        WHERE d.id = -1;
	ELSE
		SELECT
			d.id, 
			d.hash,
			d.type,        
			d.name,
			d.description,
			d.keywords,
			d.created_date,
			d.modified_date,
			fn_GetUserDocPermissionsMask(user_id, d.id) as 'permissions_mask',
			d.author_user_id as 'author_id',
            d.account_id,
            d.org_unit_id
		FROM document d
        INNER JOIN document_tree dt on dt.document_id = d.id
		
		
		
		WHERE
			d.author_user_id <> anon_user_id AND
			dt.parent_document_id = parent_document_id 
            
			
			
		ORDER BY id ASC, 'permissions_mask' DESC;
    
    END IF;

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `prc_GetDoc` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `prc_GetDoc`(
	user_id int,
    account_id int,
	document_id int,
    document_hash varchar(255)
)
BEGIN
	DECLARE anon_user_id INT DEFAULT -1;
    DECLARE is_anon_doc INT DEFAULT 0;
    DECLARE is_shared_everyone INT DEFAULT 0;
    DECLARE docId INT DEFAULT -1;
    DECLARE permissions_mask VARCHAR(255) DEFAULT '';
	DECLARE everyone_permissions_mask VARCHAR(255) DEFAULT '';
        
    SET docId = (SELECT id FROM document WHERE 
    (
		(document_id <> -1 AND document.id = document_id) OR 
        (document_hash <> '' AND document.hash = document_hash)
	)
    );
    
    SET is_shared_everyone = (		
		SELECT count(*) FROM document_security ds
		WHERE ds.document_id = docId AND ds.security_group_id = 1
	);
    
    SET is_anon_doc = (	SELECT count(*)
		FROM document d
		WHERE 
			d.id = docId AND
            d.author_user_id = anon_user_id
    );
    
    SET permissions_mask = (SELECT fn_GetUserDocPermissionsMask(user_id, document_id));
    SET everyone_permissions_mask  = (SELECT fn_GetUserDocPermissionsMask(anon_user_id, document_id));
	
    
    IF is_anon_doc > 0 THEN
		SET is_shared_everyone = 1;
        
 		SELECT d.*, 'RN' as 'permissions_mask', is_shared_everyone as 'shared_everyone'
		FROM document d
		WHERE d.id = docId AND d.account_id = account_id;
    ELSE
		IF everyone_permissions_mask <> '' THEN
			SET is_shared_everyone = 1;
        END IF;
        
        IF (permissions_mask <> '')
        THEN
			SELECT 
				d.*,
				permissions_mask as 'permissions_mask',
				is_shared_everyone as 'shared_everyone'
			FROM document d
			WHERE d.id = docId  AND d.account_id = account_id;
		ELSE
			SELECT 
				d.*,
				'' as 'permissions_mask',
				0 as 'shared_everyone'
			FROM document d
			WHERE d.id = -1000;  -- select nothing
        END IF;
    END IF;
    
    
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `prc_GetDocPath` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `prc_GetDocPath`(
	document_id int
)
BEGIN
	SELECT path FROM document_tree dt
    WHERE dt.document_id = document_id;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `prc_GetDocSecurityInfo` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `prc_GetDocSecurityInfo`(
	document_id int
)
BEGIN
	SELECT 
		ds.relationship_id,
		ds.document_id ,
        ds.security_group_id,
        ds.permissions_mask,
        ds.granted_by_userid,
        ds.inherited_by_relationship_id
	FROM document_security ds
	WHERE ds.document_id = document_id;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `prc_GetDocsList` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `prc_GetDocsList`(
	user_id int,
    account_id int,
    document_type varchar(255))
BEGIN
	DECLARE anon_user_id INT DEFAULT -1;

	SELECT
		d.id, 
		d.hash,
        d.type,        
		d.name,
		d.description,
		d.keywords,
        d.author_user_id,
        d.account_id,
        d.org_unit_id,
		d.created_date,
		d.modified_date,
        d.content,
        d.allow_inherit_parent_permissions,
		ds.permissions_mask
	FROM document d
	INNER JOIN document_security ds on ds.document_id = d.id
	INNER JOIN user_security_group_cache usg on usg.security_group_id = ds.security_group_id
	WHERE 
		usg.user_id = user_id AND
		(ds.permissions_mask LIKE '%R%' OR ds.permissions_mask LIKE '%I%') AND
        d.account_id = account_id AND
		d.type = document_type
	ORDER BY d.id;

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `prc_isDocSharedEveryone` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `prc_isDocSharedEveryone`(
	document_id int
)
BEGIN
	DECLARE anon_user_id INT DEFAULT -1;
    DECLARE is_anon_doc INT DEFAULT 0;

	DECLARE is_shared INTEGER DEFAULT (
		SELECT count(*) FROM document_security ds
		where ds.document_id = document_id AND ds.security_group_id = 1
	);
	
	SET anon_user_id = (SELECT id FROM user WHERE email = 'anonymous@jqwidgets.com');
    
    SET is_anon_doc = (	SELECT count(*)
		FROM document d
		WHERE 
			d.id = document_id AND
            d.author_user_id = anon_user_id
    );    

	IF (is_shared > 0 OR is_anon_doc > 0) THEN
		SELECT 1 as 'result';
	ELSE
		SELECT -1 as 'result';
	END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `prc_RemoveDocFromParentDoc` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `prc_RemoveDocFromParentDoc`(
	user_id int,
    account_id int,
	document_id int,
	parent_document_id int
)
BEGIN
	DECLARE relationship_exist INT DEFAULT (SELECT count(*) FROM document_tree dt WHERE dt.document_id = document_id AND dt.parent_document_id = parent_document_id);
	DECLARE document_search_path varchar(4096) default CONCAT('/', parent_document_id, '/', document_id);
	DECLARE parent_path varchar(4096) default CONCAT('/', parent_document_id);
    
    DECLARE doc_is_in_account INT DEFAULT (SELECT count(*) FROM document where document.id=document_id and document.account_id = account_id);
    DECLARE parent_doc_is_in_account INT DEFAULT (SELECT count(*) FROM document where document.id=parent_document_id and document.account_id = account_id);
 
    IF doc_is_in_account = 0 OR parent_doc_is_in_account = 0 THEN
		SELECT -4 as 'result';
	ELSEIF fn_CheckUserDocPermission(user_id, document_id, 'S') = 'none' AND
		fn_CheckUserDocPermission(user_id, document_id, 'D') = 'none' THEN
		SELECT -1 as 'result';
	ELSEIF fn_CheckUserDocPermission(user_id, parent_document_id, 'W') = 'none' AND
		fn_CheckUserDocPermission(user_id, document_id, 'D') = 'none' THEN
		SELECT -2 as 'result';
	ELSEIF relationship_exist <> 1 THEN
		SELECT -3 as 'result'; 
	ELSE
        CALL prc_RemoveSecurityGroupsPermissionsPropagatedToParentDocs(document_id);
    
		START TRANSACTION;
                
		DELETE FROM document_tree
        WHERE 
			document_tree.document_id = document_id AND
			document_tree.parent_document_id = parent_document_id;

        UPDATE document_tree 
			SET path = substring(path, IF (position(parent_path in path) > 0, position(parent_path in path) + length(parent_path) ,1)  )
		WHERE path LIKE CONCAT('%', document_search_path, '%');
        
        SELECT 1 as 'result';
        
        COMMIT;
    END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `prc_RemoveSecurityGroupsPermissionsPropagatedToParentDocs` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `prc_RemoveSecurityGroupsPermissionsPropagatedToParentDocs`(
	document_id int
)
BEGIN
	DELETE ds1 FROM document_security ds1 JOIN
    document_security ds2 ON ds1.inherited_by_relationship_id = ds2.relationship_id
    WHERE ds2.document_id = document_id;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `prc_SaveDoc` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `prc_SaveDoc`(
	user_id int,
    account_id int,
    org_unit_id int,
	document_id int,
	docname varchar(255),
	doctype varchar(255),
    allow_inherit_parent_permissions binary(1),
	description varchar(255),
	keywords varchar(255),
	content longtext,
    SEO_name varchar(255)
)
BEGIN
	DECLARE secgroup_id INT DEFAULT -1;
	DECLARE current_author INTEGER DEFAULT -1;
	DECLARE new_doc_id INTEGER DEFAULT -1;
	DECLARE anon_user_id INT DEFAULT -1;
    DECLARE ver_value VARCHAR(255);
    DECLARE ver_seqnum INTEGER DEFAULT -1;
    DECLARE doc_exists_in_same_account INTEGER DEFAULT (SELECT COUNT(*) FROM document where document.id = document_id AND document.account_id = account_id);

	SET SEO_name = LOWER(SEO_name);
            
	IF user_id <> anon_user_id THEN    
		SET secgroup_id = (
			SELECT security_group_id 
			FROM user_security_group_cache usg
			WHERE usg.user_id = user_id AND usg.is_primary_security_group = 1
		);
    END IF;
	
	IF (SELECT (LENGTH(SEO_name)) = 0) THEN
		SET SEO_name = (SELECT REPLACE(UUID(),'-',''));
	END IF;
    
    WHILE (SELECT COUNT(*) FROM document WHERE `hash` = SEO_name) > 0
    DO
		SET ver_value = (SELECT SUBSTRING_INDEX(SEO_name, '-ver-', -1));
        IF ver_value = SEO_name THEN -- no version 
			SET ver_seqnum = 2;
		ELSE
			SET ver_seqnum = (ver_value * 1) + 1;
            SET SEO_name = (SELECT SUBSTRING_INDEX(SEO_name, '-ver-', 1)); -- take the first substring
        END IF;
        
        SET SEO_name = (SELECT CONCAT(SEO_name, '-ver-', ver_seqnum)); -- update the version
    END WHILE;
    
    -- enforce creating new doc / fork in case of anonymous user 
	IF user_id = anon_user_id OR user_id = -1 OR user_id IS NULL THEN
		SET document_id = -1;
		SET user_id = anon_user_id;
    END IF;
    
    -- missing primary security group
    IF user_id <> anon_user_id AND secgroup_id IS NULL THEN
		SELECT -1 AS `doc_id`, -1 as `hash`;
    ELSE
		IF document_id = -1 THEN
			-- new document
			INSERT INTO `document` (
				`hash`,
				`type`,
				`name`,
				`description`,
				`keywords`,
				`author_user_id`,
                `account_id`,
                `org_unit_id`,
				`created_date`,
				`modified_date`,
				`content`,
				`allow_inherit_parent_permissions`) 
			VALUES(
				SEO_name, 
				doctype, 
				docname,
				description,
				keywords,
				user_id,
                account_id,
                org_unit_id,
				UTC_TIMESTAMP,
				UTC_TIMESTAMP,
				content,
				allow_inherit_parent_permissions);

			SET new_doc_id = LAST_INSERT_ID();
			
			INSERT INTO document_security(
				`relationship_id`,
				`document_id`,
				`security_group_id`,
				`permissions_mask`,
				`granted_by_userid`,
				`inherited_by_relationship_id`)
			VALUES(
				NULL,
				new_doc_id,
				secgroup_id,
				'RWDS',
				user_id,
				NULL);

			SELECT id as `doc_id`, `hash` FROM
			document WHERE id = new_doc_id;

		ELSEIF doc_exists_in_same_account = 0 THEN
			SELECT -3 AS `doc_id`, -3 as `hash`;
        ELSE
			
			IF fn_CheckUserDocPermission(user_id, document_id, 'W') <> 'none' THEN
				UPDATE document SET
					`type` = doctype,
					`name` = docname,
					`description` = description,
					`keywords` = keywords,
					`modified_date` = UTC_TIMESTAMP,
					`content` = content,
					`allow_inherit_parent_permissions` = allow_inherit_parent_permissions
				WHERE id = document_id;
				
				SELECT id as `doc_id`, `hash` FROM
				document WHERE id = document_id;            
			ELSE
				SELECT -2 AS `doc_id`, -2 as `hash`;
			END IF;
		END IF;
    END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `prc_SetDocSecurityGroupPermissions` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `prc_SetDocSecurityGroupPermissions`(
	grantor_user_id int,
	security_group_id int,
	document_id int,
	permissions_mask varchar(15)
)
BEGIN
	DECLARE relationship_id INTEGER DEFAULT NULL;

    -- add write in case of delete
    IF permissions_mask LIkE '%D%' AND permissions_mask NOT LIKE '%W%' THEN
		SET permissions_mask = CONCAT('W', permissions_mask);
    END IF;
    
    -- add read in case of write
    IF permissions_mask LIkE '%W%' AND permissions_mask NOT LIKE '%R%' THEN
		SET permissions_mask = CONCAT('R', permissions_mask);
    END IF;
    
	IF fn_CheckUserDocPermission(grantor_user_id, document_id, 'S') = 'none' THEN
		SELECT -1 as 'result';
	ELSE
		IF (security_group_id IS NULL) THEN
			SELECT -2 as 'result';
		ELSE	               
			-- remove 'I' permissions to this security group passed from this doc to parent docs
			CALL prc_ClearImplicitReadPermissionFromParentDocs(security_group_id, document_id);

			DELETE FROM document_security
			WHERE 
				document_security.document_id = document_id AND
                document_security.security_group_id = security_group_id;

			IF (permissions_mask <> '') THEN			
				INSERT INTO document_security VALUES (
					NULL,
					document_id,
                    security_group_id,
                    permissions_mask,
                    grantor_user_id,
                    NULL);
				
				IF (permissions_mask LIKE '%R%') THEN
					SET relationship_id = (SELECT ds.relationship_id FROM document_security ds
                    WHERE ds.document_id = document_id AND ds.security_group_id = security_group_id
                    AND ds.permissions_mask = permissions_mask AND ds.granted_by_userid = grantor_user_id
                    LIMIT 1
                    );
                    
					CALL prc_SetImplicitReadPermissionToParentDocs(grantor_user_id, security_group_id, document_id, relationship_id);
				END IF;
			END IF;

			SELECT 1 as 'result';
		END IF;
	END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `prc_SetImplicitReadPermissionToParentDocs` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `prc_SetImplicitReadPermissionToParentDocs`(
	grantor_user_id int,
    security_group_id int,
    document_id int,
    inherited_by_relationship_id int
)
BEGIN
	DECLARE parent_docs_path VARCHAR(8192) DEFAULT (SELECT path FROM document_tree dt WHERE dt.document_id = document_id);
	DECLARE i INTEGER DEFAULT 1;
    DECLARE len INTEGER DEFAULT LENGTH(parent_docs_path);
	DECLARE tokenStart INTEGER DEFAULT 0;
	DECLARE tokenEnd INTEGER DEFAULT 0;
    DECLARE token varchar(200) DEFAULT '';
    DECLARE parent_doc_id INTEGER DEFAULT -1;

	DECLARE out_path VARCHAR(8192) DEFAULT '';
        
    IF parent_docs_path IS NULL THEN
		SET parent_docs_path = '';
    END IF;

	label1: LOOP
		IF i >= len THEN
			LEAVE label1;
		END IF;
        
        SET tokenStart = LOCATE('/', parent_docs_path, i);
        
        IF tokenStart = 0 THEN
		   LEAVE label1;
		END IF;
        
        SET tokenStart = tokenStart + 1;

        SET tokenEnd = LOCATE('/', parent_docs_path, tokenStart);
        IF tokenEnd = 0 THEN
			SET tokenEnd = len + 1;
        END IF;
        
		SET token = SUBSTRING(parent_docs_path, tokenStart, tokenEnd - tokenStart);
          
		SET i = tokenEnd;

		IF LENGTH(token) > 0 THEN
        
			
        
            SET parent_doc_id = CONVERT(token, SIGNED INTEGER);
			
            DELETE FROM document_security
            WHERE
				document_security.document_id = parent_doc_id AND
				document_security.security_group_id = security_group_id AND
                document_security.inherited_by_relationship_id = inherited_by_relationship_id AND 
                document_security.permissions_mask = 'I';
			
            INSERT document_security VALUES(NULL, parent_doc_id, security_group_id, 'I', grantor_user_id, inherited_by_relationship_id);
            
            
		END IF;
        
		ITERATE label1;
      
	END LOOP label1;    
    
    SELECT 1 as 'result';
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `prc_SetUserSecurityGroupsCache` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `prc_SetUserSecurityGroupsCache`(
user_id int,
secgroups varchar(8192),
primary_secgroup_flag bit(1)
)
BEGIN
	DECLARE i INTEGER DEFAULT 1;
    DECLARE len INTEGER DEFAULT LENGTH(secgroups);
	DECLARE tokenEnd INTEGER DEFAULT 0;
    
    DECLARE token varchar(200) DEFAULT '';

	DELETE FROM user_security_group_cache WHERE user_security_group_cache.user_id = user_id;
			
	label1: LOOP
		IF i >= len THEN
			LEAVE label1;
		END IF;
		
		SET tokenEnd = LOCATE(';', secgroups, i);
		
		IF tokenEnd = 0 THEN
		   SET tokenEnd = len + 1;
		END IF;
		
		SET token = SUBSTRING(secgroups, i, tokenEnd - i);
				  
		SET i = tokenEnd + 1;

		IF LENGTH(token) > 0 THEN
			INSERT INTO user_security_group_cache VALUES (user_id, CONVERT(token, UNSIGNED INTEGER), primary_secgroup_flag);
		END IF;
		
		ITERATE label1;
	  
	END LOOP label1;
    
    SELECT 1 as 'status';
		
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `prc_shareDocEveryone` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `prc_shareDocEveryone`(
	user_id int,
	document_id int
)
BEGIN
	DECLARE is_shared INTEGER DEFAULT (
		SELECT count(*) FROM document_security ds
		where ds.document_id = document_id AND ds.security_group_id = 1
	);
	
	IF (is_shared > 0) THEN
		SELECT 1 as 'result';
	ELSEIF fn_CheckUserDocPermission(grantor_user_id, document_id, 'S') = 'none' THEN
		SELECT -1 as 'result';    
    ELSE
		INSERT INTO `document_security` (
			`document_id`,
			`security_group_id`,
			`permissions_mask`,
			`granted_by_userid`)
		 VALUES (document_id, 1, 'R', user_id);

		SELECT 1 as 'result';
	END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `prc_unshareDocEveryone` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `prc_unshareDocEveryone`(
	user_id int,
	document_id int
)
BEGIN
	DECLARE is_shared INTEGER DEFAULT (
		SELECT count(*) FROM document_security ds
		where ds.document_id = document_id AND ds.security_group_id = 1
	);
	
	IF (is_shared <> 1) THEN
		SELECT 1 as 'result';
	ELSEIF fn_CheckUserDocPermission(grantor_user_id, document_id, 'S') = 'none' THEN
		SELECT -1 as 'result';        
	ELSE
		DELETE FROM document_security
		WHERE document_security.document_id = document_id AND 
			  document_security.security_group_id = 1;

		SELECT 1 as 'result';
	END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `tokenize` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `tokenize`(
  txt longtext
)
BEGIN
	DECLARE i INTEGER DEFAULT 1;
    DECLARE len INTEGER DEFAULT LENGTH(txt);
	DECLARE tokenEnd INTEGER DEFAULT 0;
    
    DECLARE longtxt longtext DEFAULT '';
    DECLARE token varchar(200) DEFAULT '';
    DECLARE email varchar(200) DEFAULT '';
    DECLARE mask varchar(200) DEFAULT '';
        
	label1: LOOP
		IF i >= len THEN
			LEAVE label1;
		END IF;
        
        SET tokenEnd = LOCATE('|', txt, i);
        
        IF tokenEnd = 0 THEN
		   SET tokenEnd = len + 1;
		END IF;
        
		SET token = SUBSTRING(txt, i, tokenEnd - i);
        SET email = SUBSTRING(token, 1, LOCATE(';', token, 1)-1);
        SET mask = SUBSTRING(token, LOCATE(';', token, 1) + 1, LENGTH(token) + 1);
        
        SET longtxt = CONCAT(longtxt,'|','email:',email,', mask:', mask);
                
		SET i = tokenEnd + 1;
        
		ITERATE label1;
      
	END LOOP label1;

	SELECT longtxt;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2019-08-12  6:54:51
