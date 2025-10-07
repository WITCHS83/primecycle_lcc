<?php
function try_port($host,$port){
  $t0 = microtime(true);
  $fp = @fsockopen($host, $port, $errno, $errstr, 10);
  $dt = number_format((microtime(true)-$t0)*1000, 0).'ms';
  if ($fp) { echo "OK $host:$port ($dt)\n"; fclose($fp); }
  else { echo "FAIL $host:$port ($errno) $errstr ($dt)\n"; }
}
try_port('smtp.gmail.com',587);
try_port('smtp.gmail.com',465);
try_port('smtp.office365.com',587);
