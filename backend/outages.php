<?php

date_default_timezone_set('America/Regina');

$log_file = fopen('outages.log', 'r');
while (($line = fgets($log_file)) !== false) {
  if (!empty(trim($line))) {
    [$start, $end] = explode(' ', trim($line));
    echo date('D Y-m-d H:i:s', $start) . ' -> ' . date('D Y-m-d H:i:s', $end) . PHP_EOL;
  }
}

