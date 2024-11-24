<?php
require_once '/var/www/.structure/library/base/communication.php';
require '/var/www/.structure/library/gameCloud/init.php';
echo sizeof(get_sql_query(
    GameCloudVariables::PUNISHED_PLAYERS_TABLE,
    array("id")
));
