CREATE TABLE `ChandlerACLGroupsPermissions`
(
    `group`      VARCHAR(36) COLLATE "utf8mb4_unicode_ci"   NOT NULL,
    `model`      VARCHAR(1000) COLLATE "utf8mb4_unicode_ci" NOT NULL,
    `context`    INT(10) UNSIGNED                                    DEFAULT NULL,
    `permission` VARCHAR(36) COLLATE "utf8mb4_unicode_ci"   NOT NULL,
    `status`     TINYINT(1)                                 NOT NULL DEFAULT 1
) ENGINE = InnoDB
  DEFAULT CHARSET = `utf8mb4`
  COLLATE = `utf8mb4_unicode_ci`;

CREATE TABLE `ChandlerACLPermissionAliases`
(
    `alias`      VARCHAR(190) COLLATE "utf8mb4_unicode_ci" NOT NULL,
    `model`      VARCHAR(255) COLLATE "utf8mb4_unicode_ci" NOT NULL,
    `context`    VARCHAR(255) COLLATE "utf8mb4_unicode_ci" NOT NULL,
    `permission` VARCHAR(255) COLLATE "utf8mb4_unicode_ci" NOT NULL,
    PRIMARY KEY (`alias`)
) ENGINE = InnoDB
  DEFAULT CHARSET = `utf8mb4`
  COLLATE = `utf8mb4_unicode_ci`;

CREATE TABLE `ChandlerACLRelations`
(
    `user`     VARCHAR(36) COLLATE "utf8mb4_unicode_ci" NOT NULL,
    `group`    VARCHAR(36) COLLATE "utf8mb4_unicode_ci" NOT NULL,
    `priority` BIGINT(20) UNSIGNED                      NOT NULL DEFAULT 0
) ENGINE = InnoDB
  DEFAULT CHARSET = `utf8mb4`
  COLLATE = `utf8mb4_unicode_ci`;

CREATE TABLE `ChandlerACLUsersPermissions`
(
    `user`       VARCHAR(36) COLLATE "utf8mb4_unicode_ci"   NOT NULL,
    `model`      VARCHAR(1000) COLLATE "utf8mb4_unicode_ci" NOT NULL,
    `context`    INT(10) UNSIGNED                           NOT NULL,
    `permission` INT(10) UNSIGNED                           NOT NULL,
    `status`     TINYINT(1)                                 NOT NULL DEFAULT 1
) ENGINE = InnoDB
  DEFAULT CHARSET = `utf8mb4`
  COLLATE = `utf8mb4_unicode_ci`;

CREATE TABLE `ChandlerGroups`
(
    `id`    VARCHAR(36) COLLATE "utf8mb4_unicode_ci"  NOT NULL,
    `name`  VARCHAR(100) COLLATE "utf8mb4_unicode_ci" NOT NULL,
    `color` MEDIUMINT(8) UNSIGNED DEFAULT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = `utf8mb4`
  COLLATE = `utf8mb4_unicode_ci`;

CREATE TABLE `ChandlerTokens`
(
    `token` VARCHAR(64) COLLATE "utf8mb4_unicode_ci"   NOT NULL,
    `user`  VARCHAR(36) COLLATE "utf8mb4_unicode_ci"   NOT NULL,
    `ip`    VARCHAR(255) COLLATE "utf8mb4_unicode_ci"  NOT NULL,
    `ua`    VARCHAR(1000) COLLATE "utf8mb4_unicode_ci" NOT NULL,
    PRIMARY KEY (`token`)
) ENGINE = InnoDB
  DEFAULT CHARSET = `utf8mb4`
  COLLATE = `utf8mb4_unicode_ci`;

CREATE TABLE `ChandlerUsers`
(
    `id`           VARCHAR(36) COLLATE "utf8mb4_unicode_ci"  NOT NULL,
    `login`        VARCHAR(64) COLLATE "utf8mb4_unicode_ci"  NOT NULL,
    `passwordHash` VARCHAR(136) COLLATE "utf8mb4_unicode_ci" NOT NULL,
    `deleted`      TINYINT(1)                                NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `login` (`login`)
) ENGINE = InnoDB
  DEFAULT CHARSET = `utf8mb4`
  COLLATE = `utf8mb4_unicode_ci`;

INSERT INTO `ChandlerGroups`
VALUES ('c75fe4de-1e62-11ea-904d-42010aac0003', 'Users', NULL);
INSERT INTO `ChandlerGroups`
VALUES ('594e6cb4-2a3a-11ea-9e1e-42010aac0003', 'Administrators', NULL);

INSERT INTO `ChandlerACLGroupsPermissions`
VALUES ('594e6cb4-2a3a-11ea-9e1e-42010aac0003', 'admin', NULL, 'access', 1);

INSERT INTO `ChandlerUsers` (`id`, `login`, `passwordHash`, `deleted`)
VALUES ('ffffffff-ffff-ffff-ffff-ffffffffffff', 'admin@localhost.localdomain6',
        '9ed792f7235638a1686d69f6d9bc038f$7e315bea792f08e63e38a355980c8070', '0');

INSERT INTO `ChandlerACLRelations`
VALUES ('ffffffff-ffff-ffff-ffff-ffffffffffff', '594e6cb4-2a3a-11ea-9e1e-42010aac0003', 64);
INSERT INTO `ChandlerACLRelations`
VALUES ('ffffffff-ffff-ffff-ffff-ffffffffffff', 'c75fe4de-1e62-11ea-904d-42010aac0003', 32);

CREATE TRIGGER `bfiu_groups`
    BEFORE INSERT
    ON `ChandlerGroups`
    FOR EACH ROW SET `NEW`.`id` = UUID();

CREATE TRIGGER `bfiu_tokens`
    BEFORE INSERT
    ON `ChandlerTokens`
    FOR EACH ROW SET `NEW`.`token` = UUID();

CREATE TRIGGER `bfiu_users`
    BEFORE INSERT
    ON `ChandlerUsers`
    FOR EACH ROW SET `NEW`.`id` = UUID();
