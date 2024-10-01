<?php
require '/var/www/.structure/library/base/form.php';
$month = get_form_get("month");
$year = get_form_get("year");

if (is_numeric($month) && is_numeric($year)) {
    require_once '/var/www/.structure/library/base/communication.php';

    if (is_private_connection()) {
        $text = private_file_get_contents("https://www.vagdedes.com/finance/input/?year=$year&month=$month");

        if ($text !== false) {
            $json = json_decode($text, true);
            $array = array();
            $key = get_form_get("key");

            foreach (($json[$key]["succesful_transactions"] ?? array()) as $value) {
                $country = $value["country"];
                unset($value["country"]);

                if (array_key_exists($country, $array)) {
                    $array[$country][] = $value;
                } else {
                    $array[$country] = array($value);
                }
            }
            header('Content-type: Application/JSON');
            echo json_encode($array, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
}