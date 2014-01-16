gtfssplit
=========

Split GTFS files to smaller chunks.

Usage
-----

Create at least a 6 GB (6,000 * 2,048 = 12,288,000 blocks) RAM disk for
output. On OS X, run the following command in Terminal:

    diskutil erasevolume HFS+ 'RAMdisk' `hdiutil attach -nomount ram://12288000`
 
Create a new directory for each agency. (E.g. ./gtfs-input/HSL for
agency HSL.)

Download the agency's GTFS.zip file and unzip it into the agency's
directory. You should get:

	./gtfs-input/HSL/agency.txt
	./gtfs-input/HSL/calendar.txt
	./gtfs-input/HSL/calendar_dates.txt
	./gtfs-input/HSL/routes.txt
	./gtfs-input/HSL/shapes.txt
	./gtfs-input/HSL/stop_times.txt
	./gtfs-input/HSL/stops.txt
	./gtfs-input/HSL/trips.txt

Run gtfssplit.php to use this agency's files (note that the path to
your RAMdisk may differ depending on your operating system):

    php gtfssplit.php --in ./gtfs-input/HSL --out /Volumes/RAMdisk/gtfs-output/HSL

Results
-------

After some time, you should get a directory structure into
`RAMdisk/gtfs-output/<agency>`:

	index.txt				- listing of most of the files
	route/					- directory for all routes
	  <route_id>/			- directory for a single route
	    <service_id>/		- directory for a service of route
	      <trip_id>/		- a trip of this service (with a starting time)
	        <trip_id>.txt	- trip destination and shape_id
	        shape.txt		- coordinates of this service's path
	        stops.txt		- arrival and departure times etc.
	      dates.txt			- dates when this service (usually) operates
          exceptions.txt	- exceptions: when this service doesn't operate
	    <route_id>.txt		- route name, destination, URL etc.
	shape/					- directory for paths
	  <shape_id>			- directory for a single shape
	    <shape_id>.txt		- all points along the path
	stop					- directory for all stops
	  <stop_id>				- directory for a single stop
	    <stop_id>.txt		- stop name, location, description, "direction"
	    departures.txt		- all departures from this stop, one per line
	  all.txt				- information about all stops in a single file
	  area					- directory for area "tiles"
	    <lat>-<lng>.txt		- stops in an area ("tile")
	tmp						- you can safely delete this directory

Stop descriptions are the same as the rows in the stops.txt file,
except that the stop's "heading" is added to the description. Heading
is the angle (in degrees) in which vehicles pass the stop on average.
0 degrees is from south to north, 90 degrees is from west to east etc.

There are two sets of area tiles. One set with 1 decimal precision and
another set with 2 decimal precision.

The coordinates &lt;lat&gt; and &lt;lng&gt; define the tile's south and
west coordinates (the lower left corner). The north and east
coordinates (the upper right corner) are by default 0.5 or 0.05 decimal
degrees greater. Examples:

1 decimal precision:

	60.0-25.0.txt		- latitude from 60.0 to 60.5, longitude from 25.0 - 25.5
	60.5-25.0.txt		- latitude from 60.5 to 61.0, longitude from 25.0 - 25.5
	61.0-25.0.txt		- latitude from 61.0 to 61.5, longitude from 25.0 - 25.5
	...
	60.0-25.5.txt		- latitude from 60.0 to 60.5, longitude from 25.5 - 26.0
	60.0-26.0.txt		- latitude from 60.0 to 60.5, longitude from 26.0 - 26.5
	60.0-26.5.txt		- latitude from 60.0 to 60.5, longitude from 26.5 - 27.0
	...

2 decimal precision:

	60.00-25.00.txt		- latitude from 60.00 to 60.05, longitude from 25.00 - 25.05
	60.05-25.00.txt		- latitude from 60.05 to 60.10, longitude from 25.00 - 25.05
	60.10-25.00.txt		- latitude from 60.10 to 60.15, longitude from 25.00 - 25.05
	...
	60.00-25.05.txt		- latitude from 60.00 to 60.05, longitude from 25.05 - 25.10
	60.00-25.10.txt		- latitude from 60.00 to 60.05, longitude from 25.10 - 25.15
	60.00-25.15.txt		- latitude from 60.00 to 60.05, longitude from 25.15 - 25.20
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

If you set LAT_STEP1 and LNG_STEP1 to 0.1 and LAT_STEP2 and LNG_STEP2
to 0.01, you get:

	60.0-25.0.txt, 60.1-25.0.txt, 60.2-25.0.txt, ..., 60.0-25.1.txt, ...
	60.00-25.00.txt, 60.01-25.00.txt, 60.02-25.00.txt, ..., 60.00-25.01.txt, ...
