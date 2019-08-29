-- #!mysql
-- #{bcp
-- #    {table
-- #        {entities
CREATE TABLE IF NOT EXISTS entities
(
    uuid             VARCHAR(36) UNIQUE PRIMARY KEY  NOT NULL,
    entity_name      VARCHAR(16)                     NOT NULL,
    entity_classpath TEXT                            NOT NULL,
    address          VARCHAR(15) DEFAULT '127.0.0.1' NOT NULL
);
-- #        }
-- #        {log_history
CREATE TABLE IF NOT EXISTS log_history
(
    log_id     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY NOT NULL,
    who        VARCHAR(36)                                NOT NULL,
    x          BIGINT                                     NOT NULL,
    y          TINYINT UNSIGNED                           NOT NULL,
    z          BIGINT                                     NOT NULL,
    world_name VARCHAR(255)                               NOT NULL,
    action     TINYINT UNSIGNED                           NOT NULL,
    time       TIMESTAMP(6) DEFAULT CURRENT_TIMESTAMP     NOT NULL,
    rollback   BOOLEAN      DEFAULT FALSE                 NOT NULL,
    FOREIGN KEY (who) REFERENCES entities (uuid)
);
-- #        }
-- #        {blocks_log
CREATE TABLE IF NOT EXISTS blocks_log
(
    history_id BIGINT UNSIGNED     NOT NULL,
    old_id     INTEGER UNSIGNED    NOT NULL,
    old_meta   TINYINT(2) UNSIGNED NOT NULL,
    old_nbt    LONGBLOB DEFAULT NULL,
    new_id     INTEGER UNSIGNED    NOT NULL,
    new_meta   TINYINT(2) UNSIGNED NOT NULL,
    new_nbt    LONGBLOB DEFAULT NULL,
    FOREIGN KEY (history_id) REFERENCES log_history (log_id) ON DELETE CASCADE
);
-- #        }
-- #        {entities_log
CREATE TABLE IF NOT EXISTS entities_log
(
    history_id      BIGINT UNSIGNED  NOT NULL,
    entityfrom_uuid VARCHAR(36)      NOT NULL,
    entityfrom_id   INTEGER UNSIGNED NOT NULL,
    entityfrom_nbt  LONGBLOB DEFAULT NULL,
    FOREIGN KEY (history_id) REFERENCES log_history (log_id) ON DELETE CASCADE,
    FOREIGN KEY (entityfrom_uuid) REFERENCES entities (uuid)
);
-- #        }
-- #        {inventories_log
CREATE TABLE IF NOT EXISTS inventories_log
(
    history_id BIGINT UNSIGNED               NOT NULL,
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
-- #        {db_status
CREATE TABLE IF NOT EXISTS status
(
    only_one_row BOOLEAN PRIMARY KEY DEFAULT TRUE              NOT NULL,
    version      VARCHAR(20)                                   NOT NULL,
    upgraded_on  TIMESTAMP(6)        DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CHECK (only_one_row)
);
-- #        }
-- #    }
-- #    {add
-- #        {entity
-- #            :uuid string
-- #            :name string
-- #            :path string
-- #            :address string 127.0.0.1
INSERT INTO entities (uuid, entity_name, entity_classpath, address)
VALUES (:uuid, :name, :path, :address)
ON DUPLICATE KEY UPDATE address=:address;
-- #        }
-- #        {db_version
-- #            :version string
INSERT INTO status (version)
VALUES (:version)
ON DUPLICATE KEY UPDATE version=version;
-- #        }
-- #        {log
-- #            {main
-- #                :uuid string
-- #                :x int
-- #                :y int
-- #                :z int
-- #                :world_name string
-- #                :action int
INSERT INTO log_history(who, x, y, z, world_name,
                        action)
VALUES ((SELECT uuid FROM entities WHERE uuid = :uuid), :x, :y, :z, :world_name, :action);
-- #            }
-- #            {block
-- #                :old_id int
-- #                :old_meta int
-- #                :old_nbt ?string
-- #                :new_id int
-- #                :new_meta int
-- #                :new_nbt ?string
INSERT INTO blocks_log(history_id, old_id, old_meta, old_nbt, new_id, new_meta,
                       new_nbt)
VALUES (LAST_INSERT_ID(), :old_id, :old_meta, :old_nbt, :new_id, :new_meta,
        :new_nbt);
-- #            }
-- #            {entity
-- #                :uuid string
-- #                :id int
-- #                :nbt ?string
INSERT INTO entities_log(history_id, entityfrom_uuid, entityfrom_id, entityfrom_nbt)
VALUES (LAST_INSERT_ID(), (SELECT uuid FROM entities WHERE uuid = :uuid), :id, :nbt);
-- #            }
-- #            {inventory
-- #                :slot int
-- #                :old_id int 0
-- #                :old_meta int 0
-- #                :old_nbt ?string
-- #                :old_amount int 0
-- #                :new_id int 0
-- #                :new_meta int 0
-- #                :new_nbt ?string
-- #                :new_amount int 0
INSERT INTO inventories_log(history_id, slot, old_id, old_meta, old_nbt, old_amount, new_id,
                            new_meta, new_nbt, new_amount)
VALUES (LAST_INSERT_ID(), :slot, :old_id, :old_meta, :old_nbt, :old_amount, :new_id,
        :new_meta, :new_nbt, :new_amount);
-- #            }
-- #        }
-- #    }
-- #    {update
-- #        {entity_id
-- #            :log_id int
-- #            :entity_id int
UPDATE entities_log
SET entityfrom_id = :entity_id
WHERE history_id = :log_id;
-- #        }
-- #        {db_version
-- #             :version string
UPDATE status
SET version     = :version,
    upgraded_on = DEFAULT
LIMIT 1;
-- #        }
-- #        {rollback_status
-- #             :rollback bool
-- #             :log_ids list:int
UPDATE log_history
SET rollback = :rollback
WHERE log_id IN :log_ids;
-- #        }
-- #    }
-- #    {get
-- #        {db_status
SELECT *
FROM status
LIMIT 1;
-- #        }
-- #        {log
-- #            {last_id
SELECT MAX(log_id) AS lastId
FROM log_history;
-- #            }
-- #            {old_blocks
-- #                :log_ids list:int
SELECT history_id,
       bl.old_id,
       bl.old_meta,
       bl.old_nbt,
       x,
       y,
       z,
       world_name
FROM log_history
         INNER JOIN blocks_log bl ON log_history.log_id = bl.history_id
WHERE log_id IN :log_ids;
-- #            }
-- #            {new_blocks
-- #                :log_ids list:int
SELECT history_id,
       bl.new_id,
       bl.new_meta,
       bl.new_nbt,
       x,
       y,
       z,
       world_name
FROM log_history
         INNER JOIN blocks_log bl ON log_history.log_id = bl.history_id
WHERE log_id IN :log_ids;
-- #            }
-- #            {old_inventories
-- #                :log_ids list:int
SELECT history_id,
       il.slot,
       il.old_id,
       il.old_meta,
       il.old_nbt,
       il.old_amount,
       x,
       y,
       z
FROM log_history
         INNER JOIN inventories_log il ON log_history.log_id = il.history_id
WHERE log_id IN :log_ids;
-- #            }
-- #            {new_inventories
-- #                :log_ids list:int
SELECT history_id,
       il.slot,
       il.new_id,
       il.new_meta,
       il.new_nbt,
       il.new_amount,
       x,
       y,
       z
FROM log_history
         INNER JOIN inventories_log il ON log_history.log_id = il.history_id
WHERE log_id IN :log_ids;
-- #            }
-- #            {entities
-- #                :log_ids list:int
SELECT e.entity_classpath,
       el.entityfrom_id,
       el.entityfrom_nbt,
       x,
       y,
       z,
       action
FROM log_history
         INNER JOIN entities_log el ON log_history.log_id = el.history_id
         INNER JOIN entities e ON e.uuid = el.entityfrom_uuid
WHERE log_id IN :log_ids;
-- #            }
-- #            {block
-- #                :min_x int
-- #                :max_x int
-- #                :min_y int
-- #                :max_y int
-- #                :min_z int
-- #                :max_z int
-- #                :world_name string
SELECT bl.old_id,
       bl.old_meta,
       bl.old_nbt,
       bl.new_id,
       bl.new_meta,
       bl.new_nbt,
       e.entity_name AS entity_from,
       x,
       y,
       z,
       world_name,
       action,
       time,
       rollback
FROM log_history
         INNER JOIN entities e ON log_history.who = e.uuid
         INNER JOIN blocks_log bl ON log_history.log_id = bl.history_id
WHERE (x BETWEEN :min_x AND :max_x)
  AND (y BETWEEN :min_y AND :max_y)
  AND (z BETWEEN :min_z AND :max_z)
  AND world_name = :world_name
  AND action BETWEEN 0 AND 2
ORDER BY time DESC;
-- #            }
-- #            {entity
-- #                :min_x int
-- #                :max_x int
-- #                :min_y int
-- #                :max_y int
-- #                :min_z int
-- #                :max_z int
-- #                :world_name string
SELECT e1.entity_name AS entity_from,
       e2.entity_name AS entity_to,
       x,
       y,
       z,
       world_name,
       action,
       time,
       rollback
FROM log_history
         INNER JOIN entities e1 ON log_history.who = e1.uuid
         INNER JOIN entities_log el ON log_history.log_id = el.history_id
         INNER JOIN entities e2 ON el.entityfrom_uuid = e2.uuid
WHERE (x BETWEEN :min_x AND :max_x)
  AND (y BETWEEN :min_y AND :max_y)
  AND (z BETWEEN :min_z AND :max_z)
  AND world_name = :world_name
  AND action BETWEEN 3 AND 5
ORDER BY time DESC;
-- #            }
-- #            {near
-- #                :min_x int
-- #                :max_x int
-- #                :min_y int
-- #                :max_y int
-- #                :min_z int
-- #                :max_z int
-- #                :world_name string
SELECT bl.old_id,
       bl.old_meta,
       bl.old_nbt,
       bl.new_id,
       bl.new_meta,
       bl.new_nbt,
       e1.entity_name AS entity_from,
       e2.entity_name AS entity_to,
       x,
       y,
       z,
       world_name,
       action,
       time,
       rollback
FROM log_history
         LEFT JOIN entities e1 ON log_history.who = e1.uuid
         LEFT JOIN entities_log el ON log_history.log_id = el.history_id
         LEFT JOIN entities e2 ON el.entityfrom_uuid = e2.uuid
         LEFT JOIN blocks_log bl ON log_history.log_id = bl.history_id
WHERE (x BETWEEN :min_x AND :max_x)
  AND (y BETWEEN :min_y AND :max_y)
  AND (z BETWEEN :min_z AND :max_z)
  AND world_name = :world_name
  AND action BETWEEN 0 AND 5
ORDER BY time DESC;
-- #            }
-- #            {transaction
-- #                :min_x int
-- #                :max_x int
-- #                :min_y int
-- #                :max_y int
-- #                :min_z int
-- #                :max_z int
-- #                :world_name string
SELECT il.old_id,
       il.old_meta,
       il.old_nbt,
       il.old_amount,
       il.new_id,
       il.new_meta,
       il.new_nbt,
       il.new_amount,
       e.entity_name AS entity_from,
       x,
       y,
       z,
       world_name,
       action,
       time,
       rollback
FROM log_history
         INNER JOIN entities e ON log_history.who = e.uuid
         INNER JOIN inventories_log il on log_history.log_id = il.history_id
WHERE (x BETWEEN :min_x AND :max_x)
  AND (y BETWEEN :min_y AND :max_y)
  AND (z BETWEEN :min_z AND :max_z)
  AND world_name = :world_name
  AND action BETWEEN 6 AND 7
ORDER BY time DESC;
-- #            }
-- #        }
-- #    }
-- #    {purge
-- #        :time int
DELETE
FROM log_history
WHERE time < FROM_UNIXTIME(UNIX_TIMESTAMP() - :time);
-- #    }
-- #}