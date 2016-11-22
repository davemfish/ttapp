<?php

///
/// version 2:  switch to php and incorporate fxns to upload files and batch the r code (SAW)
///  20141030
///
/// markercluster pie charts borrow heavily from http://bl.ocks.org/gisminister/10001728
/// 
/// TODO: somewhere this code should probably cleanup old sessions
/// 


 //
 // TOP STUFF
 //

// set time zone
date_default_timezone_set('America/Los_Angeles');

// Header
echo "
<html>
  <head>
    <meta charset=\"UTF-8\">

    <!-- Latest compiled and minified CSS -->
    <link rel=\"stylesheet\" href=\"https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap.min.css\">

    <!-- Optional theme -->
    <link rel=\"stylesheet\" href=\"https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap-theme.min.css\">

    <!-- Latest compiled and minified JavaScript -->
    <script src=\"https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/js/bootstrap.min.js\"></script>

    <!-- Bootstrap file for file input forms -->
    <script type=\"text/javascript\" src=\"./libs/bootstrap-filestyle.min.js\"> </script>

    <script src=\"https://www.google.com/jsapi\"></script>

  </head>
  <body> "
  ;

// <link rel=\"stylesheet\" href=\"https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap.min.css\">

  // these pages down, linking to local versions
    
    // <link rel=\"stylesheet\" type=\"text/css\" href=\"https://cdn.rawgit.com/Leaflet/Leaflet.markercluster/v0.4.0/dist/MarkerCluster.Default.css\">
    // <link rel=\"stylesheet\" type=\"text/css\" href=\"https://cdn.rawgit.com/Leaflet/Leaflet.markercluster/v0.4.0/dist/MarkerCluster.css\">
    // <script src=\"https://cdn.rawgit.com/Leaflet/Leaflet.markercluster/v0.4.0/dist/leaflet.markercluster.js\"></script>



 //
 // MESSAGE BAR
 //


 //
 // MAP SCREEN
 //

 echo "
<div class=\"container\" style=\"margin-bottom: 18px; margin-top: 12px;\">
  <div role=\"tabpanel\" id=\"content\"> 

  <h3>Sorry, the Recreation Dashboard is permanently offline</h3>
  <p>The Dashboard is not compatible with the latest version of the InVEST Recreation model</p>
  <br>
  <p>If you are using InvEST version 3.1.x or earlier, you are encouraged to download the latest version</p>
  <p><a href=\"http://naturalcapitalproject.org/invest\">http://naturalcapitalproject.org/invest</a></p>
  <br>
  <h4>Dashboards for Coastal Vulnerability and Habitat Risk Assessment models are still available:</h4>
  <p><a href=\"http://vulpes.sefs.uw.edu/ttapp/cv-dash.php\">Coastal Vulnerability</a></p>
  <p><a href=\"http://vulpes.sefs.uw.edu/ttapp/hra-dash.php\">Habitat Risk Assessment</a></p>
  <br>
  <h4>Feedback on any of the Dashboards is always appreciated:</h4>
  <p><a href=\"https://github.com/davemfish/ttapp/issues\">https://github.com/davemfish/ttapp/issues</a></p>
  <p><a href=\"http://forums.naturalcapitalproject.org/index.php?p=/categories/experimental-software-tools\">http://forums.naturalcapitalproject.org/index.php?p=/categories/experimental-software-tools</a></p>
  </div>
  </div>
  ";


// <?php

 //
 // BOTTOM BUSINESS
 //

require_once("footer.php");

echo "
  </body>
</html> ";

?>
