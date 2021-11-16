-- #!sqlite
-- #{patch
-- #    {0.6.0
-- #        {1
CREATE TABLE IF NOT EXISTS "temp"
(
    log_id     INTEGER UNIQUE PRIMARY KEY AUTOINCREMENT NOT NULL,
    who        VARCHAR(36)                              NOT NULL,
    x          BIGINT                                   NOT NULL,
    y          TINYINT UNSIGNED                         NOT NULL,
    z          BIGINT                                   NOT NULL,
    world_name VARCHAR(255)                             NOT NULL,
    action     TINYINT UNSIGNED                         NOT NULL,
    time       TIMESTAMP  DEFAULT (STRFTIME('%Y-%m-%d %H:%M:%f', 'now', 'localtime')) NOT NULL,
    "rollback" TINYINT(1) DEFAULT 0 NOT NULL,
    FOREIGN KEY (who) REFERENCES "entities" (uuid)
);
-- #        }
-- #        {2
INSERT INTO "temp"
SELECT *
FROM "log_history";
-- #        }
-- #        {3
DROP TABLE "log_history";
-- #        }
-- #        {4
ALTER TABLE "temp"
    RENAME TO "log_history";
-- #        }
-- #        {5
CREATE TABLE IF NOT EXISTS "temp"
(
    history_id UNSIGNED BIG INT UNIQUE NOT NULL,
    old_id     UNSIGNED INTEGER        NOT NULL,
    old_meta   UNSIGNED TINYINT(2)     NOT NULL,
    old_nbt    BLOB DEFAULT NULL,
    new_id     UNSIGNED INTEGER        NOT NULL,
    new_meta   UNSIGNED TINYINT(2)     NOT NULL,
    new_nbt    BLOB DEFAULT NULL,
    FOREIGN KEY (history_id) REFERENCES "log_history" (log_id) ON DELETE CASCADE
);
-- #        }
-- #        {6
INSERT INTO "temp"
SELECT *
FROM "blocks_log";
-- #        }
-- #        {7
DROP TABLE "blocks_log";
-- #        }
-- #        {8
ALTER TABLE "temp"
    RENAME TO "blocks_log";
-- #        }
-- #        {9
CREATE TABLE IF NOT EXISTS "temp"
(
    history_id      UNSIGNED BIG INT UNIQUE NOT NULL,
    entityfrom_uuid VARCHAR(36)             NOT NULL,
    entityfrom_id   UNSIGNED INTEGER        NOT NULL,
    entityfrom_nbt  BLOB DEFAULT NULL,
    FOREIGN KEY (history_id) REFERENCES "log_history" (log_id) ON DELETE CASCADE,
    FOREIGN KEY (entityfrom_uuid) REFERENCES "entities" (uuid)
);
-- #        }
-- #        {10
INSERT INTO "temp"
SELECT *
FROM "entities_log";
-- #        }
-- #        {11
DROP TABLE "entities_log";
-- #        }
-- #        {12
ALTER TABLE "temp"
    RENAME TO "entities_log";
-- #        }
-- #        {13
CREATE TABLE IF NOT EXISTS "temp"
(
    history_id UNSIGNED BIG INT UNIQUE NOT NULL,
    slot       UNSIGNED TINYINT        NOT NULL,
    old_id     UNSIGNED INTEGER    DEFAULT 0 NOT NULL,
    old_meta   UNSIGNED TINYINT(2) DEFAULT 0 NOT NULL,
    old_nbt    BLOB                DEFAULT NULL,
    old_amount UNSIGNED TINYINT    DEFAULT 0 NOT NULL,
    new_id     UNSIGNED INTEGER    DEFAULT 0 NOT NULL,
    new_meta   UNSIGNED TINYINT(2) DEFAULT 0 NOT NULL,
    new_nbt    BLOB                DEFAULT NULL,
    new_amount UNSIGNED TINYINT    DEFAULT 0 NOT NULL,
    FOREIGN KEY (history_id) REFERENCES "log_history" (log_id) ON DELETE CASCADE
);
-- #        }
-- #        {14
INSERT INTO "temp"
SELECT *
FROM "inventories_log";
-- #        }
-- #        {15
DROP TABLE "inventories_log";
-- #        }
-- #        {16
ALTER TABLE "temp"
    RENAME TO "inventories_log";
-- #        }
-- #    }
-- #    {0.6.2
-- #        {1
CREATE TABLE IF NOT EXISTS "temp"
(
    history_id UNSIGNED BIG INT PRIMARY KEY,
    old_id     UNSIGNED INTEGER    NOT NULL,
    old_meta   UNSIGNED TINYINT(2) NOT NULL,
    old_nbt    BLOB DEFAULT NULL,
    new_id     UNSIGNED INTEGER    NOT NULL,
    new_meta   UNSIGNED TINYINT(2) NOT NULL,
    new_nbt    BLOB DEFAULT NULL,
    FOREIGN KEY (history_id) REFERENCES "log_history" (log_id) ON DELETE CASCADE
);
-- #        }
-- #        {2
INSERT INTO "temp"
SELECT *
FROM "blocks_log";
-- #        }
-- #        {3
DROP TABLE "blocks_log";
-- #        }
-- #        {4
ALTER TABLE "temp"
    RENAME TO "blocks_log";
-- #        }
-- #        {5
CREATE TABLE IF NOT EXISTS "temp"
(
    history_id      UNSIGNED BIG INT PRIMARY KEY,
    entityfrom_uuid VARCHAR(36)      NOT NULL,
    entityfrom_id   UNSIGNED INTEGER NOT NULL,
    entityfrom_nbt  BLOB DEFAULT NULL,
    FOREIGN KEY (history_id) REFERENCES "log_history" (log_id) ON DELETE CASCADE,
    FOREIGN KEY (entityfrom_uuid) REFERENCES "entities" (uuid)
);
-- #        }
-- #        {6
INSERT INTO "temp"
SELECT *
FROM "entities_log";
-- #        }
-- #        {7
DROP TABLE "entities_log";
-- #        }
-- #        {8
ALTER TABLE "temp"
    RENAME TO "entities_log";
-- #        }
-- #        {9
CREATE TABLE IF NOT EXISTS "temp"
(
    history_id UNSIGNED BIG INT PRIMARY KEY,
    slot       UNSIGNED TINYINT NOT NULL,
    old_id     UNSIGNED INTEGER    DEFAULT 0 NOT NULL,
    old_meta   UNSIGNED TINYINT(2) DEFAULT 0 NOT NULL,
    old_nbt    BLOB                DEFAULT NULL,
    old_amount UNSIGNED TINYINT    DEFAULT 0 NOT NULL,
    new_id     UNSIGNED INTEGER    DEFAULT 0 NOT NULL,
    new_meta   UNSIGNED TINYINT(2) DEFAULT 0 NOT NULL,
    new_nbt    BLOB                DEFAULT NULL,
    new_amount UNSIGNED TINYINT    DEFAULT 0 NOT NULL,
    FOREIGN KEY (history_id) REFERENCES "log_history" (log_id) ON DELETE CASCADE
);
-- #        }
-- #        {10
INSERT INTO "temp"
SELECT *
FROM "inventories_log";
-- #        }
-- #        {11
DROP TABLE "inventories_log";
-- #        }
-- #        {12
ALTER TABLE "temp"
    RENAME TO "inventories_log";
-- #        }
-- #        {13
CREATE TABLE IF NOT EXISTS "temp"
(
    log_id     INTEGER PRIMARY KEY AUTOINCREMENT,
    who        VARCHAR(36)      NOT NULL,
    x          BIGINT           NOT NULL,
    y          TINYINT UNSIGNED NOT NULL,
    z          BIGINT           NOT NULL,
    world_name VARCHAR(255)     NOT NULL,
    action     TINYINT UNSIGNED NOT NULL,
    time       TIMESTAMP  DEFAULT (STRFTIME('%Y-%m-%d %H:%M:%f', 'now', 'localtime')) NOT NULL,
    "rollback" TINYINT(1) DEFAULT 0 NOT NULL,
    FOREIGN KEY (who) REFERENCES "entities" (uuid)
);
-- #        }
-- #        {14
INSERT INTO "temp"
SELECT *
FROM "log_history";
-- #        }
-- #        {15
DROP TABLE "log_history";
-- #        }
-- #        {16
ALTER TABLE "temp"
    RENAME TO "log_history";
-- #        }
-- #    }
-- #    {0.7.1
-- #        {1
CREATE TABLE IF NOT EXISTS "entities_new"
(
    uuid             VARCHAR(36) PRIMARY KEY,
    entity_name      VARCHAR(16) NOT NULL,
    entity_classpath TEXT        NOT NULL
);
-- #        }
-- #        {2
INSERT INTO "entities_new" (uuid, entity_name, entity_classpath)
SELECT uuid, entity_name, entity_classpath
FROM "entities";
-- #        }
-- #        {3
DROP TABLE "entities";
-- #        }
-- #        {4
ALTER TABLE "entities_new"
    RENAME TO "entities";
-- #        }
-- #    }
-- #    {0.8.0
-- #        {1
CREATE TABLE IF NOT EXISTS "temp"
(
    log_id     INTEGER PRIMARY KEY AUTOINCREMENT,
    who        VARCHAR(36)      NOT NULL,
    x          INTEGER          NOT NULL,
    y          SMALLINT         NOT NULL,
    z          INTEGER          NOT NULL,
    world_name VARCHAR(255)     NOT NULL,
    action     TINYINT UNSIGNED NOT NULL,
    time       BIGINT           NOT NULL,
    "rollback" TINYINT(1) DEFAULT 0 NOT NULL,
    CONSTRAINT fk_log_who FOREIGN KEY (who) REFERENCES "entities" (uuid)
);
-- #        }
-- #        {2
INSERT INTO "temp"
SELECT *
FROM "log_history";
-- #        }
-- #        {3
UPDATE "temp"
SET time = STRFTIME('%s', time);
-- #        }
-- #        {4
DROP TABLE "log_history";
-- #        }
-- #        {5
ALTER TABLE "temp"
    RENAME TO "log_history";
-- #        }
-- #        {6
CREATE TABLE IF NOT EXISTS "temp"
(
    history_id INTEGER PRIMARY KEY,
    old_id     INTEGER NOT NULL,
    old_meta   INTEGER NOT NULL,
    old_nbt    BLOB DEFAULT NULL,
    new_id     INTEGER NOT NULL,
    new_meta   INTEGER NOT NULL,
    new_nbt    BLOB DEFAULT NULL,
    CONSTRAINT fk_blocks_log_id FOREIGN KEY (history_id) REFERENCES "log_history" (log_id) ON DELETE CASCADE
);
-- #        }
-- #        {7
INSERT INTO "temp"
SELECT *
FROM "blocks_log";
-- #        }
-- #        {8
DROP TABLE "blocks_log";
-- #        }
-- #        {9
ALTER TABLE "temp"
    RENAME TO "blocks_log";
-- #        }
-- #        {10
CREATE TABLE IF NOT EXISTS "temp"
(
    history_id      INTEGER PRIMARY KEY,
    entityfrom_uuid VARCHAR(36)      NOT NULL,
    entityfrom_id   UNSIGNED INTEGER NOT NULL,
    entityfrom_nbt  BLOB DEFAULT NULL,
    CONSTRAINT fk_entities_log_id FOREIGN KEY (history_id) REFERENCES "log_history" (log_id) ON DELETE CASCADE,
    CONSTRAINT fk_entities_entityfrom FOREIGN KEY (entityfrom_uuid) REFERENCES "entities" (uuid)
);
-- #        }
-- #        {11
INSERT INTO "temp"
SELECT *
FROM "entities_log";
-- #        }
-- #        {12
DROP TABLE "entities_log";
-- #        }
-- #        {13
ALTER TABLE "temp"
    RENAME TO "entities_log";
-- #        }
-- #        {14
CREATE TABLE IF NOT EXISTS "temp"
(
    history_id INTEGER PRIMARY KEY,
    slot       UNSIGNED TINYINT NOT NULL,
    old_id     INTEGER          DEFAULT 0 NOT NULL,
    old_meta   INTEGER          DEFAULT 0 NOT NULL,
    old_nbt    BLOB             DEFAULT NULL,
    old_amount UNSIGNED TINYINT DEFAULT 0 NOT NULL,
    new_id     INTEGER          DEFAULT 0 NOT NULL,
    new_meta   INTEGER          DEFAULT 0 NOT NULL,
    new_nbt    BLOB             DEFAULT NULL,
    new_amount UNSIGNED TINYINT DEFAULT 0 NOT NULL,
    CONSTRAINT fk_inventories_log_id FOREIGN KEY (history_id) REFERENCES "log_history" (log_id) ON DELETE CASCADE
);
-- #        }
-- #        {15
INSERT INTO "temp"
SELECT *
FROM "inventories_log";
-- #        }
-- #        {16
DROP TABLE "inventories_log";
-- #        }
-- #        {17
ALTER TABLE "temp"
    RENAME TO "inventories_log";
-- #        }
-- #    }
-- #    {0.8.1
-- #        {1
CREATE TABLE IF NOT EXISTS "temp"
(
    log_id     INTEGER PRIMARY KEY AUTOINCREMENT,
    who        VARCHAR(36)      NOT NULL,
    x          INTEGER          NOT NULL,
    y          SMALLINT         NOT NULL,
    z          INTEGER          NOT NULL,
    world_name VARCHAR(255)     NOT NULL,
    action     TINYINT UNSIGNED NOT NULL,
    time       DOUBLE PRECISION NOT NULL,
    "rollback" TINYINT(1) DEFAULT 0 NOT NULL,
    CONSTRAINT fk_log_who FOREIGN KEY (who) REFERENCES "entities" (uuid)
);
-- #        }
-- #        {2
INSERT INTO "temp"
SELECT *
FROM "log_history";
-- #        }
-- #        {3
DROP TABLE "log_history";
-- #        }
-- #        {4
ALTER TABLE "temp"
    RENAME TO "log_history";
-- #        }
-- #        {5
CREATE TABLE IF NOT EXISTS "temp"
(
    version     VARCHAR(20) PRIMARY KEY NOT NULL,
    upgraded_on TIMESTAMP DEFAULT (STRFTIME('%Y-%m-%d %H:%M:%S', 'now', 'localtime')) NOT NULL
);
-- #        }
-- #        {6
INSERT INTO "temp" (version, upgraded_on)
SELECT version, upgraded_on
FROM "status";
-- #        }
-- #        {7
DROP TABLE "status";
-- #        }
-- #        {8
ALTER TABLE "temp"
    RENAME TO "status";
-- #        }
-- #    }
-- #    {0.8.3
-- #        {1
DELETE
FROM status
WHERE version = '0.8.2';
-- #        }
-- #    }
-- #    {1.0.0
-- #        {1
CREATE TABLE IF NOT EXISTS "temp"
(
    uuid        VARCHAR(36) PRIMARY KEY,
    entity_name VARCHAR(16) NOT NULL
);
-- #        }
-- #        {2
INSERT INTO "temp"
SELECT uuid, entity_name
FROM "entities";
-- #        }
-- #        {3
DROP TABLE "entities";
-- #        }
-- #        {4
ALTER TABLE "temp"
    RENAME TO "entities";
-- #        }
-- #    }
-- #}