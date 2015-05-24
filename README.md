Geo information helper functions
=======

Basis: Wordpress database

_process_queue.php:
Process submission queue. Get Street View image. Resize to create thumbnails.

_process_locations.php:
Use geocode API to extract location information.

_process_coordinates.php:
Extract coordinates (latitude, longitude) from street view or bing url (or other input).

scan_locations.php:
Get location information (city, country, region, street, ...) from a set of coordinates.

duplicate-finder.php:
Find and display duplicates. Allow merging of wordpress posts by drag and drop.

extract_date.php:
Get the date of a Street View picture. This file will run for as long as there are unprocessed pictures in the database.
Implemented as a redirect loop. Output is given in the console.
