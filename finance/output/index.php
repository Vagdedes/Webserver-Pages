<?php
require '/var/www/.structure/library/base/form.php';
$month = get_form_get("month");
$year = get_form_get("year");

if (is_numeric($month) && is_numeric($year) && $month >= 1 && $month <= 12) {
    require '/var/www/.structure/library/base/communication.php';

    if (is_private_connection()) {
        require_once '/var/www/.structure/library/base/utilities.php';
        require '/var/www/.structure/library/memory/init.php';
        require '/var/www/.structure/library/paypal/init.php';
        $table = "personal.expenses";
        $cacheKey = $table . $month . $year;

        // Separator

        if (!empty(get_form_post("method"))) {
            $formName = get_form_post("formName");
            $formAmount = get_form_post("formAmount");

            if (!empty($formName) && is_numeric($formAmount)) {
                $currentDate = date("Y-m-d H:i:s");
                $query = sql_query("SELECT amount, transaction_date FROM $table WHERE transaction_name = '$formName' AND month = '$month' AND year = '$year';");
                $continue = true;

                if (isset($query->num_rows) && $query->num_rows > 0) {
                    while ($row = $query->fetch_assoc()) {
                        $loopDate = $row["transaction_date"];

                        if ($row["amount"] == $formAmount || abs(strtotime($currentDate) - strtotime($loopDate)) > 300) {
                            $continue = false;
                            break;
                        }
                    }
                }

                if ($continue) {
                    clear_memory(array($cacheKey), true);
                    sql_insert(
                        $table,
                        array(
                            "year" => $year,
                            "month" => $month,
                            "transaction_name" => $formName,
                            "amount" => $formAmount,
                            "transaction_date" => $currentDate
                        )
                    );
                }
            }
        } else {
            $array = get_key_value_pair($cacheKey);

            if ($array === null) {
                $array = array();

                for ($x = 0; $x < 2; $x++) {
                    $business = $x == 0;
                    $cacheKey = $table . $month . $year . $business;

                    if ($business) {
                        access_business_paypal_account();
                    } else {
                        access_personal_paypal_account();
                    }

                    // Separator
                    $currentDate = date("Y-m-d H:i:s");
                    $yearMonth = $year . "-" . ($month < 10 ? "0" . $month : $month) . "-";
                    $startDate = "T00:00:00Z";
                    $endDate = "T23:59:59Z";
                    $dayMultiplier = 5;
                    $monthDays = 30;

                    for ($e = 0; $e < round($monthDays / $dayMultiplier); $e++) {
                        $loopStartDay = $e * $dayMultiplier;
                        $loopLastDay = $loopStartDay + $dayMultiplier;
                        $loopStartDay += 1;
                        $loopStartDate = $yearMonth . ($loopStartDay < 10 ? "0" . $loopStartDay : $loopStartDay) . $startDate;

                        if ($currentDate >= str_replace("T", " ", str_replace("Z", "", $loopStartDate))) {
                            $loopEndDate = $yearMonth . ($loopLastDay < 10 ? "0" . $loopLastDay : $loopLastDay) . $endDate;
                            $transaction = search_paypal_transactions("STARTDATE=$loopStartDate&ENDDATE=$loopEndDate&TRANSACTIONCLASS=Sent&STATUS=Success");

                            if (is_array($transaction)) {
                                for ($i = 0; $i < sizeof($transaction); $i++) {
                                    $majorKey = "L_NAME" . $i;

                                    if (array_key_exists($majorKey, $transaction)) {
                                        $loopName = $transaction[$majorKey];
                                        $loopAmount = abs($transaction["L_AMT" . $i]);

                                        if (array_key_exists($loopName, $array)) {
                                            $array[$loopName] += $loopAmount;
                                        } else {
                                            $array[$loopName] = $loopAmount;
                                        }
                                    } else {
                                        break;
                                    }
                                }
                            } else {
                                $array["PayPal " . ($business ? "Business" : "Personal") . " " . $x] = "Offline";
                            }
                        } else {
                            break;
                        }
                    }
                }

                // Separator
                set_sql_cache(null, $table);
                $query = get_sql_query(
                    $table,
                    null,
                    array(
                        array("month", $month),
                        array("year", $year)
                    )
                );

                if (!empty($query)) {
                    foreach ($query as $object) {
                        $loopName = $object->transaction_name;
                        $loopAmount = $object->amount;

                        if (array_key_exists($loopName, $array)) {
                            $array[$loopName] += $loopAmount;
                        } else {
                            $array[$loopName] = $loopAmount;
                        }
                    }
                }
                set_key_value_pair($cacheKey, $array, "10 minutes");
            }

            if (!empty($array)) {
                $total = 0;

                foreach ($array as $loopName => $loopAmount) {
                    if (is_numeric($loopAmount)) {
                        if ($loopAmount != 0) {
                            echo $loopName . ": " . $loopAmount . "<br>";
                            $total += $loopAmount;
                        }
                    } else {
                        echo $loopName . ": " . $loopAmount . "<br>";
                    }
                }
                echo "<b>Total: $total</b>";

                echo "<p><form method='post' style='margin: 0; padding: 0;'>
                    <input type='text' name='formName' placeholder='Name' style='margin: 0; padding: 0;'>
                    <br>
                    <input type='text' name='formAmount' placeholder='Price' style='margin: 0; padding: 0;'>
                    <br>
                    <input type='submit' name='method' value='Submit' style='margin: 0; padding: 0;'>
                </form>";
            } else {
                echo "<b>No information found</b>";
            }
        }
    }
}