-- #!sqlite
-- #{patch
-- #    {0.6.0
-- #        {1
ALTER TABLE log_history ADD UNIQUE(log_id);
-- #        }
-- #        {2
ALTER TABLE blocks_log ADD UNIQUE(history_id);
-- #        }
-- #        {3
ALTER TABLE entities_log ADD UNIQUE(history_id);
-- #        }
-- #        {4
ALTER TABLE inventories_log ADD UNIQUE(history_id);
-- #        }
-- #    }
-- #}