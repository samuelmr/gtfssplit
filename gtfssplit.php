<?php

 $starttime = microtime(true);
 $prevtmp = $starttime;
 
 // step 1 area files, e.g. 60.0-25-0.txt, 60.5-25.0.txt
 // will be rounded to 1 digit precision ("%.01f")
 define('LAT_STEP1', 0.5);
 define('LNG_STEP1', 0.5);
 // step 1 area files, e.g. 60.00-25-00.txt, 60.05-25.00.txt
 // will be rounded to 2 digit precision ("%.02f")
 define('LAT_STEP2', 0.05);
 define('LNG_STEP2', 0.05);
 // DEBUG levels: 1: a few messages per input file
 //               2: a few messages per output file
 define('DEBUG', 1);
 define('READ_BUFFER_LENGTH', 4096);

 $opts = array("in:", "out:");
 $options = getopt('', $opts);
 
 if (!$options['in'] || !$options['out']) {
  echo "Usage: php ".basename(__FILE__).
       " --in <inputdir> --out <outputdir>\n";
  exit; 
 }
 
 $fromdir = $options['in'];
 $todir = $options['out'];

 $routerel = "route";
 $servicerel = "service";
 $shaperel = "shape";
 $stoprel = "stop";
 $tmprel = "tmp";

 $routedir = "$todir/$routerel";
 $servicedir = "$todir/$servicerel";
 $shapedir = "$todir/$shaperel";
 $stopdir = "$todir/$stoprel";
 $tmpdir = "$todir/$tmprel";

 $indexfile = "$todir/index.txt";
 // http://dataprotocols.org/simple-data-format/
 $datapackage = "$todir/datapackage.json";
 $resources = array();
 
 // you can turn off parsing of individual files for testing purposes
 $parse_calendar = TRUE;
 $parse_calendar_dates = TRUE;
 $parse_routes = TRUE;
 $parse_shapes = TRUE;
 $parse_stops = TRUE;
 $parse_stop_times = TRUE;
 $parse_trips = TRUE;
 $update_package = FALSE;

 // https://developers.google.com/transit/gtfs/reference
 // all other field types are strings
 $gtfs_types = array('stop_lat' => 'number',
                     'stop_lon' => 'number',
                     'route_type' => 'integer',
                     'direction_id' => 'boolean',
                     'wheelchair_accessible' => 'integer',
                     # 'arrival_time' => 'time', // not real time, 25:55:00 allowed
                     # 'departure_time' => 'time',
                     'stop_sequence' => 'integer',
                     'pickup_type' => 'integer',
                     'drop_off_type' => 'integer',
                     'shape_dist_traveled' => 'number',
                     'monday' => 'boolean',
                     'tuesday' => 'boolean',
                     'wednesday' => 'boolean',
                     'thursday' => 'boolean',
                     'friday' => 'boolean',
                     'saturday' => 'boolean',
                     'sunday' => 'boolean',
                     'start_date' => 'date',
                     'end_date' => 'date',
                     'date' => 'date',
                     'shape_pt_lat' => 'number',
                     'shape_pt_lon' => 'number',
                     'shape_pt_sequence' => 'integer',
                     // custom attribute: to which direction vehicles pass a stop
                     'direction' => 'integer');                 

 if (!is_dir(dirname($todir))) {
  mkdir(dirname($todir));
 }
 if (!is_dir($todir)) {
  mkdir($todir);
 }
 if (!is_dir($tmpdir)) {
  mkdir($tmpdir);
 }
 $openfiles = array();
 $areafiles = array();
 $routenames = array();
 
 // empty
 file_put_contents($indexfile, "");
 
 if ($parse_calendar) {
  debug("*** Split calendar ***", 1);
  $rh = fopen("$fromdir/calendar.txt", r);
  $header = stream_get_line($rh, READ_BUFFER_LENGTH, "\n");
  $keys = str_getcsv($header);
  $id_key = array_search('service_id', $keys); // always 0 - is it?
  while (($row = stream_get_line($rh, READ_BUFFER_LENGTH, "\n")) !== false) {
   $data = str_getcsv($row);
   $service_id = $data[$id_key];
   $tmpservicedir = "$tmpdir/service/$service_id";
   @mkdir("$tmpdir/service");
   @mkdir($tmpservicedir);
   $openfile = "$tmpservicedir/$service_id.txt";
   if (!$openfiles[$openfile]) {
     file_put_contents($openfile, "$header\n");
     $openfiles[$openfile] = 1;
   }
   file_put_contents($openfile, "$row\n", FILE_APPEND);
   debug("Calendar: ".$service_id, 2);
  }
  if (!feof($rh)) {
   trigger_error('Error reading calendar.txt', E_USER_WARNING);
  }
  fclose($rh);
  $openfiles = array();
  $tmptime = microtime(true);
  debug("Calendar parsed in ".gmdate("H \h, i \m, s \s", $tmptime - $prevtmp), 1);
 }
 
 if ($parse_calendar_dates) {
  debug("*** Split calendar dates ***", 1);
  $rh = fopen("$fromdir/calendar_dates.txt", r);
  $header = stream_get_line($rh, READ_BUFFER_LENGTH, "\n");
  $keys = str_getcsv($header);
  $id_key = array_search('service_id', $keys); // always 0 - is it?
  while (($row = stream_get_line($rh, READ_BUFFER_LENGTH, "\n")) !== false) {
   $data = str_getcsv($row);
   $service_id = $data[$id_key];
   $tmpservicedir = "$tmpdir/service/$service_id";
   @mkdir("$tmpdir/service");
   @mkdir($tmpservicedir);
   $openfile = "$tmpservicedir/exceptions.txt";
   if (!$openfiles[$openfile]) {
     file_put_contents($openfile, "$header\n");
     $openfiles[$openfile] = 1;
   }
   file_put_contents($openfile, "$row\n", FILE_APPEND);
   # file_put_contents($openfile, "$data[1] $data[2]\n", FILE_APPEND);
   debug("Calendar dates: ".$service_id, 2);
  }
  if (!feof($rh)) {
   trigger_error('Error reading calendar_dates.txt', E_USER_WARNING);
  }
  fclose($rh);
  $openfiles = array();
  $prevtmp = $tmptime;
  $tmptime = microtime(true);
  debug("Calendar dates parsed in ".gmdate("H \h, i \m, s \s", $tmptime - $prevtmp), 1);
 }
 
 if ($parse_routes) {
  debug("*** Split routes ***", 1);
  if (!is_dir($routedir)) {
   mkdir($routedir);
  }
  $rh = fopen("$fromdir/routes.txt", r);
  $header = stream_get_line($rh, READ_BUFFER_LENGTH, "\n");
  $keys = str_getcsv($header);
  $id_key = array_search('route_id', $keys); // always 0 - is it?
  while (($row = stream_get_line($rh, READ_BUFFER_LENGTH, "\n")) !== false) {
   $data = str_getcsv($row);
   $route_id = $data[$id_key];
   $routenames[$route_id] = $data['route_short_name'];
   @mkdir("$routedir/$route_id");
   file_put_contents("$routedir/$route_id/$route_id.txt", "$header\n$row");
   file_put_contents($indexfile, "$routerel/$route_id/$route_id.txt\n", FILE_APPEND);
   if ($update_package) {
    add_resource("$routedir/$route_id/$route_id.txt", $keys);
   }
   debug("Route: ".$route_id, 2);
  }
  if (!feof($rh)) {
   trigger_error('Error reading routes.txt', E_USER_WARNING);
  }
  fclose($rh);
  $prevtmp = $tmptime;
  $tmptime = microtime(true);
  debug("Routes parsed in ".gmdate("H \h, i \m, s \s", $tmptime - $prevtmp), 1);
 }

 if ($parse_shapes) {
  debug("*** Split shapes ***", 1);
  if (!is_dir($shapedir)) {
   mkdir($shapedir);
  }
  $rh = fopen("$fromdir/shapes.txt", r);
  $prev = '';
  $prevprev = '';
  $header = stream_get_line($rh, READ_BUFFER_LENGTH, "\n");
  $keys = str_getcsv($header);
  $id_key = array_search('shape_id', $keys); // always 0 - is it?
  $lat_key = array_search('shape_pt_lat', $keys);
  $lon_key = array_search('shape_pt_lon', $keys);
  $seq_key = array_search('shape_pt_sequence', $keys);
  $shapehead = 'shape_pt_lat,shape_pt_lon';
  while (($row = stream_get_line($rh, READ_BUFFER_LENGTH, "\n")) !== false) {
   $data = str_getcsv($row);
   $shape_id = $data[$id_key];
   $point_id = $shape_id.'-'.$data[$seq_key];
   $point = $data[$lat_key].','.$data[$lon_key];
   if (!is_dir("$shapedir/$shape_id")) {
    mkdir("$shapedir/$shape_id");
   }
   if ($data[3] == 1) {
    file_put_contents("$shapedir/$shape_id/$shape_id.txt", "$shapehead\n$point\n");
    file_put_contents($indexfile, "$shaperel/$shape_id/$shape_id.txt\n", FILE_APPEND);
    if ($update_package) {
     add_resource("$shapedir/$shape_id/$shape_id.txt", str_getcsv($shapehead));
    } 
    debug("Shape: ".$shape_id, 2);
    $prev = '';
    $prevprev = '';
   }
   else { 
    file_put_contents("$shapedir/$shape_id/$shape_id.txt", "$point\n", FILE_APPEND);
   }
/*
   // is it necessary at all to save individual points into separate files?
   file_put_contents("$shapedir/$shape_id/$point_id.txt", "$header\n$row");
   file_put_contents($indexfile, "$shaperel/$shape_id/$point_id.txt\n", FILE_APPEND);
   if ($update_package) {
    add_resource("$shapedir/$shape_id/$point_id.txt", $keys);
   } 
   debug("$shape_id/$point_id", 2);
*/
   if ($prev) {
    list($plat, $plon) = explode(',', $prev);
    @mkdir("$tmpdir/$plat");
    if ($prevprev) {
     file_put_contents("$tmpdir/$plat/${plon}_prev.txt", "$prevprev\n", FILE_APPEND);
    }
    file_put_contents("$tmpdir/$plat/${plon}_next.txt", "$point\n", FILE_APPEND);
   }
   $prevprev = $prev;
   $prev = $point;
   if ($prev && $prevprev) {
    list($plat, $plon) = explode(',', $prev);
    @mkdir("$tmpdir/$plat");
    file_put_contents("$tmpdir/$plat/${plon}_prev.txt", "$prevprev\n", FILE_APPEND);
   }
  }
  if (!feof($rh)) {
   trigger_error('Error reading shapes.txt', E_USER_WARNING);
  }
  fclose($rh);
  $prevtmp = $tmptime;
  $tmptime = microtime(true);
  debug("Shapes parsed in ".gmdate("H \h, i \m, s \s", $tmptime - $prevtmp), 1);
 }
 
 if ($parse_stops) {
  debug("*** Split stops ***", 1);
  if (!is_dir($stopdir)) {
   mkdir($stopdir);
  }
  @mkdir("$stopdir/area");
  $rh = fopen("$fromdir/stops.txt", r);
  $header = stream_get_line($rh, READ_BUFFER_LENGTH, "\n");
  $header .= ',direction';
  $keys = str_getcsv($header);
  $id_key = array_search('stop_id', $keys); // always 0 - is it?
  $lat_key = array_search('stop_lat', $keys);
  $lon_key = array_search('stop_lon', $keys);
  file_put_contents("$stopdir/all.txt", "$header\n");
  file_put_contents($indexfile, "$stoprel/all.txt\n", FILE_APPEND);
  if ($update_package) {
   add_resource("$stopdir/all.txt", $keys);
  }
  while (($row = stream_get_line($rh, READ_BUFFER_LENGTH, "\n")) !== false) {
   $data = str_getcsv($row);
   $stop_id = $data[0];
   @mkdir("$stopdir/$stop_id");
   $avgprevlat = merc_y($data[$lat_key]);
   $avgprevlng = merc_x($data[$lon_key]);
   $avgnextlat = merc_y($data[$lat_key]);
   $avgnextlng = merc_x($data[$lon_key]);
   if (is_numeric($data[$lat_key]) && is_numeric($data[$lon_key])) {
    if (is_file("$tmpdir/$data[$lat_key]/$data[$lon_key]_prev.txt")) {
     $prevstops = file("$tmpdir/$data[$lat_key]/$data[$lon_key]_prev.txt");
     $prevlats = array();
     $prevlngs = array();
     foreach($prevstops as $point) {
      list($lat, $lng) = explode(',', $point);
      $prevlats[] = merc_y($lat);
      $prevlngs[] = merc_x($lng);
     }
     if ((count($prevlats) > 0) && (count($prevlngs) > 0)) {
      $avgprevlat = array_sum($prevlats)/count($prevlats);
      $avgprevlng = array_sum($prevlngs)/count($prevlngs);
     }
    }
    if (is_file("$tmpdir/$data[$lat_key]/$data[$lon_key]_next.txt")) {
     $nextstops = file("$tmpdir/$data[$lat_key]/$data[$lon_key]_next.txt");
     $nextlats = array();
     $nextlngs = array();
     foreach($nextstops as $point) {
      list($lat, $lng) = explode(',', $point);      
      $nextlats[] = merc_y($lat);
      $nextlngs[] = merc_x($lng);
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
     if (($latdiff == 0) && ($lngdiff == 0)) {
      $deg = 0;
     }
     elseif (($latdiff == 0) && ($lngdiff != 0)) {
      $deg = ($avgnextlng > $avgprevlng) ? 0 : 180;
     }
     elseif ($lngdiff == 0) {
      $deg = ($avgnextlat > $avgprevlat) ? 90 : 270;
     }
     else {
      # $deg = round(rad2deg(atan($latdiff/$lngdiff))) - 90;
      $y = sin($lngdiff) * cos($avgnextlat);
      $x = cos($avgprevlat) * sin($avgnextlat) -
           sin($avgprevlat) * cos($avgnextlat) * cos($longdiff);
      $deg = round(rad2deg(atan2($y, $x)));
      if (($latdiff < 0) && ($lngdiff < 0)) {
       $deg += 180;
      }
      if ($deg < 0) {
       $deg += 360;
      }
     }
    }
    $row .= ",$deg";
    $lat1 = sprintf("%.01f", floor($data[$lat_key]*(1/LAT_STEP1))/(1/LAT_STEP1));
    $lng1 = sprintf("%.01f", floor($data[$lon_key]*(1/LNG_STEP1))/(1/LNG_STEP1));
    $lat2 = sprintf("%.02f", floor($data[$lat_key]*(1/LAT_STEP2))/(1/LAT_STEP2));
    $lng2 = sprintf("%.02f", floor($data[$lon_key]*(1/LNG_STEP2))/(1/LNG_STEP2));
    $areafile1 = "$stopdir/area/$lat1-$lng1.txt";
    if (!$areafiles[$areafile1]) {
     file_put_contents($areafile1, "$header\n");
     $areafiles[$areafile1] = 1;
     file_put_contents($indexfile, "$stoprel/area/$lat1-$lng1.txt\n", FILE_APPEND);
     if ($update_package) {
      add_resource($areafile1, $keys);
     }
    }
    file_put_contents($areafile1, "$row\n", FILE_APPEND);
    $areafile2 = "$stopdir/area/$lat2-$lng2.txt";
    if (!$areafiles[$areafile2]) {
     file_put_contents($areafile2, "$header\n");
     $areafiles[$areafile2] = 1;
     file_put_contents($indexfile, "$stoprel/area/$lat2-$lng2.txt\n", FILE_APPEND);
     if ($update_package) {
      add_resource($areafile2, $keys);
     }
    }
    file_put_contents($areafile2, "$row\n", FILE_APPEND);
    file_put_contents("$stopdir/all.txt", "$row\n", FILE_APPEND);
    file_put_contents("$stopdir/$stop_id/$stop_id.txt", "$header\n$row");
    @file_put_contents($indexfile, "$stoprel/$stop_id/$stop_id.txt\n", FILE_APPEND);
    if ($update_package) {
     add_resource("$stopdir/$stop_id/$stop_id.txt", $keys);
    }
   }
   debug("Stop: ".$stop_id, 2);
  }
  if (!feof($rh)) {
   trigger_error('Error reading stops.txt', E_USER_WARNING);
  }
  fclose($rh);
  foreach ($areafiles as $areafile => $num) {
   debug("Sort area: ".$areafile, 2);
   exec("sort -uk2 $areafile -o $areafile");	
  }
  $prevtmp = $tmptime;
  $tmptime = microtime(true);
  debug("Stops parsed in ".gmdate("H \h, i \m, s \s", $tmptime - $prevtmp), 1);
 }

 if ($parse_stop_times) {
  debug("*** Split stop times***", 1);
  $rh = fopen("$fromdir/stop_times.txt", r);
  $header = stream_get_line($rh, READ_BUFFER_LENGTH, "\n");
  $keys = str_getcsv($header);
  $id_key = array_search('trip_id', $keys); // always 0 - is it?
  $dep_key = array_search('departure_time', $keys);
  $stop_key = array_search('stop_id', $keys);
  $dep_keys = array('departure_time', 'trip_id');
  while (($row = stream_get_line($rh, READ_BUFFER_LENGTH, "\n")) !== false) {
   $data = str_getcsv($row);
   $trip_id = $data[$id_key];
   $departure = $data[$dep_key].",$trip_id\n";
   $stop_id = $data[$stop_key];
   $tmptripdir = "$tmpdir/trip/$trip_id";
   @mkdir("$tmpdir/trip");
   @mkdir($tmptripdir);
   $openfile = "$tmptripdir/$trip_id.txt";
   if (!$openfiles[$openfile]) {
    file_put_contents($openfile, "$header\n");
    $openfiles[$openfile] = 1;
   }
   file_put_contents($openfile, "$row\n", FILE_APPEND);
/*
   if (!is_file("$tmptripdir/stops.txt")) {
    file_put_contents("$tmptripdir/stops.txt", "stop");
   }
   file_put_contents("$tmptripdir/stops.txt", $stop_id, FILE_APPEND);
*/
   if (!is_file("$stopdir/$stop_id/departures.txt")) {
    @file_put_contents("$stopdir/$stop_id/departures.txt", join(',', $dep_keys)."\n");
    @file_put_contents($indexfile, "$stoprel/$stop_id/departures.txt\n", FILE_APPEND);
    if ($update_package) {
     add_resource("$stopdir/$stop_id/departures.txt", $dep_keys);
    }
   }
   @file_put_contents("$stopdir/$stop_id/departures.txt", $departure, FILE_APPEND);
   debug("Stop times: ".$trip_id."/".$stop_id, 2);
  }
  if (!feof($rh)) {
   trigger_error('Error reading stop_times.txt', E_USER_WARNING);
  }
  fclose($rh);
  $openfiles = array(); 
  $prevtmp = $tmptime;
  $tmptime = microtime(true);
  debug("Stop times parsed in ".gmdate("H \h, i \m, s \s", $tmptime - $prevtmp), 1);
 }

 if ($parse_trips) {
  debug("*** Split trips ***", 1);
  $rh = fopen("$fromdir/trips.txt", r);
  $header = stream_get_line($rh, READ_BUFFER_LENGTH, "\n");
  $keys = str_getcsv($header);
  $id_key = array_search('route_id', $keys); // always 0 - is it?
  $service_key = array_search('service_id', $keys);
  $trip_key = array_search('trip_id', $keys);
  $shape_key = array_search('shape_id', $keys);
  $routes_done = array();
  $services_done = array();
  while (($row = stream_get_line($rh, READ_BUFFER_LENGTH, "\n")) !== false) {
   $data = str_getcsv($row);
   $route_id = $data[$id_key];
   $service_id = $data[$service_key];
   $trip_id = $data[$trip_key];
   $shape_id = $data[$shape_key];
   $tripdir = "$routedir/$route_id/$service_id/$trip_id";
   @mkdir("$routedir");
   @mkdir("$routedir/$route_id");
   @mkdir("$routedir/$route_id/$service_id");
   @mkdir($tripdir);
   @copy("$shapedir/$shape_id/$shape_id.txt", "$tripdir/shape.txt");
   # @mkdir("$tripdir/time");
/*
   @rename("$tmpdir/trip/$trip_id/stops.txt", "$tripdir/stops.txt");
*/
   if (!$routes_done[$route_id]) {
    $routeinfo = @file("$routedir/$route_id/$route_id.txt");
    if ($routeinfo) {
     $routeheader = $routeinfo[0];
     $route_keys = str_getcsv($routeheader);
     $routedata = $routeinfo[1];
     $triprows = @file("$tmpdir/trip/$trip_id/$trip_id.txt");
     $tripheader = $triprows[0];
     $trip_keys = str_getcsv($tripheader);
     $stop_key = array_search('stop_id', $trip_keys);
     for ($i=1; $i<count($triprows); $i++) {
      $triprow = str_getcsv($triprows[$i]);
      $stop_id = $triprow[$stop_key];
      @mkdir("$stopdir/$stop_id");
      if ($stop_id && !is_file("$stopdir/$stop_id/routes.txt")) {
       file_put_contents("$stopdir/$stop_id/routes.txt", $routeheader);
       file_put_contents($indexfile, "$stoprel/$stop_id/routes.txt\n", FILE_APPEND);
       if ($update_package) {
        add_resource("$stopdir/$stop_id/routes.txt", $route_keys);
       }
      }
      file_put_contents("$stopdir/$stop_id/routes.txt", "$routedata\n", FILE_APPEND);
     }
     $routes_done[$route_id] = 1;
    }
   }
   if (!$services_done[$service_id]) {
    @rename("$tmpdir/service/$service_id/$service_id.txt",
            "$routedir/$route_id/$service_id/dates.txt");
    file_put_contents($indexfile, "$routerel/$route_id/$service_id/dates.txt\n",
                      FILE_APPEND);
    if ($update_package) {
     add_resource("$routedir/$route_id/$service_id/dates.txt");
    }
    if (is_file("$tmpdir/service/$service_id/exceptions.txt")) {
     rename("$tmpdir/service/$service_id/exceptions.txt",
            "$routedir/$route_id/$service_id/exceptions.txt");
     file_put_contents($indexfile, "$routerel/$route_id/$service_id/exceptions.txt\n",
                       FILE_APPEND);
    }
    if ($update_package) {
     add_resource("$routedir/$route_id/$service_id/exceptions.txt");
    }
    $services_done[$service_id] = 1;
   }
   @rename("$tmpdir/trip/$trip_id/$trip_id.txt", "$tripdir/stops.txt");
   file_put_contents($indexfile, "$triprel/stops.txt\n", FILE_APPEND);
   if ($update_package) {
    add_resource("$tripdir/stops.txt");
   }
   file_put_contents("$tripdir/$trip_id.txt", "$header\n$row"); 
   file_put_contents($indexfile, "$triprel/$trip_id.txt\n", FILE_APPEND);
   if ($update_package) {
    add_resource("$tripdir/$trip_id.txt", $keys);
    }
   $trips_done[$trip_id] = 1;
   debug("Trip: ".$trip_id, 2);
  }
  if (!feof($rh)) {
   trigger_error('Error reading trips.txt', E_USER_WARNING);
  }
  fclose($rh);
  $prevtmp = $tmptime;
  $tmptime = microtime(true);
  debug("Stops parsed in ".gmdate("H \h, i \m, s \s", $tmptime - $prevtmp), 1);
 }

 write_resources();

 exec("sort $indexfile -o $indexfile");	
 debug("*** All done! ***", 1);
 debug("Took ".gmdate("H \h, i \m, s \s", microtime(true) - $starttime), 1);

 function debug($msg, $level) {
  if (DEBUG >= $level) {
   print "$msg\n";
  }
 }

 function add_resource($path, $names=NULL) {
  global $resources;
  global $gtfs_types;
  $fields = array();
  if (!$names) {
   $rh = fopen($path, r);
   $header = stream_get_line($rh, READ_BUFFER_LENGTH, "\n");
   $names = str_getcsv($header);  
  }
  foreach ($names as $name) {
   $type = $gtfs_types[$name] ? $gtfs_types[$name] : "string";
   $arr = array("name" => $name,
                "type" => $type);
   if ($type == 'date') {
    $arr["format"] = "YYYYMMDD";
   }
   elseif ($type == 'time') {
    $arr["format"] = "hh:mm";
   }
   $fields[] = $arr;
  }
  $resources[] = array("path" => $path, "schema" => array("fields" => $fields));
  debug("Added path $path to resources (".count($resources).")", 2);
 }

 function write_resources() {
  if ($update_package) {
   $package = array('name' => "$agency-GTFS", 'resources' => $resources);
   file_put_contents($datapackage, json_encode($package));
  }
 }

 function merc_x($lon) {
  $r_major = 6378137.000;
  return $r_major * deg2rad($lon);
 }
 
 function merc_y($lat) {
  if ($lat > 89.5) $lat = 89.5;
  if ($lat < -89.5) $lat = -89.5;
  $r_major = 6378137.000;
  $r_minor = 6356752.3142;
  $temp = $r_minor / $r_major;
  $es = 1.0 - ($temp * $temp);
  $eccent = sqrt($es);
  $phi = deg2rad($lat);
  $sinphi = sin($phi);
  $con = $eccent * $sinphi;
  $com = 0.5 * $eccent;
  $con = pow((1.0-$con)/(1.0+$con), $com);
  $ts = tan(0.5 * ((M_PI*0.5) - $phi))/$con;
  $y = - $r_major * log($ts);
  return $y;
 }
 
 function merc($x, $y) {
  return array('x'=>merc_x($x), 'y'=>merc_y($y));
 }

?>
