<?php
 $fromdir = './data/hsl';
 $todir = './tiles/hsl';

 $routedir = "$todir/route";
 $servicedir = "$todir/service";
 $shapedir = "$todir/shape";
 $stopdir = "$todir/stop";
 $tmpdir = "$todir/tmp";

 define('DEBUG', true);
 define('READ_BUFFER_LENGTH', 4096);

 if (!is_dir(dirname($todir))) {
  mkdir(dirname($todir));
 }

 if (!is_dir($todir)) {
  mkdir($todir);
 }

 if (!is_dir($tmpdir)) {
  mkdir($tmpdir);
 }

 debug("*** Split calendar ***");
 $rh = fopen("$fromdir/calendar.txt", r);
 while (($row = stream_get_line($rh, READ_BUFFER_LENGTH, "\n")) !== false) {
  $data = explode(',', $row);
  $service_id = $data[0];
  $tmpservicedir = "$tmpdir/service/$service_id";
  @mkdir("$tmpdir/service");
  @mkdir($tmpservicedir);
  file_put_contents("$tmpservicedir/$service_id.txt", $row);
  debug($service_id);
 }
 if (!feof($rh)) {
  trigger_error('Error reading calendar.txt', E_USER_WARNING);
 }
 fclose($rh);

 debug("*** Split calendar dates ***");
 $rh = fopen("$fromdir/calendar_dates.txt", r);
 while (($row = stream_get_line($rh, READ_BUFFER_LENGTH, "\n")) !== false) {
  $data = explode(',', $row);
  $service_id = $data[0];
  $tmpservicedir = "$tmpdir/service/$service_id";
  @mkdir("$tmpdir/service");
  @mkdir($tmpservicedir);
  file_put_contents("$tmpservicedir/exceptions.txt", "$data[1] $data[2]\n", FILE_APPEND);
  debug($service_id);
 }
 if (!feof($rh)) {
  trigger_error('Error reading calendar_dates.txt', E_USER_WARNING);
 }
 fclose($rh);

 debug("*** Split routes ***");
 if (!is_dir($routedir)) {
  mkdir($routedir);
 }
 $rh = fopen("$fromdir/routes.txt", r);
 while (($row = stream_get_line($rh, READ_BUFFER_LENGTH, "\n")) !== false) {
  $data = explode(',', $row);
  $route_id = $data[0];
  @mkdir("$routedir/$route_id");
  file_put_contents("$routedir/$route_id/$route_id.txt", $row);
  debug($route_id);
 }
 if (!feof($rh)) {
  trigger_error('Error reading routes.txt', E_USER_WARNING);
 }
 fclose($rh);

 debug("*** Split shapes ***");
 if (!is_dir($shapedir)) {
  mkdir($shapedir);
 }
 $rh = fopen("$fromdir/shapes.txt", r);
 while (($row = stream_get_line($rh, READ_BUFFER_LENGTH, "\n")) !== false) {
  $data = explode(',', $row);
  $shape_id = $data[0];
  $point_id = $shape_id.'-'.$data[3];
  $point = $data[1].','.$data[2];
  if (!is_dir("$shapedir/$shape_id")) {
   mkdir("$shapedir/$shape_id");
  }
  if ($data[3] == 1) {
   file_put_contents("$shapedir/$shape_id/$shape_id.txt", $point);
  }
  else { 
   file_put_contents("$shapedir/$shape_id/$shape_id.txt", " $point", FILE_APPEND);
  }
  file_put_contents("$shapedir/$shape_id/$point_id.txt", $row);
  debug($shape_id);
 }
 if (!feof($rh)) {
  trigger_error('Error reading shapes.txt', E_USER_WARNING);
 }
 fclose($rh);

 debug("*** Split stops ***");
 if (!is_dir($stopdir)) {
  mkdir($stopdir);
 }
 @mkdir("$stopdir/area");
 $rh = fopen("$fromdir/stops.txt", r);
 while (($row = stream_get_line($rh, READ_BUFFER_LENGTH, "\n")) !== false) {
  $data = explode(',', $row);
  $stop_id = $data[0];
  $lat = sprintf("%.02f", floor($data[4]*20)/20);
  $lng = sprintf("%.02f", floor($data[5]*20)/20);
  @mkdir("$stopdir/$stop_id");
  file_put_contents("$stopdir/$stop_id/$stop_id.txt", $row);
  file_put_contents("$stopdir/area/$lat-$lng.txt", "$row\n", FILE_APPEND);
  debug($stop_id);
 }
 if (!feof($rh)) {
  trigger_error('Error reading stops.txt', E_USER_WARNING);
 }
 fclose($rh);

 foreach (glob("*-*.txt") as $areatile) {
  debug($areatile);
  exec("sort $areatile | uniq > $areatile");
 }

 debug("*** Split stop times***");
 $rh = fopen("$fromdir/stop_times.txt", r);
 while (($row = stream_get_line($rh, READ_BUFFER_LENGTH, "\n")) !== false) {
  $data = explode(',', $row);
  $trip_id = $data[0];
  $departure = $data[2]." $trip_id\n";
  $stop_id = $data[3];
  $tmptripdir = "$tmpdir/trip/$trip_id";
  @mkdir("$tmpdir/trip");
  @mkdir($tmptripdir);
  file_put_contents("$tmptripdir/$trip_id.txt", $row, FILE_APPEND);
  file_put_contents("$stopdir/$stop_id/departures.txt", $departure, FILE_APPEND);
  debug($trip_id);
 }
 if (!feof($rh)) {
  trigger_error('Error reading stop_times.txt', E_USER_WARNING);
 }
 fclose($rh);

 debug("*** Split trips ***");
 $rh = fopen("$fromdir/trips.txt", r);
 while (($row = stream_get_line($rh, READ_BUFFER_LENGTH, "\n")) !== false) {
  $data = explode(',', $row);
  $route_id = $data[0];
  $service_id = $data[1];
  $trip_id = $data[2];
  $shape_id = $data[5];
  $tripdir = "$routedir/$route_id/$service_id/$trip_id";
  @mkdir("$routedir/$route_id");
  @mkdir("$routedir/$route_id/$service_id");
  @mkdir($tripdir);
  @copy("$shapedir/$shape_id/$shape_id.txt", "$tripdir/shape.txt");
  @mkdir("$tripdir/time");
  @rename("$tmpdir/trip/$trip_id", "$tripdir/time");
  @rename("$tmpdir/service/$service_id", "$routedir/$route_id/$service_id/date");
  file_put_contents("$tripdir/$trip_id.txt", $row);
  debug($trip_id);
 }
 if (!feof($rh)) {
  trigger_error('Error reading trips.txt', E_USER_WARNING);
 }
 fclose($rh);

 debug("*** All done! ***");

 function debug($msg) {
   if (DEBUG) {
     print "$msg\n";
   }
 }

?>
