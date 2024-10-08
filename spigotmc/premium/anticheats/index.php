<!DOCTYPE html>
<html lang="en">
<head>
    <?php
    require '/var/www/.structure/library/base/utilities.php';
    ini_set('user_agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36');
    $title = "Anti Cheat List (Bukkit & Spigot)";
    $domain = get_domain();
    //$title = explode(" | ", getTitle("https://www.spigotmc.org/wiki/anti-cheat-list-bukkit-and-spigot/"))[0];
    ?>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title><?php echo $title; ?></title>
    <meta name="description"
          content="A list of all premium anti-cheats available on the SpigotMC platform, rated from best to worst by the community.">
    <link rel="shortcut icon" type="image/png" href="https://<?php echo $domain; ?>/.images/bedrockIcon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet"
          href='https://<?php echo $domain; ?>/.css/universal.css?id="<?php echo rand(0, 2147483647) ?>'>
</head>
<body>

<div class="area">
    <div class="area_logo">
        <div class='search'>
            <ul>
                <li class='search_top'></li>
                <li class='search_bottom'></li>
            </ul>
        </div>
    </div>
    <div class="area_title">
        <?php echo $title; ?>
    </div>
    <div class="area_text">
        List is based on the <a href='https://spigotmc.org/resources'>SpigotMC</a> platform and its
        <a href='https://gist.github.com/Phoenix616/c757c627c5f3507b42b294caa948a17d'>weighted rating algorithm</a>.
    </div>
    <div class="area_list" id="legal">
        <ul>
            <?php
            require '/var/www/.structure/library/base/form.php';
            require_once '/var/www/.structure/library/base/communication.php';

            // Separator
            function ceilRating($decimal)
            {
                $base = (int)$decimal;
                $decimal -= $base;
                $decimal = ceil($decimal * 1000);
                return $base + ($decimal / 1000);
            }

            // Separator
            $anticheats = array();
            $query = get_sql_query(
                "anticheat.spigotmc_premium_anticheats",
                array("resource_id")
            );

            if (!empty($query)) {
                foreach ($query as $row) {
                    $anticheats[] = $row->resource_id;
                }
            }

            // Separator
            $order = array();
            $identification = array();
            $downloadsTrackingTable = "anticheat.spigotmc_downloads_tracking";

            foreach ($anticheats as $anticheat) {
                $url = "https://api.spigotmc.org/simple/0.2/index.php?action=getResource&id=" . $anticheat;
                $urlKey = string_to_integer($url);
                $contents = get_key_value_pair($urlKey);

                if (!is_object($contents)) {
                    $contents = timed_file_get_contents($url, 3);

                    if ($contents === false) {
                        $contents = null;
                    } else {
                        $contents = json_decode($contents, false);
                        set_key_value_pair($urlKey, $contents, "1 hour");
                    }
                }

                if (is_object($contents)) {
                    $stats = $contents->{"stats"};
                    $premium = $contents->{"premium"};
                    $downloads = $stats->{"downloads"};
                    $ratingsCount = $stats->{"reviews"}->{"unique"};
                    $averageRating = $stats->{"rating"};

                    $averageRating = (10 * 3 + $averageRating * $ratingsCount) / (10 + $ratingsCount);
                    $keyRating = (string)($averageRating * pow(10, 10));

                    $price = $premium->{"price"};
                    $currency = strtoupper($premium->{"currency"});

                    $identification[$keyRating] = array($anticheat, $contents->{"title"}, $downloads, $averageRating, $ratingsCount, $price, $currency);
                    $order[] = $keyRating;

                    // Separator
                    $downloadsTrackingQuery = get_sql_query(
                        $downloadsTrackingTable,
                        array("resource_id"),
                        array(
                            array("resource_id", $anticheat),
                            array("downloads", $downloads)
                        ),
                        null,
                        1
                    );

                    if (empty($downloadsTrackingQuery)) {
                        sql_insert(
                            $downloadsTrackingTable,
                            array(
                                "resource_id" => $anticheat,
                                "downloads" => $downloads,
                                "date" => get_current_date()
                            )
                        );
                    }
                } else {
                    $order = array();
                    break;
                }
            }
            rsort($order);

            // Separator
            $year = get_form_get("year");
            $month = get_form_get("month");
            $sales = array();

            $showSales = is_numeric($year) && is_numeric($month)
                && $month >= 1 && $month <= 12
                && is_private_connection();

            if ($showSales) {
                $query = get_sql_query(
                    $downloadsTrackingTable,
                    array("resource_id", "downloads"),
                    array(
                        array("date", get_current_date())
                    )
                );

                if (!empty($query)) {
                    $min = array();
                    $max = array();
                    $lastMonth = $month == 1;
                    $previousMonth = $lastMonth ? 12 : $month;
                    $previousOrCurrentYear = $lastMonth ? ($year - 1) : $year;
                    $previousMonthDays = cal_days_in_month(CAL_GREGORIAN, $previousMonth, $previousOrCurrentYear);

                   foreach ($query as $row){
                        $date = explode("-", $row->date);
                        $loopYear = $date[0];
                        $loopMonth = $date[1];
                        $loopDay = substr($date[2], 0, 2);

                        if ($loopYear == $year && $loopMonth == $month || $loopYear == $previousOrCurrentYear && $loopMonth == $previousMonth && $loopDay == $previousMonthDays) {
                            $resource_id = $row->resource_id;
                            $downloads = $row->downloads;

                            if ($downloads != null) {
                                if (!array_key_exists($resource_id, $min)) {
                                    $min[$resource_id] = $downloads;
                                } else {
                                    $min[$resource_id] = min($min[$resource_id], $downloads);
                                }
                                if (!array_key_exists($resource_id, $max)) {
                                    $max[$resource_id] = $downloads;
                                } else {
                                    $max[$resource_id] = max($max[$resource_id], $downloads);
                                }
                                $sales[$resource_id] = $max[$resource_id] - $min[$resource_id];
                            }
                        }
                    }
                }
            }
            $salesIncluded = !empty($sales);

            // Separator
            if (!empty($order)) {
                $success = false;

                foreach ($order as $position => $keyRating) {
                    $position = "<b>" . add_ordinal_number($position + 1) . "</b>";
                    $data = $identification[$keyRating] ?? null;

                    if ($data !== null) {
                        $success = true;
                        $id = $data[0];

                        // Separator
                        $priceAmount = $data[5];

                        if ($priceAmount - floor($priceAmount) == 0.0) {
                            $priceAmount = (int)$priceAmount;
                        }
                        $currency = $data[6];
                        $price = $priceAmount . " " . $currency;

                        // Separator
                        $title = null;

                        foreach (explode(" ", $data[1]) as $word) {
                            $length = strlen($word);

                            if ($length >= 2 && ($length >= 5 || is_alpha_numeric($word)) && !str_contains(strtolower($word), "inactive")) {
                                $title = $word;
                                break;
                            }
                        }
                        if ($title != null) {
                            $title .= " (" . $price . ")";
                        } else {
                            $title = substr($data[1], 0, 25) . "...";
                        }

                        // Separator
                        $downloads = $data[2];
                        $reviews = $data[4];

                        // Separator
                        $rating = substr($data[3], 0, 5);

                        if ($salesIncluded && !array_key_exists($id, $sales)) {
                            $sales[$id] = 0;
                        }
                        $salesCount = !$showSales || empty($sales) ? 0 : $sales[$id];
                        $salesAmount = $salesCount * (($priceAmount * 0.9685) - 0.32);

                        echo "<li><div class='area_list_title'>$position <a href='https://www.spigotmc.org/resources/$id'>$title</a></div>";
                        $count = "<b>Count</b> $reviews " . ($reviews == 1 ? "Review" : "Reviews");
                        echo "<div class='area_list_contents'>
							<div class='area_list_contents_title'>Rating</div>
							<div class='area_list_contents_box'>
							    $count
							    <br>
								<b>Score</b> $rating/5 Stars
							</div>
							</div>
						<div class='area_list_contents'>
							<div class='area_list_contents_title'>Traffic</div>
							<div class='area_list_contents_box'>
							<b>Downloads</b> $downloads
							</div>
						</div>"
                            . ($salesIncluded ? "<div class='area_list_contents'>
													<div class='area_list_contents_title'>Sales</div>
													<div class='area_list_contents_box'>
													<b>Count</b> $salesCount
													<br>
													<b>Amount</b> $salesAmount $currency
													</div>
												</div>" : "");
                        echo "</li>";
                    }
                }

                if (!$success) {
                    echo "<li><div class='area_list_title'>API Error</a></div>
						<div class='area_list_contents'>An error has been encountered, please check back later.</div>
						</div>";
                }
            } else {
                echo "<li><div class='area_list_title'>API Error</a></div>
						<div class='area_list_contents'>An error has been encountered, please check back later.</div>
						</div>";
            }
            ?>
        </ul>
    </div>
</div>
</body>
</html>
