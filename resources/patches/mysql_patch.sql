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
ALTER TABLE entities DROP COLUMN address;
-- #        }
-- #    }
-- #}