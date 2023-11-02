<?php
header("Content-type: application/pdf");
header("Content-Disposition: inline; filename=filename.pdf");
@readfile("/var/www/.structure/private/resume.pdf");
