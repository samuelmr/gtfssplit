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

The coordinates <lat> and <lng> define the tile's south and west coordinates (the
lower left corner). The north and east coordinates (the upper right corner) are
0.05 decimal degrees greater. Examples:

	60.00-25.00.txt			- latitude from 60.00 to 60.05, longitude from 25.00 - 25.05
	60.05-25.00.txt			- latitude from 60.05 to 60.10, longitude from 25.00 - 25.05
	60.10-25.00.txt			- latitude from 60.10 to 60.15, longitude from 25.00 - 25.05
	...
	60.00-25.05.txt			- latitude from 60.00 to 60.05, longitude from 25.05 - 25.10
	60.00-25.10.txt			- latitude from 60.00 to 60.05, longitude from 25.10 - 25.15
	60.00-25.15.txt			- latitude from 60.00 to 60.05, longitude from 25.15 - 25.20
	...

