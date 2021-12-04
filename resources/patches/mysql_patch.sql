-- #!mysql
-- #{patch
-- #    {0.6.0
-- #        {1
ALTER TABLE log_history
    ADD UNIQUE (log_id);
-- #        }
-- #        {2
ALTER TABLE blocks_log
    ADD UNIQUE (history_id);
-- #        }
-- #        {3
ALTER TABLE entities_log
    ADD UNIQUE (history_id);
-- #        }
-- #        {4
ALTER TABLE inventories_log
    ADD UNIQUE (history_id);
-- #        }
-- #    }
-- #    {0.6.2
-- #        {1
CREATE TABLE IF NOT EXISTS temp
(
    history_id BIGINT UNSIGNED PRIMARY KEY,
    old_id     INTEGER UNSIGNED    NOT NULL,
    old_meta   TINYINT(2) UNSIGNED NOT NULL,
    old_nbt    LONGBLOB DEFAULT NULL,
    new_id     INTEGER UNSIGNED    NOT NULL,
    new_meta   TINYINT(2) UNSIGNED NOT NULL,
    new_nbt    LONGBLOB DEFAULT NULL,
    FOREIGN KEY (history_id) REFERENCES log_history (log_id) ON DELETE CASCADE
);
-- #        }
-- #        {2
INSERT INTO temp
SELECT *
FROM blocks_log;
-- #        }
-- #        {3
DROP TABLE blocks_log;
-- #        }
-- #        {4
ALTER TABLE temp
    RENAME TO blocks_log;
-- #        }
-- #        {5
CREATE TABLE IF NOT EXISTS temp
(
    history_id      BIGINT UNSIGNED PRIMARY KEY,
    entityfrom_uuid VARCHAR(36)      NOT NULL,
    entityfrom_id   INTEGER UNSIGNED NOT NULL,
    entityfrom_nbt  LONGBLOB DEFAULT NULL,
    FOREIGN KEY (history_id) REFERENCES log_history (log_id) ON DELETE CASCADE,
    FOREIGN KEY (entityfrom_uuid) REFERENCES entities (uuid)
);
-- #        }
-- #        {6
INSERT INTO temp
SELECT *
FROM entities_log;
-- #        }
-- #        {7
DROP TABLE entities_log;
-- #        }
-- #        {8
ALTER TABLE temp
    RENAME TO entities_log;
-- #        }
-- #        {9
CREATE TABLE IF NOT EXISTS temp
(
    history_id BIGINT UNSIGNED PRIMARY KEY,
    slot       TINYINT UNSIGNED              NOT NULL,
    old_id     INTEGER UNSIGNED    DEFAULT 0 NOT NULL,
    old_meta   TINYINT(2) UNSIGNED DEFAULT 0 NOT NULL,
    old_nbt    LONGBLOB            DEFAULT NULL,
    old_amount TINYINT UNSIGNED    DEFAULT 0 NOT NULL,
    new_id     INTEGER UNSIGNED    DEFAULT 0 NOT NULL,
    new_meta   TINYINT(2) UNSIGNED DEFAULT 0 NOT NULL,
    new_nbt    LONGBLOB            DEFAULT NULL,
    new_amount TINYINT UNSIGNED    DEFAULT 0 NOT NULL,
    FOREIGN KEY (history_id) REFERENCES log_history (log_id) ON DELETE CASCADE
);
-- #        }
-- #        {10
INSERT INTO temp
SELECT *
FROM inventories_log;
-- #        }
-- #        {11
DROP TABLE inventories_log;
-- #        }
-- #        {12
ALTER TABLE temp
    RENAME TO inventories_log;
-- #        }
-- #        {13
CREATE TABLE IF NOT EXISTS temp
(
    log_id     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    who        VARCHAR(36)                            NOT NULL,
    x          BIGINT                                 NOT NULL,
    y          TINYINT UNSIGNED                       NOT NULL,
    z          BIGINT                                 NOT NULL,
    world_name VARCHAR(255)                           NOT NULL,
    action     TINYINT UNSIGNED                       NOT NULL,
    time       TIMESTAMP(6) DEFAULT CURRENT_TIMESTAMP NOT NULL,
    rollback   BOOLEAN      DEFAULT FALSE             NOT NULL,
    FOREIGN KEY (who) REFERENCES entities (uuid)
);
-- #        }
-- #        {14
INSERT INTO temp
SELECT *
FROM log_history;
-- #        }
-- #        {15
DROP TABLE log_history;
-- #        }
-- #        {16
ALTER TABLE temp
    RENAME TO log_history;
-- #        }
-- #    }
-- #    {0.7.1
-- #        {1
ALTER TABLE entities
    DROP COLUMN address;
-- #        }
-- #    }
-- #    {0.8.0
-- #        {1
CREATE TABLE IF NOT EXISTS log_history_temp
(
    log_id     BIGINT AUTO_INCREMENT PRIMARY KEY,
    who        VARCHAR(36)           NOT NULL,
    x          INTEGER               NOT NULL,
    y          SMALLINT              NOT NULL,
    z          INTEGER               NOT NULL,
    world_name VARCHAR(255)          NOT NULL,
    action     TINYINT UNSIGNED      NOT NULL,
    time       BIGINT                NOT NULL,
    rollback   BOOLEAN DEFAULT FALSE NOT NULL
);
-- #        }
-- #        {2
CREATE TABLE IF NOT EXISTS blocks_log_temp
(
    history_id BIGINT PRIMARY KEY,
    old_id     INTEGER NOT NULL,
    old_meta   INTEGER NOT NULL,
    old_nbt    LONGBLOB DEFAULT NULL,
    new_id     INTEGER NOT NULL,
    new_meta   INTEGER NOT NULL,
    new_nbt    LONGBLOB DEFAULT NULL
);
-- #        }
-- #        {3
CREATE TABLE IF NOT EXISTS entities_log_temp
(
    history_id      BIGINT PRIMARY KEY,
    entityfrom_uuid VARCHAR(36)      NOT NULL,
    entityfrom_id   INTEGER UNSIGNED NOT NULL,
    entityfrom_nbt  LONGBLOB DEFAULT NULL
);
-- #        }
-- #        {4
CREATE TABLE IF NOT EXISTS inventories_log_temp
(
    history_id BIGINT PRIMARY KEY,
    slot       TINYINT UNSIGNED           NOT NULL,
    old_id     INTEGER          DEFAULT 0 NOT NULL,
    old_meta   INTEGER          DEFAULT 0 NOT NULL,
    old_nbt    LONGBLOB         DEFAULT NULL,
    old_amount TINYINT UNSIGNED DEFAULT 0 NOT NULL,
    new_id     INTEGER          DEFAULT 0 NOT NULL,
    new_meta   INTEGER          DEFAULT 0 NOT NULL,
    new_nbt    LONGBLOB         DEFAULT NULL,
    new_amount TINYINT UNSIGNED DEFAULT 0 NOT NULL
);
-- #        }
-- #        {5
INSERT INTO log_history_temp
SELECT *
FROM log_history;
-- #        }
-- #        {6
UPDATE log_history_temp
SET time = (UNIX_TIMESTAMP(time));
-- #        }
-- #        {7
INSERT INTO blocks_log_temp
SELECT *
FROM blocks_log;
-- #        }
-- #        {8
INSERT INTO entities_log_temp
SELECT *
FROM entities_log;
-- #        }
-- #        {9
INSERT INTO inventories_log_temp
SELECT *
FROM inventories_log;
-- #        }
-- #        {10
DROP TABLE blocks_log;
-- #        }
-- #        {11
DROP TABLE entities_log;
-- #        }
-- #        {12
DROP TABLE inventories_log;
-- #        }
-- #        {13
DROP TABLE log_history;
-- #        }
-- #        {14
ALTER TABLE log_history_temp
    RENAME TO log_history;
-- #        }
-- #        {15
ALTER TABLE blocks_log_temp
    RENAME TO blocks_log;
-- #        }
-- #        {16
ALTER TABLE entities_log_temp
    RENAME TO entities_log;
-- #        }
-- #        {17
ALTER TABLE inventories_log_temp
    RENAME TO inventories_log;
-- #        }
-- #        {18
ALTER TABLE log_history
    ADD CONSTRAINT fk_log_who FOREIGN KEY (who) REFERENCES entities (uuid);
-- #        }
-- #        {19
ALTER TABLE blocks_log
    ADD CONSTRAINT fk_blocks_log_id FOREIGN KEY (history_id) REFERENCES log_history (log_id) ON DELETE CASCADE;
-- #        }
-- #        {20
ALTER TABLE entities_log
    ADD CONSTRAINT fk_entities_log_id FOREIGN KEY (history_id) REFERENCES log_history (log_id) ON DELETE CASCADE;
-- #        }
-- #        {21
ALTER TABLE entities_log
    ADD CONSTRAINT fk_entities_entityfrom FOREIGN KEY (entityfrom_uuid) REFERENCES entities (uuid);
-- #        }
-- #        {22
ALTER TABLE inventories_log
    ADD CONSTRAINT fk_inventories_log_id FOREIGN KEY (history_id) REFERENCES log_history (log_id) ON DELETE CASCADE;
-- #        }
-- #    }
-- #    {0.8.1
-- #        {1
ALTER TABLE log_history
    MODIFY time DOUBLE PRECISION NOT NULL;
-- #        }
-- #        {2
CREATE TABLE IF NOT EXISTS temp
(
    version     VARCHAR(20) PRIMARY KEY             NOT NULL,
    upgraded_on TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
);
-- #        }
-- #        {3
INSERT INTO temp(version, upgraded_on)
SELECT version, upgraded_on
FROM status;
-- #        }
-- #        {4
DROP TABLE status;
-- #        }
-- #        {5
ALTER TABLE temp
    RENAME TO status;
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
ALTER TABLE entities
    DROP COLUMN entity_classpath;
-- #        }
-- #    }
-- #}