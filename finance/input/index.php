<?php
require '/var/www/.structure/library/base/form.php';
$month = get_form_get("month");
$year = get_form_get("year");

if (is_numeric($month) && is_numeric($year)) {
    require '/var/www/.structure/library/base/requirements/account_systems.php';
    require '/var/www/.structure/library/finance/init.php';

    if (is_private_connection()) {
        $results = get_financial_input($year, $month);

        if (!empty($results)) {
            header('Content-type: Application/JSON');
            echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            echo json_encode("No information found");
        }
    }
}