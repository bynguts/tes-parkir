<?php
$hash = '$2y$10$4OYARUBnvQ28t/cDpl8gpO9YRhYljy4w48wkvwlI/UFt8y6jL/35m';
echo "admin123: " . (password_verify('admin123', $hash) ? 'yes' : 'no') . "\n";
echo "password: " . (password_verify('password', $hash) ? 'yes' : 'no') . "\n";
echo "operator123: " . (password_verify('operator123', $hash) ? 'yes' : 'no') . "\n";
echo "123456: " . (password_verify('123456', $hash) ? 'yes' : 'no') . "\n";
