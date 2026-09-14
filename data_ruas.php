<?php
// Alias redirect to data_unit_ruas.php
$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: data_unit_ruas.php" . $query);
exit;
