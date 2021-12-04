-- #!sqlite
-- #{bcp
-- #    {table
-- #        {entities
CREATE TABLE IF NOT EXISTS "entities"
(
    uuid        VARCHAR(36) PRIMARY KEY,
    entity_name VARCHAR(16) NOT NULL
);
-- #        }
-- #        {log_history
CREATE TABLE IF NOT EXISTS "log_history"
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
-- #        {blocks_log
CREATE TABLE IF NOT EXISTS "blocks_log"
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
-- #        {entities_log
CREATE TABLE IF NOT EXISTS "entities_log"
(
    history_id      INTEGER PRIMARY KEY,
    entityfrom_uuid VARCHAR(36)      NOT NULL,
    entityfrom_id   UNSIGNED INTEGER NOT NULL,
    entityfrom_nbt  BLOB DEFAULT NULL,
    CONSTRAINT fk_entities_log_id FOREIGN KEY (history_id) REFERENCES "log_history" (log_id) ON DELETE CASCADE,
    CONSTRAINT fk_entities_entityfrom FOREIGN KEY (entityfrom_uuid) REFERENCES "entities" (uuid)
);
-- #        }
-- #        {inventories_log
CREATE TABLE IF NOT EXISTS "inventories_log"
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
-- #        {db_status
CREATE TABLE IF NOT EXISTS status
(
    version     VARCHAR(20) PRIMARY KEY NOT NULL,
    upgraded_on TIMESTAMP DEFAULT (STRFTIME('%Y-%m-%d %H:%M:%S', 'now', 'localtime')) NOT NULL
);
-- #        }
-- #    }
-- #    {generic
-- #        {enable_wal_mode
PRAGMA journal_mode = WAL;
-- #        }
-- #        {set_sync_normal
PRAGMA synchronous = NORMAL;
-- #        }
-- #        {set_foreign_keys
-- #            :flag bool
PRAGMA foreign_keys = :flag;
-- #        }
-- #        {begin_transaction
BEGIN TRANSACTION;
-- #        }
-- #        {end_transaction
END TRANSACTION;
-- #        }
-- #        {optimize
PRAGMA optimize;
-- #        }
-- #        {vacuum
VACUUM;
-- #        }
-- #    }
-- #    {add
-- #        {entity
-- #            :uuid string
-- #            :name string
INSERT OR
REPLACE
INTO "entities" (uuid, entity_name)
VALUES (:uuid, :name);
-- #        }
-- #        {db_version
-- #            :version string
INSERT OR IGNORE
INTO status (version)
VALUES (:version);
-- #        }
-- #        {log
-- #            {main
-- #                :uuid string
-- #                :x int
-- #                :y int
-- #                :z int
-- #                :world_name string
-- #                :action int
-- #                :time float
INSERT INTO "log_history"(who, x, y, z, world_name, action, time)
VALUES ((SELECT uuid FROM entities WHERE uuid = :uuid), :x, :y, :z, :world_name, :action, :time);
-- #            }
-- #            {block
-- #                :log_id int
-- #                :old_id int
-- #                :old_meta int
-- #                :old_nbt ?string
-- #                :new_id int
-- #                :new_meta int
-- #                :new_nbt ?string
INSERT INTO "blocks_log"(history_id, old_id, old_meta, old_nbt, new_id, new_meta, new_nbt)
VALUES (:log_id, :old_id, :old_meta, :old_nbt, :new_id, :new_meta, :new_nbt);
-- #            }
-- #            {entity
-- #                :log_id int
-- #                :uuid string
-- #                :id int
-- #                :nbt ?string
INSERT INTO "entities_log"(history_id, entityfrom_uuid, entityfrom_id, entityfrom_nbt)
VALUES (:log_id, (SELECT uuid FROM entities WHERE uuid = :uuid), :id, :nbt);
-- #            }
-- #            {inventory
-- #                :log_id int
-- #                :slot int
-- #                :old_id int 0
-- #                :old_meta int 0
-- #                :old_nbt ?string
-- #                :old_amount int 0
-- #                :new_id int 0
-- #                :new_meta int 0
-- #                :new_nbt ?string
-- #                :new_amount int 0
INSERT INTO "inventories_log"(history_id, slot, old_id, old_meta, old_nbt, old_amount, new_id, new_meta, new_nbt,
                              new_amount)
VALUES (:log_id, :slot, :old_id, :old_meta, :old_nbt, :old_amount, :new_id,
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
-- #        {rollback_status
-- #             :rollback bool
-- #             :log_ids list:int
UPDATE log_history
SET "rollback" = :rollback
WHERE log_id IN :log_ids;
-- #        }
-- #    }
-- #    {get
-- #        {db_status
SELECT version, (SELECT version FROM "status" ORDER BY upgraded_on LIMIT 1) AS init_version
FROM "status"
ORDER BY upgraded_on DESC
LIMIT 1;
-- #        }
-- #        {log
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
FROM "log_history"
         INNER JOIN blocks_log bl ON log_history.log_id = bl.history_id
WHERE log_id IN :log_ids
ORDER BY time DESC;
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
FROM "log_history"
         INNER JOIN blocks_log bl ON log_history.log_id = bl.history_id
WHERE log_id IN :log_ids
ORDER BY time;
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
FROM "log_history"
         INNER JOIN inventories_log il ON log_history.log_id = il.history_id
WHERE log_id IN :log_ids
ORDER BY time DESC;
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
FROM "log_history"
         INNER JOIN inventories_log il ON log_history.log_id = il.history_id
WHERE log_id IN :log_ids
ORDER BY time;
-- #            }
-- #            {entities
-- #                :log_ids list:int
SELECT log_id,
       el.entityfrom_id,
       el.entityfrom_nbt,
       x,
       y,
       z,
       action
FROM "log_history"
         INNER JOIN entities_log el ON log_history.log_id = el.history_id
         INNER JOIN entities e ON e.uuid = el.entityfrom_uuid
WHERE log_id IN :log_ids
ORDER BY time DESC;
-- #            }
-- #            {block
-- #                :min_x int
-- #                :max_x int
-- #                :min_y int
-- #                :max_y int
-- #                :min_z int
-- #                :max_z int
-- #                :world_name string
-- #                :limit int
-- #                :offset int
SELECT COUNT(*) OVER () AS cnt_rows,
       bl.old_id,
       bl.old_meta,
       bl.old_nbt,
       bl.new_id,
       bl.new_meta,
       bl.new_nbt,
       e.entity_name    AS entity_from,
       x,
       y,
       z,
       world_name,
       action,
       time,
       "rollback"
FROM "log_history"
         INNER JOIN "entities" e ON log_history.who = e.uuid
         INNER JOIN "blocks_log" bl ON log_history.log_id = bl.history_id
WHERE (x BETWEEN :min_x AND :max_x)
  AND (y BETWEEN :min_y AND :max_y)
  AND (z BETWEEN :min_z AND :max_z)
  AND world_name = :world_name
  AND action BETWEEN 0 AND 2
ORDER BY time DESC
LIMIT :limit OFFSET :offset;
-- #            }
-- #            {entity
-- #                :min_x int
-- #                :max_x int
-- #                :min_y int
-- #                :max_y int
-- #                :min_z int
-- #                :max_z int
-- #                :world_name string
-- #                :limit int
-- #                :offset int
SELECT COUNT(*) OVER () AS cnt_rows,
       e1.entity_name   AS entity_from,
       e2.entity_name   AS entity_to,
       x,
       y,
       z,
       world_name,
       action,
       time,
       "rollback"
FROM "log_history"
         INNER JOIN "entities" e1 ON log_history.who = e1.uuid
         INNER JOIN "entities_log" el ON log_history.log_id = el.history_id
         INNER JOIN "entities" e2 ON el.entityfrom_uuid = e2.uuid
WHERE (x BETWEEN :min_x AND :max_x)
  AND (y BETWEEN :min_y AND :max_y)
  AND (z BETWEEN :min_z AND :max_z)
  AND world_name = :world_name
  AND action BETWEEN 3 AND 5
ORDER BY time DESC
LIMIT :limit OFFSET :offset;
-- #            }
-- #            {near
-- #                :min_x int
-- #                :max_x int
-- #                :min_y int
-- #                :max_y int
-- #                :min_z int
-- #                :max_z int
-- #                :world_name string
-- #                :limit int
-- #                :offset int
SELECT COUNT(*) OVER () AS cnt_rows,
       bl.old_id,
       bl.old_meta,
       bl.old_nbt,
       bl.new_id,
       bl.new_meta,
       bl.new_nbt,
       e1.entity_name   AS entity_from,
       e2.entity_name   AS entity_to,
       x,
       y,
       z,
       world_name,
       action,
       time,
       "rollback"
FROM "log_history"
         LEFT JOIN "entities" e1 ON log_history.who = e1.uuid
         LEFT JOIN "entities_log" el ON log_history.log_id = el.history_id
         LEFT JOIN "entities" e2 ON el.entityfrom_uuid = e2.uuid
         LEFT JOIN "blocks_log" bl ON log_history.log_id = bl.history_id
WHERE (x BETWEEN :min_x AND :max_x)
  AND (y BETWEEN :min_y AND :max_y)
  AND (z BETWEEN :min_z AND :max_z)
  AND world_name = :world_name
  AND action BETWEEN 0 AND 5
ORDER BY time DESC
LIMIT :limit OFFSET :offset;
-- #            }
-- #            {transaction
-- #                :min_x int
-- #                :max_x int
-- #                :min_y int
-- #                :max_y int
-- #                :min_z int
-- #                :max_z int
-- #                :world_name string
-- #                :limit int
-- #                :offset int
SELECT COUNT(*) OVER () AS cnt_rows,
       il.old_id,
       il.old_meta,
       il.old_nbt,
       il.old_amount,
       il.new_id,
       il.new_meta,
       il.new_nbt,
       il.new_amount,
       e.entity_name    AS entity_from,
       x,
       y,
       z,
       world_name,
       action,
       time,
       "rollback"
FROM "log_history"
         INNER JOIN "entities" e ON log_history.who = e.uuid
         INNER JOIN "inventories_log" il on log_history.log_id = il.history_id
WHERE (x BETWEEN :min_x AND :max_x)
  AND (y BETWEEN :min_y AND :max_y)
  AND (z BETWEEN :min_z AND :max_z)
  AND world_name = :world_name
  AND action BETWEEN 6 AND 7
ORDER BY time DESC
LIMIT :limit OFFSET :offset;
-- #            }
-- #        }
-- #    }
-- #    {purge
-- #        {time
-- #            :time float
DELETE
FROM log_history
WHERE time < (SELECT STRFTIME('%s', 'now')) - :time;
-- #        }
-- #        {world
-- #            :time float
-- #            :world_name string
DELETE
FROM log_history
WHERE (time < (SELECT STRFTIME('%s', 'now')) - :time)
  AND world_name = :world_name;
-- #        }
-- #    }
-- #}