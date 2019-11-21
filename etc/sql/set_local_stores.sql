use ezfw;

UPDATE stores SET flags=REPLACE(flags, 'local', '') WHERE flags     LIKE '%local%' AND tag NOT IN ( 'buffer', 'history', 'purge' );
UPDATE stores SET flags=CONCAT(flags, ',local')     WHERE flags NOT LIKE '%local%' AND tag     IN ( 'buffer', 'history', 'purge' );

