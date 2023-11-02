<?php
require_once '/var/www/.structure/library/base/form.php';
$id = get_form_get("id");

if (!empty($id)) {
    require_once '/var/www/.structure/library/base/communication.php';

    if (is_private_connection()) {
        require_once '/var/www/.structure/library/base/utilities.php';
        require_once '/var/www/.structure/library/stripe/init.php';
        header('Content-type: Application/JSON');
        $object = get_stripe_transaction($id);
        echo json_encode(
            empty($object) ? new stdClass() : $object,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        require_once '/var/www/.structure/library/base/redirect.php';
    }
} else {
    require_once '/var/www/.structure/library/base/redirect.php';
}