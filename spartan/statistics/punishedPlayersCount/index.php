<?php
require_once '/var/www/.structure/library/base/communication.php';
require '/var/www/.structure/library/gameCloud/init.php';
echo sizeof(get_sql_query(
    $punished_players_table,
    array("id")
));
