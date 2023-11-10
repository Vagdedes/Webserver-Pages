<?php
require '/var/www/.structure/library/base/form.php';
$month = get_form_get("month");
$year = get_form_get("year");


if (is_numeric($month) && is_numeric($year)) {
    require '/var/www/.structure/library/base/communication.php';

    if (is_private_connection()) {
        require '/var/www/.structure/library/base/requirements/account_systems.php';
        $formAmount = get_form_get("amount");
        $ignoreFees = !empty(get_form_get("ignoreFees"));
        $hasAmount = is_numeric($formAmount);

        // Separator
        $standardTax = 1.24;
        $monthString = ($month < 10 ? "0" . $month : $month);
        $previousMonth = $monthString - 1;
        $potentialPreviousYear = $year;

        if ($previousMonth == 0) {
            $previousMonth = 12;
            $potentialPreviousYear = $year - 1;
        } else if ($previousMonth < 10) {
            $previousMonth = "0" . $previousMonth;
        }
        $currentMonthDays = cal_days_in_month(CAL_GREGORIAN, $monthString, $year);
        $previousMonthDays = cal_days_in_month(CAL_GREGORIAN, $previousMonth, $potentialPreviousYear);
        $startDate = $potentialPreviousYear . "-" . $previousMonth . "-" . $previousMonthDays . " 22:00:00"; // GMT+2
        $endDate = $year . "-" . $monthString . "-" . $currentMonthDays . " 21:59:59"; // GMT +2

        // Separator
        $results = array();
        $totalAmount = 0;
        $totalFee = 0;
        $totalTax = 0;

        // Separator
        $blacklist = get_sql_query(
            "personal.expensesBlacklist",
            array("transaction_key", "transaction_value"),
            array(
                array("deletion_date", null)
            )
        );

        // Separator
        $totalString = "total";
        $transactions = get_all_paypal_transactions(0, $startDate);

        if (!empty($transactions)) {
            $failedTransactions = get_failed_paypal_transactions(null, 0, $startDate);

            foreach ($transactions as $transactionID => $transaction) {
                foreach ($blacklist as $blacklisted) {
                    if (isset($transaction->{$blacklisted->transaction_key})
                        && $transaction->{$blacklisted->transaction_key} == $blacklisted->transaction_value) {
                        continue 2;
                    }
                }
                $date = str_replace("T", " ", str_replace("Z", "", $transaction->ORDERTIME));
                $fee = 0.0;
                $hasFees = isset($transaction->FEEAMT);

                if ($date >= $startDate && $date <= $endDate && ($ignoreFees || $hasFees)) {
                    $fee = $hasFees ? $transaction->FEEAMT : 0.0;

                    if ($ignoreFees || $fee != 0.0) {
                        $fee = abs($fee);
                        $amount = $transaction->AMT;
                        $beforeTax = $amount - $fee;
                        $tax = cut_decimal($beforeTax - ($beforeTax / $standardTax), 2);
                        $currency = $transaction->CURRENCYCODE;
                        $receivers = array(
                            $totalString,
                            isset($transaction->RECEIVERBUSINESS) ? "paypal:" . $transaction->RECEIVERBUSINESS : "Unknown"
                        );

                        $object = new stdClass();
                        $object->date = $date;
                        $object->amount = $beforeTax . " " . $currency;
                        $object->name = $transaction->FIRSTNAME . " " . $transaction->LASTNAME;
                        $object->email = $transaction->EMAIL;
                        $object->details = get_domain() . "/contents/?path=paypal/view&id=" . $transactionID;
                        $object->country = code_to_country($transaction->COUNTRYCODE);

                        if (!in_array($transactionID, $failedTransactions)) {
                            if (!$hasAmount || $formAmount == $beforeTax) {
                                foreach ($receivers as $receiver) {
                                    if (!array_key_exists($receiver, $results)) {
                                        $resultObject = new stdClass();
                                        $resultObject->profit_before_tax = $beforeTax;
                                        $resultObject->profit_after_tax = ($beforeTax - $tax);
                                        $resultObject->fees = $fee;
                                        $resultObject->tax = $tax;
                                        $resultObject->loss = 0.0;

                                        if ($receiver != $totalString) {
                                            $array = array();
                                            $array[strtotime($date)] = $object;
                                            $resultObject->succesful_transactions = $array;
                                            $resultObject->failed_transactions = array();
                                        }
                                        $results[$receiver] = $resultObject;
                                    } else {
                                        $resultObject = $results[$receiver];
                                        $resultObject->profit_before_tax += $beforeTax;
                                        $resultObject->profit_after_tax += ($beforeTax - $tax);
                                        $resultObject->fees += $fee;
                                        $resultObject->tax += $tax;

                                        if ($receiver != $totalString) {
                                            $resultObject->succesful_transactions[strtotime($date)] = $object;
                                            ksort($resultObject->succesful_transactions);
                                        }
                                    }
                                }
                            }
                        } else {
                            foreach ($receivers as $receiver) {
                                if (!array_key_exists($receiver, $results)) {
                                    $resultObject = new stdClass();
                                    $resultObject->profit_before_tax = 0;
                                    $resultObject->profit_after_tax = 0;
                                    $resultObject->fees = 0;
                                    $resultObject->tax = 0;
                                    $resultObject->loss = $beforeTax;

                                    if ($receiver != $totalString) {
                                        $array = array();
                                        $array[strtotime($date)] = $object;
                                        $resultObject->succesful_transactions = array();
                                        $resultObject->failed_transactions = $array;
                                    }
                                    $results[$receiver] = $resultObject;
                                } else {
                                    $resultObject = $results[$receiver];
                                    $resultObject->loss += $beforeTax;

                                    if ($receiver != $totalString) {
                                        $resultObject->failed_transactions[strtotime($date)] = $object;
                                        ksort($resultObject->failed_transactions);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // Separator
        $transactions = get_all_stripe_transactions(0, true, $startDate);

        if (!empty($transactions)) {
            $failedTransactions = get_failed_stripe_transactions(null, 0, $startDate);

            foreach ($transactions as $transactionID => $transaction) {
                foreach ($blacklist as $blacklisted) {
                    if (isset($transaction->{$blacklisted->transaction_key})
                        && $transaction->{$blacklisted->transaction_key} == $blacklisted->transaction_value) {
                        continue 2;
                    }
                }
                $date = date("Y-m-d H:i:s", $transaction->created);
                $hasFees = isset($transaction->fee);

                if ($date >= $startDate && $date <= $endDate && ($ignoreFees || $hasFees)) {
                    $fee = $hasFees ? $transaction->fee : 0;

                    if ($ignoreFees || $fee != 0) {
                        $fee = $fee / 100.0;
                        $amount = $transaction->amount / 100.0;
                        $beforeTax = $amount - $fee;
                        $tax = cut_decimal($beforeTax - ($beforeTax / $standardTax), 2);
                        $currency = strtoupper($transaction->currency);
                        $receivers = array(
                            $totalString,
                            "stripe"
                        );

                        $object = new stdClass();
                        $object->date = $date;
                        $object->name = get_object_depth_key($transaction, "source.billing_details.name")[1];
                        $object->email = get_object_depth_key($transaction, "source.billing_details.email")[1];
                        $object->amount = $beforeTax . " " . $currency;
                        $object->details = get_domain() . "/contents/?path=stripe/view&id=" . $transactionID;

                        if (!in_array($transactionID, $failedTransactions)) {
                            if (!$hasAmount || $formAmount == $beforeTax) {
                                foreach ($receivers as $receiver) {
                                    if (!array_key_exists($receiver, $results)) {
                                        $resultObject = new stdClass();
                                        $resultObject->profit_before_tax = $beforeTax;
                                        $resultObject->profit_after_tax = ($beforeTax - $tax);
                                        $resultObject->fees = $fee;
                                        $resultObject->tax = $tax;
                                        $resultObject->loss = 0.0;

                                        if ($receiver != $totalString) {
                                            $array = array();
                                            $array[strtotime($date)] = $object;
                                            $resultObject->succesful_transactions = $array;
                                            $resultObject->failed_transactions = array();
                                        }
                                        $results[$receiver] = $resultObject;
                                    } else {
                                        $resultObject = $results[$receiver];
                                        $resultObject->profit_before_tax += $beforeTax;
                                        $resultObject->profit_after_tax += ($beforeTax - $tax);
                                        $resultObject->fees += $fee;
                                        $resultObject->tax += $tax;

                                        if ($receiver != $totalString) {
                                            $resultObject->succesful_transactions[strtotime($date)] = $object;
                                            ksort($resultObject->succesful_transactions);
                                        }
                                    }
                                }
                            }
                        } else {
                            foreach ($receivers as $receiver) {
                                if (!array_key_exists($receiver, $results)) {
                                    $resultObject = new stdClass();
                                    $resultObject->profit_before_tax = 0;
                                    $resultObject->profit_after_tax = 0;
                                    $resultObject->fees = 0;
                                    $resultObject->tax = 0;
                                    $resultObject->loss = $beforeTax;

                                    if ($receiver != $totalString) {
                                        $array = array();
                                        $array[strtotime($date)] = $object;
                                        $resultObject->succesful_transactions = array();
                                        $resultObject->failed_transactions = $array;
                                    }
                                    $results[$receiver] = $resultObject;
                                } else {
                                    $resultObject = $results[$receiver];
                                    $resultObject->loss += $beforeTax;

                                    if ($receiver != $totalString) {
                                        $resultObject->failed_transactions[strtotime($date)] = $object;
                                        ksort($resultObject->failed_transactions);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // Separator
        $application = new Application(null);
        $products = $application->getAccount(0);
        $products = $products->getProduct()->find(null, false);

        if ($products->isPositiveOutcome()) {
            $receivers = array(
                $totalString,
                "builtbybit"
            );
            $fee = 0.0;
            $currency = "USD";

            foreach ($products->getObject() as $product) {
                if (!$product->is_free
                    && isset($product->identification[AccountAccounts::BUILTBYBIT_URL])) {
                    $ownerships = get_builtbybit_resource_ownerships(
                        $product->identification[AccountAccounts::BUILTBYBIT_URL],
                    );
                    $amount = array_shift($product->tiers->paid)->price;
                    $beforeTax = $amount - $fee;
                    $tax = cut_decimal($beforeTax - ($beforeTax / $standardTax), 2);

                    foreach ($ownerships as $ownership) {
                        $date = $ownership->creation_date;
                        $object = new stdClass();
                        $object->user = $ownership->user;
                        $object->date = $date;
                        $object->amount = $beforeTax . " " . $currency;
                        $object->details = $ownership->transaction_id;

                        if ($ownership->creation_date >= $startDate
                            && $ownership->creation_date <= $endDate) {
                            foreach ($receivers as $receiver) {
                                if (!array_key_exists($receiver, $results)) {
                                    $resultObject = new stdClass();
                                    $resultObject->profit_before_tax = $beforeTax;
                                    $resultObject->profit_after_tax = ($beforeTax - $tax);
                                    $resultObject->fees = 0.0;
                                    $resultObject->tax = $tax;

                                    if ($receiver != $totalString) {
                                        $array = array();
                                        $array[strtotime($date)] = $object;
                                        $resultObject->succesful_transactions = $array;
                                    }
                                    $results[$receiver] = $resultObject;
                                } else {
                                    $resultObject = $results[$receiver];
                                    $resultObject->profit_before_tax += $beforeTax;
                                    $resultObject->profit_after_tax += ($beforeTax - $tax);
                                    $resultObject->fees += $fee;
                                    $resultObject->tax += $tax;

                                    if ($receiver != $totalString) {
                                        $resultObject->succesful_transactions[strtotime($date)] = $object;
                                        ksort($resultObject->succesful_transactions);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // Separator
        $patreon = get_patreon2_subscriptions();

        if (!empty($patreon)) {
            $receivers = array(
                $totalString,
                "patreon"
            );
            $feePercentage = 0.08; // Patreon Fee
            $currency = "EUR";

            foreach ($patreon as $patron) {
                $date = $patron->attributes->last_charge_date;

                if ($date !== null) {
                    $amount = $patron->attributes->currently_entitled_amount_cents / 100.0;
                    $fee = $amount * $feePercentage;
                    $beforeTax = $amount - $fee;
                    $tax = cut_decimal($beforeTax - ($beforeTax / $standardTax), 2);

                    $object = new stdClass();
                    $object->user = $patron->attributes->full_name;
                    $object->date = $date;
                    $object->amount = $beforeTax . " " . $currency;

                    foreach ($receivers as $receiver) {
                        if (!array_key_exists($receiver, $results)) {
                            $resultObject = new stdClass();
                            $resultObject->profit_before_tax = $beforeTax;
                            $resultObject->profit_after_tax = ($beforeTax - $tax);
                            $resultObject->fees = 0.0;
                            $resultObject->tax = $tax;

                            if ($receiver != $totalString) {
                                $array = array();
                                $array[strtotime($date)] = $object;
                                $resultObject->succesful_transactions = $array;
                            }
                            $results[$receiver] = $resultObject;
                        } else {
                            $resultObject = $results[$receiver];
                            $resultObject->profit_before_tax += $beforeTax;
                            $resultObject->profit_after_tax += ($beforeTax - $tax);
                            $resultObject->fees += $fee;
                            $resultObject->tax += $tax;

                            if ($receiver != $totalString) {
                                $resultObject->succesful_transactions[strtotime($date)] = $object;
                                ksort($resultObject->succesful_transactions);
                            }
                        }
                    }
                }
            }
        }

        if (!empty($results)) {
            header('Content-type: Application/JSON');
            echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            echo "<b>No information found</b>";
        }
    }
}