<?php

/// DF 29Jan2015
/// prototype for HRA dashboard, only works with pre-processed sample data (see io-hra.r)
/// still needed is a shell script with some gdal stuff and call to io-hra.r
/// PHP here will run that shell script roughly where the R script call is now. 


/// version 2:  switch to php and incorporate fxns to upload files and batch the r code (SAW)
///  20141030
///

 //
 // TOP STUFF
 //

// set time zone
date_default_timezone_set('America/Los_Angeles');

// Check for IE 
$agent = $_SERVER['HTTP_USER_AGENT'];
if (!preg_match("/Firefox/i", $agent) && !preg_match("/Chrome/i", $agent)) {
  echo "
  <hr>
  <div id=message>
    You must use Mozilla Firefox or Google Chrome to access the page.
    <br>Download <a href=\"http://www.mozilla.com/\">Firefox</a> or <a href=\"http://www.google.com/chrome\">Chrome</a>.
  </div>
  <hr> ";
  exit();
}


// Header
echo "
<html>
  <head>
    <meta charset=\"UTF-8\">
    <script src=\"http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.js\"></script> 
    <link rel=\"stylesheet\" href=\"http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.css\" />
    <link rel=\"stylesheet\" href=\"hra-dash.css\">

    <script src=\"http://code.jquery.com/jquery-1.10.1.min.js\"></script>
    <script src=\"./libs/jquery.csv-0.71.js\"></script>
    <script src=\"http://d3js.org/d3.v3.min.js\" charset=\"utf-8\"></script>
    <script src=\"http://d3js.org/colorbrewer.v1.min.js\"></script>
    <script src=\"http://dimplejs.org/dist/dimple.v2.1.2.min.js\"></script>
    <!--<script src=\"http://cdn.datatables.net/1.10.5/js/jquery.dataTables.min.js\"></script>-->
    <!--<link rel=\"stylesheet\" href=\"http://cdn.datatables.net/1.10.5/css/jquery.dataTables.css\">-->

    <!-- Latest compiled and minified CSS -->
    <link rel=\"stylesheet\" href=\"https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap.min.css\">

    <!-- Optional theme -->
    <link rel=\"stylesheet\" href=\"https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap-theme.min.css\">

    <!-- Latest compiled and minified JavaScript -->
    <script src=\"https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/js/bootstrap.min.js\"></script>

    <!-- Bootstrap file for file input forms -->
    <script type=\"text/javascript\" src=\"./libs/bootstrap-filestyle.min.js\"> </script>

    
    <!--<script src=\"https://raw.githubusercontent.com/novus/nvd3/master/build/nv.d3.min.js\"></script>-->

    <!--<script src=\"https://www.google.com/jsapi\"></script>-->

    <script type=\"text/javascript\" src=\"./libs/leaflet-ajax-master/dist/leaflet.ajax.js\"></script>
    <script src=\"https://api.tiles.mapbox.com/mapbox.js/plugins/leaflet-pip/v0.0.2/leaflet-pip.js\"></script>
  </head>
  <body> ";

 //
 // Initialize Tabs
 //

 echo "
<div class=\"container\" style=\"margin-bottom: 18px; margin-top: 12px;\">
  <div role=\"tabpanel\" id=\"content\"> 

  <h3>Habitat Risk Assessment Dashboard</h3>
  <div id=\"url-holder\" style=\"float:right;\"></div>

  <ul class=\"nav nav-tabs\" role=\"tablist\" id=\"mytabs\">
    <li role=\"presentation\" class=\"active\"><a href=\"#upload\" aria-controls=\"upload\" role=\"tab\" data-toggle=\"tab\">Upload</a></li>
    <li role=\"presentation\" class=\"disabled\"><a href=\"#maptab\" aria-controls=\"maptab\" role=\"tab\" data-toggle=\"tab\">Map</a></li>
    <li role=\"presentation\" class=\"disabled\"><a href=\"#charttab\" aria-controls=\"charttab\" role=\"tab\" data-toggle=\"tab\">Charts</a></li>
    <!--<li role=\"presentation\" class=\"disabled\"><a href=\"#pptab\" aria-controls=\"pptab\" role=\"tab\" data-toggle=\"tab\">Post-Process</a></li>-->
    <li role=\"presentation\"><a href=\"#abouttab\" aria-controls=\"abouttab\" role=\"tab\" data-toggle=\"tab\">About</a></li>
  </ul> ";

  echo "
  <div class=\"tab-content\">
  <div role=\"tabpanel\" class=\"tab-pane active\" id=\"upload\">
    <div id=formbody>
      <form enctype=\"multipart/form-data\" id=\"form1\" name=\"form1\" method=\"post\" action=\"$_SERVER[PHP_SELF]\" accept-charset=utf-8>
      <div class=\"row\">
      <div class=\"col-lg-8\">
        <input type=hidden name=doit value=y>
        <br></br>
        <h4>View results of the HRA model by zipping and uploading your output workspace folder.</h4>
        <h5><em>Zip this folder:</em></h5>
        <img src=\"img/hra_zip_screenshot_edit.PNG\" width=\"250\"></img>
        <p></p>
        <input name=\"zipfile\" type=\"file\" class=\"filestyle\" data-buttonBefore=\"true\" data-buttonText=\"Browse...\">
        <!--<ul><br>Select <b>\"Your HRA Output Workspace.zip\"</b></ul>-->
        <br>
        <input type=Submit name=junk value=\"Upload Results\">
      </div>
      <div class=\"col-lg-4\">
      </div>
      </div>
      <div class=\"row\">
      <div class=\"col-lg-8\"></div>
      <div class=\"col-lg-4\">
        <br>
        <div class=\"well well-sm\">
          <ul>
            <b>Don't have results to upload just yet?</b>
            <br>
            <br><input type=Submit name=demoit value=\"View Sample Results\">
          </ul>
        </div>
      </div>
      </div>
      </form>
    </div>";
    // IF 'doit' means if Upload button was clicked.
    if (isset($_GET['dashid']) | (isset($_POST['doit']) & !empty($_FILES['zipfile']['tmp_name']))) {
      // File Quality Control
      // // check for errors
      if (!isset($_GET['dashid'])) {
        if ($_FILES["zipfile"]["error"] > 0){
          echo "<div class=\"alert alert-danger\" role=\"alert\">" . $_FILES["zipfile"]["error"] . "</div>";
          die;   // THIS IS IMPORTANT, or else it continues running, launches the R script, etc!!
        }
      }
      // if dashboard session id is provided, check for legend.json
      // that indicates that dashboard's R script completed successfully sometime in the past. 
      if (isset($_GET['dashid'])){
        if(!file_exists("./tmp-hra/" . $_GET['dashid'] . "/legend.json")){
          echo "<div class=\"alert alert-info\" role=\"alert\">This Dashboard Session ID does not have results. </div>";
          echo "<div class=\"alert alert-danger\" role=\"alert\">11-21-2016: Due to server maintenance, sessions created before 11-20-2016 are temporariliy unavailable. Sorry for this inconvenience. Sessions will be restored as soon as possible.</div>";
          die;
        }
      }

      echo "<div class=\"alert alert-info\" role=\"alert\">starting session... </div>";
//       if (null != session_id()) {
//       if(session_id() == '' || !isset($_SESSION)) {
//         session_start();
//       } else {
        session_start();
        session_regenerate_id(FALSE);
//       }

      if (!isset($_GET['dashid'])){
        // make a unique folder for each run
        // // was using session (like in natcap docs autobuilder), then switched to datetime + who instead
        $sessid = session_id();
        $pathid = "./tmp-hra/" . $sessid . "/";
        $longurl = "vulpes.sefs.uw.edu/ttapp/hra-dash.php?dashid=" . $sessid;

        echo "<div class=\"alert alert-info\" role=\"alert\">Path ID: $pathid </div>";
        flush();
        ob_flush();

        // set the time limit to XX seconds
        set_time_limit(300);

        // Create session directory and cd to it
        $sdir = "$pathid";
        if (!file_exists($sdir)) {
          passthru("mkdir $pathid");
  //         mkdir($pathid);
        }

        // Upload the tables
        echo "<div class=\"alert alert-info\" role=\"alert\">uploading inputs...</div>";
        // // zipfile
        $outloadfile = $pathid . "workspace.zip";
        if (move_uploaded_file($_FILES['zipfile']['tmp_name'], $outloadfile)) {
          echo "<div class=\"alert alert-success\" role=\"alert\">zipfile was successfully uploaded.</div>";
        } else {
          echo "<div class=\"alert alert-danger\" role=\"alert\">zipfile cannot be uploaded.</div>";
        }

        // Run local R script
        echo "<div class=\"alert alert-info\" role=\"alert\">creating geojson files...</div>";
        flush();
        ob_flush();
        echo "<div class=\"alert alert-info\" role=\"alert\">";
        //passthru("R -q --vanilla '--args sess=\"$sessid\"' < io-rec.r | tee io.r.log | grep -e \"^[^>+]\" -e \"^> ####\" -e \"QAQC:\" -e \"^ERROR:\" -e \"WARN:\"");  // -e "^ " -e "^\[" 
        //passthru("R -q --vanilla '--args sess=\"$sessid\"' < io-rec.r | tee io.r.log | grep -e \"kadfkjalkjdfadijfaijdfkdfdsa\"");  // -e "^ " -e "^\["
        passthru("./io-hra.sh $sessid"); 
        echo "</div>";
        flush();
        ob_flush();

        set_time_limit(300);
      } else {
        $pathid = "./tmp-hra/" . $_GET['dashid'] . "/";
        $longurl = "vulpes.sefs.uw.edu/ttapp/hra-dash.php?dashid=" . $_GET['dashid'];
      }

      echo "<div class=\"alert alert-info\" role=\"alert\">Loading workspace data...</div>";
    }
    // Load Demo Data
    if (isset($_POST['demoit'])) {

      echo "<div class=\"alert alert-info\" role=\"alert\">starting session... </div>";

      $sessid = "processedHRA";
      $pathid = "./sample/" . $sessid . "/";

      echo "<div class=\"alert alert-info\" role=\"alert\">Path ID: $pathid </div>";
      flush();
      ob_flush();

      // set the time limit to XX seconds
      set_time_limit(300);

      echo "<div class=\"alert alert-info\" role=\"alert\">Loading sample data...</div>
      <script>
      console.log('switching?');
        $(function () {
          $('ul.nav li').removeClass('disabled');
          $('#mytabs a[href=\"#maptab\"]').tab('show')
        })
      </script>";
    }

  echo "
  </div>";

  if (isset($longurl)){
    echo "
    <script>
      console.log('switching?');
      $(function () {
        $('ul.nav li').removeClass('disabled');
        $('#mytabs a[href=\"#maptab\"]').tab('show')
      })
    </script>";
    echo "

    <div class=\"modal fade\" id=\"urlModal\" tabindex=\"-1\" role=\"dialog\" aria-labelledby=\"myModalLabel\">
    <div class=\"modal-dialog\" role=\"document\">
      <div class=\"modal-content\">
        <div class=\"modal-header\">
          <button type=\"button\" class=\"close\" data-dismiss=\"modal\" aria-label=\"OK\"><span aria-hidden=\"true\">&times;</span></button>
          <h4 class=\"modal-title\" id=\"myModalLabel\">Save and Share your Dashboard session:</h4>
        </div>
        <div class=\"modal-body\">
          <input type=\"text\" value=\"$longurl\" label=\"Double-click to Select\"></input>
          <br>
          <br>
          <li><b>Copy</b> this url to view these results again later, skipping the Upload step.</li>
          <li><b>Share</b> these results by sending around this url!</li>
          <li><b>Bookmark</b> this url!</li>
        </div>
        <div class=\"modal-footer\">
          <button type=\"button\" class=\"btn btn-default\" data-dismiss=\"modal\">Okay</button>
        </div>
      </div>
    </div>
    </div>

    <script>
    $('#url-holder').html('<button class=\"btn btn-success\" type=\"button\" data-toggle=\"modal\" data-target=\"#urlModal\">Share These Results!</button>');
    </script>";
  }

  echo "
  <div role=\"tabpanel\" class=\"tab-pane\" id=\"maptab\"> 
    <div class=\"row\">
      <div class=\"col-lg-9\">
        <div id=\"map\"></div>
        <!--<h5> select a layer to map:</h5>
        <select id=\"domain\"></select>-->
      </div>
      <div class=\"col-lg-3\">
          <br>
          <p><span class=\"glyphicon glyphicon-arrow-left\"></span>&nbsp;&nbsp;Select map layers</p> 
          <p style=\"text-indent: 25px\"><b>H_ : Habitats </b></p>
          <p style=\"text-indent: 40px\"><img src=\"img/hab-risk-legend.svg\"></img></p>
          <p style=\"text-indent: 25px\"><b>S_ : Stressors </b><img src=\"img/stressors-legend.svg\"></img></p>
          <hr>
          <p><span class=\"glyphicon glyphicon-hand-up\"></span>&nbsp;&nbsp;<b>Click a point</b> on the map to list Habitats and Stressors present at that location.</p>
          <div id='mapinfo' class='info'></div>
      </div>
  </div>
  </div>

  <div role=\"tabpanel\" class=\"tab-pane\" id=\"charttab\"> 
    <div class=\"row\">
      <div id=\"Bardiv\" class=\"col-lg-10\">
        <br>
        <h4>Plot this subregion:</h4>
        <select id=\"selectregion\"></select>
        <br>
          <div id = \"Dimplediv\"></div>
      </div>
    </div>
    <div class=\"row\">
      <div class=\"col-lg-6\">
      <br>
      <div class=\"panel panel-default\">
        <div class=\"panel-body\"> Plots show risk for each habitat within a given subregion.
        <br></br>
        There is one panel for each habitat. Colored points represent each stressor. Mouse over points to see values. 
        <br></br>
        <a href=\"http://data.naturalcapitalproject.org/nightly-build/invest-users-guide/html/habitat_risk_assessment.html#hra-equations\">Learn more about Risk plots from the InVEST User's Guide</a></div>
      </div>
      </div>
    </div>
  </div>
  <div role=\"tabpanel\" class=\"tab-pane\" id=\"abouttab\"> 
    <div class=\"row\">
      <div class=\"col-lg-7\">

        <h3>About</h3>

        <p>This application allows an <a href=\"http://www.naturalcapitalproject.org/InVEST.html\"> InVEST</a> user to view model results interactively in a web browser. 
        All the data displayed in this app come from the user's InVEST output workspace.</p>
        <p></p>
        <p>Not all of the results produced by the HRA model are displayed in this application.
        You may wish to explore and analyze your results further with GIS or data analysis software.</p>
        <h3> Compatibility </h3>
        <p>For best results, please try Google Chrome or Mozilla Firefox web browsers.</p>
        <p>This app has been tested with InVEST versions 3.1+</p>
        <br>
        <small><i>Built by the <a href=\"http://naturalcapitalproject.org\">Natural Capital Project</a>. The source code (R and javascript) is available and
        you are encouraged to submit bugs and feature requests at <a href=\"https://github.com/davemfish/ttapp/issues\">https://github.com/davemfish/ttapp/issues</a></i></small>
      </div>
    </div>
  </div>
  
  </div>
</div> ";

// If data already exists, map it yah!
//if (isset($_POST['pathid'])){  // this is here because when page is first loaded, the next line gives a warning that pathid is undefined
if (file_exists($pathid . "workspace.zip")) {

  echo "
    <script>

      // Define vars
      var geojson,
        metadata = [],
        sessPath = '$pathid',
        geojsonPath = sessPath + 'ecosys_risk.geojson',
        geojsonURLs = [],
        habURLs = [],
        stressURLs = [],
        ecoURL = [],
        habnames = [],
        stressnames = [],
        econame = [],
        aoiPath = sessPath + 'aoi.geojson',
        //csvPath = sessPath + 'habsummary.csv',
        tablePath = sessPath + 'habsummary.json',
        symbPath = sessPath + 'legend.json',
        barPath = sessPath + 'barplot.html'
        riskPath = sessPath + 'datECR_wca.csv'
        lyrs = []
      ;
    </script>
  ";
?>

<script>


// load tab content upon click
$('#tabs li a').each(function(){
  $(this).click(function(e){
    e.preventDefault()
    $(this).tab("show");
  });
});

$('#mytabs a[href=\"#charttab\"]').on('shown.bs.tab', function (e) {
  $("#Dimplediv").empty();
  // svg = dimple.newSvg("#Dimplediv", 800, 400);
  console.log('test');
  makeRiskTab();
});

$('#mytabs a[href=\"#maptab\"]').on("shown.bs.tab", function() {
    console.log("invalidate map at tab listener");
    //drawAOI();
    map.invalidateSize(false);
    map.fitBounds(savemap);
});


// Init some global vars
//var pgons = new L.featureGroup(); // this group holds all the geojson layers
    
// Map
map = L.map('map', {
  center: [0, 0],
  zoom: 2,
  maxZoom:15
});
//Basemap
EsriSat = L.tileLayer('http://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
  minZoom: 0,
  maxZoom: 15,
  attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
})
CartoDB_Positron = L.tileLayer('http://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png', {
  minZoom: 0,
  maxZoom: 15,
  subdomains: 'abcd',
  attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="http://cartodb.com/attributions">CartoDB</a>'
})

base = {
  "Satellite": EsriSat,
  "Grayscale": CartoDB_Positron
};

// Layer control in upper-right of map
var control = new L.control.layers(base).addTo(map);
// Info box for outside-the-map popups
var mapinfo = document.getElementById('mapinfo');
// object for bounds of AOI to use after calls to map.invalidateSize()
var savemap = {};
;


// add stuff to the map
map.addLayer(EsriSat);
//map.addLayer(pgons);
L.control.scale().addTo(map);

// Initialize legend
var legend = L.control({position: 'bottomright'});

// init default maplayer
var maplayer = 'ecosys_risk'

// click to identify function
// https://www.mapbox.com/mapbox.js/example/v1.0.0/identify-tool/
function handleClick(e) {
    // e.layer.closePopup();
    var html = '';
    // look through each layer in order and see if the clicked point,
    // e.latlng, overlaps with one of the shapes in it.
    for (var i = 0; i < lyrs.length; i++) {
        var match = leafletPip.pointInLayer(
            // the clicked point
            e.latlng,
            // this layer
            lyrs[i].layer,
            // whether to stop at first match
            false); // true and false seem to behave the same - DF
        // if there's overlap, add some content to the popup: the layer name
        // and a table of attributes
        if (match.length) {
            html += '<strong>' + lyrs[i].name + '</strong>';
            if(lyrs[i].name[0].substring(0,1) == "H"){
              console.log(match[0].feature.properties['CLASSIFY'])
              html += propertyTable(match[0].feature.properties);
            } 
            if(lyrs[i].name[0].substring(0,1) == "e"){
              html += propertyTable(match[0].feature.properties);
            }
            if(lyrs[i].name[0].substring(0,1) == "S"){
              html += '<hr color="#d3d3d3" size=1>'
            } 
            // if(lyrs[i].name.substring(0,1) == "S"){
            //  html += '<tr></tr>';
            // }
        }
    }
    if (html) {
        // map.openPopup(html, e.latlng);
        console.log(html);
        mapinfo.innerHTML = html;
    }
}

// create a simple table from the properties in a feature
function propertyTable(o) {
    var t = '<table class="table-condensed">';
    for (var k in o) {
      if(k != "cols" & k != 'VALUE'){
        // if(k.substring(0,1) == "H"){
        //  t += '<tr><td>' + k + '</td><td>' + o[k] + '</td></tr>';
        // }
          t += '<tr><td>' + k + '</td><td>' + o[k] + '</td></tr>';
      }
    }
    // t += '</table><br>'
    t += '</table><hr color="#d3d3d3" size=1>';
    return t;
}

// function propertyTable(o) {
//     var t = '<table class="table-condensed">';
//     for (var k in o) {
//       if(k != "cols"){
//         // if(k.substring(0,1) == "H"){
//         //  t += '<tr><td>' + k + '</td><td>' + o[k] + '</td></tr>';
//         // }
//           t += '<tr><td>' + k + '</td><td>' + o[k] + '</td></tr>';
//       }
//     }
//     // t += '</table><br>'
//     t += '</table><hr color="#d3d3d3" size=1>';
//     return t;
// }

// load AOI geojson, add to map
// get subregion names from geojson properties, build dropdown select for subregions
//var aoijson = {};
function drawAOI(){
  $.getJSON(aoiPath, function(data) { // provide url to geojson
        var aoi = L.geoJson(data,{ // build a leaflet layer with style parameters
          style: function(feature){
            return {
              fillColor: "orange",
              color: "#d3d3d3",
              fillOpacity:0.0,
              opacity:1,
              weight:0.5
            }
          }
          // onEachFeature: function (feature, layer) {
          //   layer.bindPopup(feature.properties.CLASSIFY);
          // }
        }).on('click', handleClick);
        aoi.setZIndex(-3);
        // lyrs.push({ name: "AOI", layer: aoi });
        control.addOverlay(aoi, "AOI");
        // add AOI to map
        // map.addLayer(aoi);
        console.log("invalidate at draw aoi");
        // map.invalidateSize(false);
        savemap = aoi.getBounds();
        console.log(savemap);
        map.fitBounds(savemap);


        console.log(aoi);
        // // Build subregion dropdown select from geojson properties
        // for (var i = 0; i <= aoi.features.length - 1; i++) {
        //   $("#region").append("<option value='" + i + "'>" + aoi.features[i].properties.name + "</option");
        // }
        //  // set the default selection to the 1st feature 
        // $("#region option[value='" + dropdown.indexOf(aoi.features[1].properties.name) + "']").attr("selected","selected");
  });
}
// map.invalidateSize(false);
drawAOI(); // and it builds subregion dropdown



//// A lot happens in here:
//// Read legend.json - build maplayer dropdown from legend elements; build legend div.
//// Read csv - build table and chart (chart responds to both dropdowns)
//// Read geoJSON (first with default layer, then in response to dropdown selection)
//function makePage() {
//function drawChart() {
$.getJSON(symbPath, function(symbols){
  var dropdown = [];
  //console.log(symbols);
  for (var i = 0; i < symbols.length; i++) {
    if (symbols[i].layer[0].substring(0,1) == "H"){
      habnames.push(symbols[i].layer)
      habURLs.push(sessPath + symbols[i].layer + ".geojson")
      // $("#domain").append("<option value='" + i + "'>" + symbols[i].layer + "</option");
      // dropdown.push(symbols[i].layer)
    }
    if (symbols[i].layer[0].substring(0,1) == "S"){
      stressnames.push(symbols[i].layer)
      stressURLs.push(sessPath + symbols[i].layer + ".geojson")
    }
    if (symbols[i].layer[0].substring(0,1) == "e"){
      econame.push(symbols[i].layer)
      ecoURL.push(sessPath + symbols[i].layer + ".geojson")
      // $("#domain").append("<option value='" + i + "'>" + symbols[i].layer + "</option");
      // dropdown.push(symbols[i].layer)
    }
    // set the default selection 
    // $("#domain option[value='" + dropdown.indexOf("ecosys_risk") + "']").attr("selected","selected");
  }

  function loadStress(s){
    $.getJSON(stressURLs[s], function(data) {
      nm = stressnames[s];
      // console.log(j);
      // console.log(layernames);
      // console.log(geojsonURLs);
      var geojson = L.geoJson(data, {
        style: function(feature){
                  gridcolor = feature.properties.cols.replace("hex", "#");
                  return {
                    fillColor:'#0C0C0B',
                    color:'#0C0C0B',
                    fillOpacity:0.3,
                    opacity:1,
                    weight:1
                  }
          }//, // style
        // onEachFeature: function (feature, layer) {
        //   layer.bindPopup(feature.properties.CLASSIFY);
        // }
      }).on('click', handleClick);
      //geojson.addTo(map);
      //geojson.bringToFront();
      //maplayers[nm] = geojson;
      lyrs.push({ name: nm, layer: geojson })
      control.addOverlay(geojson, nm);
    });
  }

  function loadHab(h){
    $.getJSON(habURLs[h], function(data) {
      nm = habnames[h];
      // console.log(j);
      // console.log(layernames);
      // console.log(geojsonURLs);
      var geojson = L.geoJson(data, {
        style: function(feature){
                  gridcolor = feature.properties.cols.replace("hex", "#");
                  return {
                    fillColor:gridcolor,
                    color:ColorLuminance(gridcolor, 1),
                    fillOpacity:0.7,
                    opacity:1,
                    weight:0.5
                  }
          }, // style
        onEachFeature: function (feature, layer) {
          // layer.bindPopup(feature.properties.CLASSIFY);
          layer.bindPopup();
        }
      }).on('click', handleClick);
      geojson.addTo(map);
      geojson.bringToBack();
      //maplayers[nm] = geojson;
      lyrs.push({ name: nm, layer: geojson })
      control.addOverlay(geojson, nm);
    });
  }

  for (var h = 0; h < habURLs.length; h++){
    loadHab(h);
  }
  if (h == habURLs.length){
    for (var s = 0; s < stressURLs.length; s++){
      loadStress(s);
    }
  }

  function loadEco(){
    $.getJSON(ecoURL[0], function(data) {
      nm = econame[0];

      var geojson = L.geoJson(data, {
        style: function(feature){
                  gridcolor = feature.properties.cols.replace("hex", "#");
                  return {
                    fillColor:gridcolor,
                    color:ColorLuminance(gridcolor, 1),
                    fillOpacity:0.7,
                    opacity:1,
                    weight:0.5
                  }
          }//, // style
        // onEachFeature: function (feature, layer) {
        //   layer.bindPopup(feature.properties.CLASSIFY);
        // }
      }).on('click', handleClick);
      //geojson.addTo(map);
      //maplayers[nm] = geojson;
      lyrs.push({ name: nm, layer: geojson })
      control.addOverlay(geojson, nm);
    });
  }
  loadEco();

}); // legend ajax

////////////////////
// Create Risk Plots
////////////////////

function makeRiskTab(){
  var svgwidth = 1000
  var svgheight = 100

  var svg = dimple.newSvg("#Dimplediv", svgwidth, svgheight);

  d3.csv(riskPath, function (data) {
    // find max exposure/consequence for upper limit on axes
    var expmax = d3.max(data, function(d) { return +d.Exposure;} );
    // console.log(expmax);
    var conmax = d3.max(data, function(d) { return +d.Consequence;} );
    // console.log(conmax);
    var axmax = Math.ceil(d3.max([expmax, conmax]));
    // console.log(axmax);
    // var hablen = d3.set(data, function(d) {return +d.Habitat;} ).values();
    // d3.select("#Habitat").selectAll("option").data(d3.map)
    
    function riskChart(region) {
        
      var dat = dimple.filterData(data, "Subregion", region);
      // Get a unique list of habitats
      var habitats = dimple.getUniqueValues(dat, "Habitat");
      // var hablen = d3.map(data, function(d){return d.Habitat; }).keys().length;
      svg.attr("height", 200*Math.ceil(habitats.length/4));
      // console.log(hablen);

      // get stressors to assign colors
      var stressors = dimple.getUniqueValues(dat, "Stressor");
      var cols = ["#1f78b4", "#a6cee3", "#33a02c", "#b2df8a", "#e31a1c", "#fb9a99", "#ff7f00", "#fdbf6f", "#6a3d9a", "#cab2d6", "#b15928", "#ffff99"];
      // console.log(stressors[0]);
      // console.log(cols[0]);
      // Set the bounds for the charts
      var row = 0,
          col = 0,
          top = 25,
          left = 50,
          inMarg = 30,
          width = 130, //130,
          height = 130, //110,
          totalWidth = parseFloat(svg.attr("width")),
          legWidth = 90; // 


      // Draw a chart for each of the habitats
      function plotHab(hab, index, array){
          
          // Wrap to the row below
          if (legWidth + left + ((col + 1) * (width + inMarg)) > totalWidth) {
            row += 1;
            col = 0;
          }
          
          // Filter for the Habitat in the iteration
          var chartData = dimple.filterData(dat, "Habitat", hab);
          
          // Use d3 to draw a text label for the habitat
          svg.append("text")
                .attr("x", left + (col * (width + inMarg)) + (width / 2))
                .attr("y", top + (row * (height + inMarg)) + (height / 2) + 12)
                .style("font-family", "sans-serif")
                .style("text-anchor", "middle")
                .style("font-size", "28px")
                .style("opacity", 0.2)
                .text(chartData[0].Habitat.substring(0, 7));
          
          // Create a chart at the correct point in the trellis
          var myChart = new dimple.chart(svg, chartData);
          
          // Add x 
          var x = myChart.addMeasureAxis("x", "Exposure");
          
          // Add y 
          var y = myChart.addMeasureAxis("y", "Consequence");
          
          // Habitat and Risk are only added for the tooltip, 
          // color groups are based on the final series 'Stressor'
          myChart.addSeries(["Habitat", "Risk", "Stressor"], dimple.plot.bubble);

          // assign colors
          if (stressors.length < 7) {
            for (var s = 0; s < stressors.length; s++) {
              myChart.assignColor(stressors[s], cols[s*2]);
            }
          } else {
            for (var s = 0; s < stressors.length; s++) {
              myChart.assignColor(stressors[s], cols[s]);
            }
          }
          // myChart.assignColor("PrawnFishery", "Red");
          // console.log(myChart);          

          if (index === habitats.length - 1){ 
               console.log("Last callback call at index " + index + " with value " + hab ); 
               var myLegend = myChart.addLegend(4*width + 4*inMarg*2, 10, legWidth, parseFloat(svg.attr("height")), "Right");
           }
          // var myLegend = myChart.addLegend(530, 160, 60, 300, "Right");
          // myChart.addLegend(10, 10, 60, 300, "Right", "Stressor");

          // Draw the chart and adjust settings
          myChart.draw();
          x.shapes.selectAll("text").attr("fill", "#5e5e5e");
          y.shapes.selectAll("text").attr("fill", "#5e5e5e");
          // setting these tickFormats after draw() only affects tooltip
          x.tickFormat = ',.2f';
          y.tickFormat = ',.2f';
          x.ticks = axmax;
          y.ticks = axmax;
          // console.log(x);
          // x.showGridlines = false;
          // y.showGridlines = false;
          // x.shapes.selectAll("text").attr("font-size", "16px");
          // y.shapes.selectAll("text").attr("font-size", "16px");
          x.overrideMax = axmax;
          y.overrideMax = axmax;
          x.overrideMin = 0;
          y.overrideMin = 0;

          myChart.setBounds(
            left + (col * (width + inMarg)),
            top + (row * (height + inMarg)),
            width,
            height);

          // Once drawn we can access the shapes
          // If this is not in the first column remove the y text
          if (col > 0) {
            y.shapes.selectAll("text").remove();
            y.titleShape.remove();
          }
          // // If this is not in the last row remove the x text
          // if (row < 2) {
          //    x.shapes.selectAll("text").remove();
          // }
          // Remove the axis labels
          // y.titleShape.remove();
          // x.titleShape.remove();

          // Move to the next column
          col += 1;

      }
      habitats.forEach(plotHab);
      
    }; // def riskChart function

    var regions = dimple.getUniqueValues(data, "Subregion");
    $("#selectregion").empty();
    for (var i = 0; i < regions.length; i++) {
      $("#selectregion").append("<option value='" + i + "'>" + regions[i] + "</option");
    }

    $("#selectregion").change(function(){
      $("#Dimplediv").empty();
      svg = dimple.newSvg("#Dimplediv", svgwidth, svgheight);

      subregion = $("#selectregion option:selected").text();
      riskChart(subregion);
     });

    // $("#Dimplediv").empty();
    // svg = dimple.newSvg("#Dimplediv", 800, 400);
    riskChart(regions[0]);
  }); // csv load
}; // makeRiskTab function

    

/// All js below is functions for creating the marker cluster symbols

function defineFeature(feature, latlng) {
  var categoryVal = feature.properties[categoryField];
    //iconVal = feature.properties[iconField];
    var myClass = 'marker category-'+categoryVal;
    var myIcon = L.divIcon({
        className: myClass,
        iconSize:null
    });
    return L.marker(latlng, {icon: myIcon});
}

function ColorLuminance(hex, lum) {

  // validate hex string
  hex = String(hex).replace(/[^0-9a-f]/gi, '');
  if (hex.length < 6) {
    hex = hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
  }
  lum = lum || 0;

  // convert to decimal and change luminosity
  var rgb = "#", c, i;
  for (i = 0; i < 3; i++) {
    c = parseInt(hex.substr(i*2,2), 16);
    c = Math.round(Math.min(Math.max(0, c + (c * lum)), 255)).toString(16);
    rgb += ("00"+c).substr(c.length);
  }

  return rgb;
}
//console.log(popupFields);


 
</script>

<?php

} else {

  echo "
  <script>
    $(function () {
      $('#mytabs a:first').tab('show')
    })
  </script> ";

}
//}

 //
 // BOTTOM BUSINESS
 //

require_once("footer.php");

echo "
  </body>
</html> ";

?>
