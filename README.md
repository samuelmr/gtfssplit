gtfssplit
=========

Split GTFS files to smaller chunks


Create a new folder for each agency. (E.g. ./data/hsl for agency HSL.)

Download the agency's GTFS.zip file and unzip it into the agency's folder.
You should get:

	data/hsl/agency.txt
	data/hsl/calendar.txt
	data/hsl/calendar_dates.txt
	data/hsl/routes.txt
	data/hsl/shapes.txt
	data/hsl/stop_times.txt
	data/hsl/stops.txt
	data/hsl/trips.txt

Update splitgtfs.php to use this agency's files:

	$fromdir = './data/hsl';
	$todir = './tiles/hsl';

Run gtfssplit.php and you should get (after many hours) a directory structure:

	tiles/<agency>/route	- directory for all routes
	 <route_id>				- directory for a single route
	  <service_id>			- directory for a service of route
	   date					- directory for service date info
	    <service_id>.txt	- in which days of week this service (usually) is active
	    exceptions.txt		- exception dates and types, one per line
	   <trip_id>			- a trip of this service (with a starting time)
	    <trip_id>.txt		- trip destination and shape_id
	    time				- directory for service time info
	     <trip_id>.txt		- arrival and departure times etc.
	tiles/<agency>/shape	- directory for paths
	 <shape_id>				- directory for a single shape
	  <shape_id>.txt		- all points along the path (separated with spaces)
	  <shape_id>-<num>.txt	- <num>th point on the path
	tiles/<agency>/stop		- directory for all stops
	 <stop_id>				- directory for a single stop
	  <stop_id>.txt			- stop name, location, description
	  departures.txt		- all scheduled departures from this stop, one per line
	 area					- directory for area "tiles"
	  <lat>-<lng>.txt		- stops in an area ("tile")

There are two sets of area tiles. One set with 1 decimal precision and another set with
2 decimal precision.

The coordinates &lt;lat&gt; and &lt;lng&gt; define the tile's south and west coordinates
(the lower left corner). The north and east coordinates (the upper right corner) are
by default 0.5 or 0.05 decimal degrees greater. Examples:

1 decimal precision:
	60.0-25.0.txt			- latitude from 60.0 to 60.5, longitude from 25.0 - 25.5
	60.5-25.0.txt			- latitude from 60.5 to 61.0, longitude from 25.0 - 25.5
	61.0-25.0.txt			- latitude from 61.0 to 61.5, longitude from 25.0 - 25.5
	...
	60.0-25.5.txt			- latitude from 60.0 to 60.5, longitude from 25.5 - 26.0
	60.0-26.0.txt			- latitude from 60.0 to 60.5, longitude from 26.0 - 26.5
	60.0-26.5.txt			- latitude from 60.0 to 60.5, longitude from 26.5 - 27.0
	...

2 decimal precision:
	60.00-25.00.txt			- latitude from 60.00 to 60.05, longitude from 25.00 - 25.05
	60.05-25.00.txt			- latitude from 60.05 to 60.10, longitude from 25.00 - 25.05
	60.10-25.00.txt			- latitude from 60.10 to 60.15, longitude from 25.00 - 25.05
	...
	60.00-25.05.txt			- latitude from 60.00 to 60.05, longitude from 25.05 - 25.10
	60.00-25.10.txt			- latitude from 60.00 to 60.05, longitude from 25.10 - 25.15
	60.00-25.15.txt			- latitude from 60.00 to 60.05, longitude from 25.15 - 25.20
	...

The steps (0.05) can be configured by editing gtfssplit.php
	// step 1 area files, e.g. 60.0-25-0.txt, 60.5-25.0.txt
	// will be rounded to 1 digit precision ("%.01f")
	define('LAT_STEP1', 0.5);
	define('LNG_STEP1', 0.5);
	// step 1 area files, e.g. 60.00-25-00.txt, 60.05-25.00.txt
	// will be rounded to 2 digit precision ("%.02f")
	define('LAT_STEP2', 0.05);
	define('LNG_STEP2', 0.05);

If you set LAT_STEP1 and LNG_STEP1 to 0.1 and LAT_STEP2 and LNG_STEP2 to 0.01, you get:

	60.0-25.0.txt, 60.1-25.0.txt, 60.2-25.0.txt, ..., 60.0-25.1.txt, ...
	60.00-25.00.txt, 60.01-25.00.txt, 60.02-25.00.txt, ..., 60.00-25.01.txt, ...
