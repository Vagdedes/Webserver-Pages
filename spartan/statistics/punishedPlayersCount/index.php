<?php
require_once '/var/www/.structure/library/base/utilities.php';
require_once '/var/www/.structure/library/base/requirements/sql_connection.php';

require_once '/var/www/.structure/library/memory/init.php';
require_once '/var/www/.structure/library/gameCloud/init.php';
set_sql_cache("1 hour");
echo sizeof(get_sql_query(
    $punished_players_table,
    array("id")
));
