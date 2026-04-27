<?php
$f = fopen('schema.sql', 'rb');
$content = fread($f, 5000);
// Try to detect if it's UTF-16
if (substr($content, 0, 2) == "\xFF\xFE" || substr($content, 0, 2) == "\xFE\xFF") {
    $content = mb_convert_encoding($content, 'UTF-8', 'UTF-16');
}
echo $content;
