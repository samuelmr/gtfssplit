<?php
 $fromdir = './data/hsl';
 $todir = './tiles/hsl';

 $routedir = "$todir/route";
 $servicedir = "$todir/service";
 $shapedir = "$todir/shape";
 $stopdir = "$todir/stop";
 $tmpdir = "$todir/tmp";

 // step 1 area files, e.g. 60.0-25-0.txt, 60.5-25.0.txt
 // will be rounded to 1 digit precision ("%.01f")
 define('LAT_STEP1', 0.5);
 define('LNG_STEP1', 0.5);
 // step 1 area files, e.g. 60.00-25-00.txt, 60.05-25.00.txt
 // will be rounded to 2 digit precision ("%.02f")
 define('LAT_STEP2', 0.05);
 define('LNG_STEP2', 0.05);
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
  $data = str_getcsv($row);
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
  $data = str_getcsv($row);
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
  $data = str_getcsv($row);
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
 $prev = '';
 $prevprev = '';
 while (($row = stream_get_line($rh, READ_BUFFER_LENGTH, "\n")) !== false) {
  $data = str_getcsv($row);
  $shape_id = $data[0];
  $point_id = $shape_id.'-'.$data[3];
  $point = $data[1].','.$data[2];
  if (!is_dir("$shapedir/$shape_id")) {
   mkdir("$shapedir/$shape_id");
  }
  if ($data[3] == 1) {
   file_put_contents("$shapedir/$shape_id/$shape_id.txt", $point);
   $prev = '';
   $prevprev = '';
  }
  else { 
   file_put_contents("$shapedir/$shape_id/$shape_id.txt", " $point", FILE_APPEND);
  }
  file_put_contents("$shapedir/$shape_id/$point_id.txt", $row);
  debug("$shape_id");
  if ($prev && $prevprev) {
   file_put_contents("$tmpdir/${prev}_prev.txt", "$prevprev\n", FILE_APPEND);
  }
  if ($prev) {
   file_put_contents("$tmpdir/${prev}_next.txt", "$point\n", FILE_APPEND);
  }
  $prevprev = $prev;
  $prev = $point;
 }
 if ($prev && $prevprev) {
  file_put_contents("$tmpdir/${prev}_prev.txt", "$prevprev\n", FILE_APPEND);
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
  $data = str_getcsv($row);
  $stop_id = $data[0];
  @mkdir("$stopdir/$stop_id");
  $avgprevlat = $data[4];
  $avgprevlng = $data[5];
  $avgnextlat = $data[4];
  $avgnextlng = $data[5];
  if (is_numeric($data[4]) && is_numeric($data[5])) {
   if (is_file("$tmpdir/$data[4],$data[5]_prev.txt")) {
    $prevstops = file("$tmpdir/$data[4],$data[5]_prev.txt");
    $prevlats = array();
    $prevlngs = array();
    foreach($prevstops as $point) {
      list($lat, $lng) = explode(',', $point);
      $prevlats[] = $lat;
      $prevlngs[] = $lng;
    }
    if ((count($prevlats) > 0) && (count($prevlngs) > 0)) {
     $avgprevlat = array_sum($prevlats)/count($prevlats);
     $avgprevlng = array_sum($prevlngs)/count($prevlngs);
    }
   }
   if (is_file("$tmpdir/$data[4],$data[5]_next.txt")) {
    $nextstops = file("$tmpdir/$data[4],$data[5]_next.txt");
    $nextlats = array();
    $nextlngs = array();
    foreach($nextstops as $point) {
      list($lat, $lng) = explode(',', $point);
      $nextlats[] = $lat;
      $nextlngs[] = $lng;
    }
    if ((count($nextlats) > 0) && (count($nextlngs) > 0)) {
     $avgnextlat = array_sum($nextlats)/count($nextlats);
     $avgnextlng = array_sum($nextlngs)/count($nextlngs);
    }
   }
   $deg = '';
   if ($avgprevlat && $avgprevlng && $avgnextlat && $avgnextlng) {
    $latdiff = ($avgnextlat - $avgprevlat);
    $lngdiff = ($avgnextlng - $avgprevlng);
    if (($latdiff == 0) && ($lngdiff != 0)) {
      $deg = ($avgnextlng > $avgprevlng) ? 0 : 180;
    }
    elseif ($lngdiff == 0) {
      $deg = ($avgnextlat > $avgprevlat) ? 90 : 270;
    }
    else {
     $deg = round(rad2deg(atan($latdiff/$lngdiff))) - 90;
     if (($latdiff < 0) && ($lngdiff < 0)) {
       $deg += 180;
     }
     if ($deg < 0) {
       $deg += 360;
     }
    }
   }
   $row .= ",$deg";
   $lat1 = sprintf("%.01f", floor($data[4]*(1/LAT_STEP1))/(1/LAT_STEP1));
   $lng1 = sprintf("%.01f", floor($data[5]*(1/LNG_STEP1))/(1/LNG_STEP1));
   $lat2 = sprintf("%.02f", floor($data[4]*(1/LAT_STEP2))/(1/LAT_STEP2));
   $lng2 = sprintf("%.02f", floor($data[5]*(1/LNG_STEP2))/(1/LNG_STEP2));
   file_put_contents("$stopdir/area/$lat1-$lng1.txt", "$row\n", FILE_APPEND);
   file_put_contents("$stopdir/area/$lat2-$lng2.txt", "$row\n", FILE_APPEND);
   file_put_contents("$stopdir/all.txt", "$row\n", FILE_APPEND);
   file_put_contents("$stopdir/$stop_id/$stop_id.txt", $row);
  }
  debug("$stop_id");
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
  $data = str_getcsv($row);
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
  $data = str_getcsv($row);
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
