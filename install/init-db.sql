CREATE TABLE `ChandlerACLGroupsPermissions` (
 `group` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
 `model` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
 `context` int(10) unsigned DEFAULT NULL,
 `permission` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
 `status` tinyint(1) NOT NULL DEFAULT 1,
 KEY `group` (`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ChandlerACLPermissionAliases` (
 `alias` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
 `model` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
 `context` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
 `permission` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
 PRIMARY KEY (`alias`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ChandlerACLRelations` (
 `user` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
 `group` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
 `priority` bigint(20) unsigned NOT NULL DEFAULT 0,
 KEY `user` (`user`),
 KEY `group` (`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ChandlerACLUsersPermissions` (
 `user` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
 `model` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
 `context` int(10) unsigned NOT NULL,
 `permission` int(10) unsigned NOT NULL,
 `status` tinyint(1) NOT NULL DEFAULT 1,
 KEY `user` (`user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ChandlerGroups` (
 `id` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
 `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
 `color` mediumint(8) unsigned DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ChandlerTokens` (
 `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
 `user` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
 `ip` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
 `ua` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
 PRIMARY KEY (`token`),
 KEY `user` (`user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ChandlerUsers` (
 `id` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
 `login` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
 `passwordHash` varchar(136) COLLATE utf8mb4_unicode_ci NOT NULL,
 `deleted` tinyint(1) NOT NULL DEFAULT 0,
 PRIMARY KEY (`id`),
 UNIQUE KEY `login` (`login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ChandlerLogs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` int(11) NOT NULL,
  `object_table` tinytext COLLATE utf8mb4_unicode_ci NOT NULL,
  `object_model` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `object_id` bigint(20) UNSIGNED NOT NULL,
  `xdiff_old` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `xdiff_new` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `ts` bigint(20) NOT NULL,
  `ip` tinytext COLLATE utf8mb4_unicode_ci NOT NULL,
  `useragent` longtext COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `ChandlerLogs`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `ChandlerLogs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;


INSERT INTO `ChandlerGroups` VALUES ("c75fe4de-1e62-11ea-904d-42010aac0003", "Users", NULL);
INSERT INTO `ChandlerGroups` VALUES ("594e6cb4-2a3a-11ea-9e1e-42010aac0003", "Administrators", NULL);

INSERT INTO `ChandlerACLGroupsPermissions` VALUES ("594e6cb4-2a3a-11ea-9e1e-42010aac0003", "admin", NULL, "access", 1);

INSERT INTO `ChandlerUsers` (`id`, `login`, `passwordHash`, `deleted`) VALUES ("ffffffff-ffff-ffff-ffff-ffffffffffff", 'admin@localhost.localdomain6', '9ed792f7235638a1686d69f6d9bc038f$7e315bea792f08e63e38a355980c8070', '0');

INSERT INTO `ChandlerACLRelations` VALUES ("ffffffff-ffff-ffff-ffff-ffffffffffff", "594e6cb4-2a3a-11ea-9e1e-42010aac0003", 64);
INSERT INTO `ChandlerACLRelations` VALUES ("ffffffff-ffff-ffff-ffff-ffffffffffff", "c75fe4de-1e62-11ea-904d-42010aac0003", 32);




CREATE TRIGGER `bfiu_groups` BEFORE INSERT ON `ChandlerGroups`
 FOR EACH ROW SET new.id = uuid();

CREATE TRIGGER `bfiu_tokens` BEFORE INSERT ON `ChandlerTokens`
 FOR EACH ROW SET new.token = uuid();

CREATE TRIGGER `bfiu_users` BEFORE INSERT ON `ChandlerUsers`
 FOR EACH ROW SET new.id = uuid();
