<?php
require_once '/var/www/.structure/library/base/requirements/account_systems.php';
$description = "Welcome to my personal website. Here you will find my related projects and ways to communicate.";
?>

<!DOCTYPE html>
<html lang='en'>
<head>
    <?php echo get_google_analytics(); ?>
    <title>Evangelos Dedes | Software Engineer</title>
    <?php echo "<meta name='description' content='" . $description . "'>"; ?>
    <?php echo "<link rel='shortcut icon' type='image/png' href='" . Account::IMAGES_PATH . "icon.png'>"; ?>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <?php
    $randomNumber = random_number();
    echo "<link rel='stylesheet' href='" . Account::WEBSITE_DESIGN_PATH . "universal.css?id=$randomNumber>'>";
    ?>
</head>
<body>

<div class='area'>
    <div class='area_logo'>
        <img src='https://vagdedes.com/.images/me.png' alt='area_logo'>
    </div>
    <div class='area_title'>
        Evangelos Dedes
    </div>
    <div class='area_text'>
        Software Engineer
    </div>
</div>

<div class='area' id='darker'>
    <div class='product_list'>
        <ul>
            <li id="left">
                <a href='https://github.com/Vagdedes'>
                    <div class='product_list_contents'
                         style="background-image: url('https://vagdedes.com/.images/github.png');">
                        <div class='product_list_title'>GitHub</div>
                    </div>
                </a>
            </li>
            <li>
                <a href='https://www.linkedin.com/in/Vagdedes/'>
                    <div class='product_list_contents'
                         style="background-image: url('https://vagdedes.com/.images/linkedin.png');">
                        <div class='product_list_title'>LinkedIn</div>
                    </div>
                </a>
            </li>
            <li id="left">
                <a href='https://www.patreon.com/Vagdedes'>
                    <div class='product_list_contents'
                         style="background-image: url('https://vagdedes.com/.images/patreon.png');">
                        <div class='product_list_title'>Patreon</div>
                    </div>
                </a>
            </li>
            <li>
                <a href='https://vagdedes.com/discord'>
                    <div class='product_list_contents'
                         style="background-image: url('https://vagdedes.com/.images/discord.png');">
                        <div class='product_list_title'>Discord</div>
                    </div>
                </a>
            </li>
            <li id="left">
                <a href='https://vagdedes.com/resume'>
                    <div class='product_list_contents'
                         style="background-image: url('https://vagdedes.com/.images/simple.png');">
                        <div class='product_list_title'>Resume</div>
                    </div>
                </a>
            </li>
        </ul>
    </div>
</div>

<div class="footer">
    <div class="footer_center">
        <div class="footer_top">
            <a href="https://docs.google.com/forms/d/e/1FAIpQLSfTrwwKYw0npNxBkuxuHzu9WnD0xwzbSf09FA6XbAMVGcHFJw/viewform" class="selection" id="hover">CONTACT ME</a>
        </div>
        <div class="footer_bottom">
            @<?php echo date("Y") . " Vagdedes.com"; ?>
            <br>
            <div style="font-size: 12px;">
                All rights reserved.
            </div>
        </div>
    </div>
</div>

</body>
