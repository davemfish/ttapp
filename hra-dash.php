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
    if (isset($_POST['doit']) & !empty($_FILES['zipfile']['tmp_name'])) {
      // File Quality Control
      // // check for errors
      if ($_FILES["zipfile"]["error"] > 0) {
        echo "<div class=\"alert alert-danger\" role=\"alert\">" . $_FILES["zipfile"]["error"] . "</div>";
        die;   // THIS IS IMPORTANT, or else it continues running, launches the R script, etc!!
      }

      echo "<div class=\"alert alert-info\" role=\"alert\">starting session... </div>";
//       if (null != session_id()) {
//       if(session_id() == '' || !isset($_SESSION)) {
//         session_start();
//       } else {
        session_start();
        session_regenerate_id(FALSE);
//       }
      // make a unique folder for each run
      // // was using session (like in natcap docs autobuilder), then switched to datetime + who instead
      $sessid = session_id();
      $pathid = "./tmp-hra/" . $sessid . "/";

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

      echo "<div class=\"alert alert-info\" role=\"alert\">Loading workspace data...</div>";
      echo "
      <script>
      console.log('switching?');
        $(function () {
          $('ul.nav li').removeClass('disabled');
          $('#mytabs a[href=\"#maptab\"]').tab('show')
	        map.invalidateSize(false);
        })
      </script> ";
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

      echo "<div class=\"alert alert-info\" role=\"alert\">Loading sample data...</div>";
      echo "
      <script>
      console.log('switching?');
        $(function () {
          $('ul.nav li').removeClass('disabled');
          $('#mytabs a[href=\"#maptab\"]').tab('show')
          map.invalidateSize(false);
        })
      </script> ";
    }

  echo "
  </div>

  <div role=\"tabpanel\" class=\"tab-pane active\" id=\"maptab\"> 
    <div class=\"row\">
      <div class=\"col-lg-8\">
        <div class='custom-popup' id=\"map\"></div>
        <!--<h5> select a layer to map:</h5>
        <select id=\"domain\"></select>-->
      </div>
      <div class=\"col-lg-3\">
          <p> <b>Habitat (H_...)</b> layers are colored by their <b style='background-color:lightgray'><font color='blue'>'LOW'</font>, <font color='yellow'>'MED'</font>, <font color='red'>'HIGH'</font></b> Risk classifications.</p>
          <p> <b>Stressor (S_...)</b> layers, AOI polygons, overall ecosystem risk, and alternate basemap layers 
          can be turned on from the layers control box in the <b>upper-right of the map.</b></p>
          <p> <b>Click a point</b> on the map to list the Habitats and Stressors present at that location.</p>
          <div id='mapinfo' class='info'></div>
      </div>
  </div>
  </div>

  <div role=\"tabpanel\" class=\"tab-pane\" id=\"charttab\"> 
    <div class=\"row\">
      <div id=\"Bardiv\" class=\"col-lg-8\">
        <br>
        <h4>Plot this subregion:</h4>
        <select id=\"selectregion\"></select>
        <br>
          <div id = \"Dimplediv\"></div>
      </div>
      <div class=\"col-lg-3\">
      <br>
      <p>These figures show the cumulative risk for each habitat within a given subregion.</p> 
      <p>There is one subplot for every habitat. Within the habitat plot, there are points for every stressor.</p> 
      <p>Each point is graphed by Exposure and Consequence values. If the risk equation chosen was Euclidean, 
      the distance from the stressor point to the origin represents the average risk for that habitat-stressor pair within the selected subregion.</p> 
      <p>Stressors that have high exposure scores and high consequence scores pose the greatest risk to habitats. 
      <p>Reducing risk through management is likely to be more effective in situations where high risk is driven by high exposure, not high consequence.</p>
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
$('#upload a').click(function (e) {
  e.preventDefault()
  $(this).tab('show')
})

$('#maptab a').click(function (e) {
  e.preventDefault()
  $(this).tab('show')
})

$('#charttab a').click(function (e) {
  e.preventDefault()
  $(this).tab('show')
})

// $('#pptab a').click(function (e) {
//   e.preventDefault()
//   $(this).tab('show')
// })

$('#abouttab a').click(function (e) {
  e.preventDefault()
  $(this).tab('show')
})


// Init some global vars
//var pgons = new L.featureGroup(); // this group holds all the geojson layers
    
    // Map
    map = L.map('map', {
      center: [0, 0],
      zoom: 2,
      maxZoom:15
    });

    //Basemap
    MapBoxSat = L.tileLayer('https://a.tiles.mapbox.com/v3/geointerest.map-dqz2pa8r/{z}/{x}/{y}.png', {
      minZoom: 0,
      maxZoom: 15,
      attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, Imagery © <a href="http://mapbox.com">Mapbox</a>'
    })
    OpenMapSurfer_Roads = L.tileLayer('http://openmapsurfer.uni-hd.de/tiles/roads/x={x}&y={y}&z={z}', {
      minZoom: 0,
      maxZoom: 15,
      attribution: 'Imagery from <a href="http://giscience.uni-hd.de/">GIScience Research Group @ University of Heidelberg</a> &mdash; Map data &copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    })
    
    base = {
      "Satellite": MapBoxSat,
      "Physical/Political": OpenMapSurfer_Roads
    };

    // Layer control in upper-right of map
    var control = new L.control.layers(base).addTo(map);
    // Info box for outside-the-map popups
    var mapinfo = document.getElementById('mapinfo');
;


// add stuff to the map
map.addLayer(MapBoxSat);
//map.addLayer(pgons);


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
            if(lyrs[i].name[0].substring(0,1) == "H" | lyrs[i].name[0].substring(0,1) == "e"){
              html += propertyTable(match[0].feature.properties);
            } else {
              // html += '<br>'
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

// create a simple table from the properties in a feature, like the
// name of a state or district
function propertyTable(o) {
    var t = '<table class="table-condensed">';
    for (var k in o) {
      if(k != "cols"){
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
        map.fitBounds(aoi.getBounds());

        console.log(aoi);
        // // Build subregion dropdown select from geojson properties
        // for (var i = 0; i <= aoi.features.length - 1; i++) {
        //   $("#region").append("<option value='" + i + "'>" + aoi.features[i].properties.name + "</option");
        // }
        //  // set the default selection to the 1st feature 
        // $("#region option[value='" + dropdown.indexOf(aoi.features[1].properties.name) + "']").attr("selected","selected");
  });
}
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

  var svg = dimple.newSvg("#Dimplediv", 800, 400);

    d3.csv(riskPath, function (data) {
    
    function riskChart(region) {
        
      var dat = dimple.filterData(data, "Subregion", region);
      // Get a unique list of habitats
      var habitats = dimple.getUniqueValues(dat, "Habitat");

      // get stressors to assign colors
      var stressors = dimple.getUniqueValues(dat, "Stressor");
      var cols = ["#a6cee3", "#1f78b4", "#b2df8a", "#33a02c", "#fb9a99", "#e31a1c", "#fdbf6f", "#ff7f00", "#cab2d6", "#6a3d9a", "#ffff99", "#b15928"];
      console.log(stressors[0]);
      console.log(cols[0]);
      // Set the bounds for the charts
      var row = 0,
          col = 0,
          top = 25,
          left = 60,
          inMarg = 40,
          width = 130,
          height = 110,
          totalWidth = parseFloat(svg.attr("width"));

      // Draw a chart for each of the habitats
      habitats.forEach(function (hab) {
          
          // Wrap to the row above
          if (left + ((col + 1) * (width + inMarg)) > totalWidth) {
            row += 1;
            col = 0;
          }
          
          // Filter for the Habitat in the iteration
          var chartData = dimple.filterData(dat, "Habitat", hab);
          
          // Use d3 to draw a text label for the habitat
          svg
            .append("text")
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

          // var myLegend = myChart.addLegend(530, 160, 60, 300, "Right");
          // Draw the chart and adjust settings
          myChart.draw();
          x.shapes.selectAll("text").attr("fill", "#5e5e5e");
          y.shapes.selectAll("text").attr("fill", "#5e5e5e");
          x.tickFormat = ',.1f';
          x.ticks = 5;
          y.ticks = 5;
          // console.log(x);
          // x.showGridlines = false;
          // y.showGridlines = false;
          // x.shapes.selectAll("text").attr("font-size", "16px");
          // y.shapes.selectAll("text").attr("font-size", "16px");
          // x.overrideMax = 4.0;
          // y.overrideMax = 4.0;
          // x.overrideMin = 0;
          // y.overrideMin = 0;

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

      }, this);
      
    }; // def riskChart function

    var regions = dimple.getUniqueValues(data, "Subregion");
    for (var i = 0; i < regions.length; i++) {
      $("select").append("<option value='" + i + "'>" + regions[i] + "</option");
    }

    $("select").change(function(){
      $("#Dimplediv").empty();
      svg = dimple.newSvg("#Dimplediv", 800, 400);

      subregion = $("#selectregion option:selected").text();
      riskChart(subregion);
     });

    riskChart("ClaySound");
   }); // csv load

    // // Build table from csv to display as google vis

    // // transform the CSV string into a 2-dimensional array
    // var arrayData = $.csv.toArrays(csvString, {onParseValue: $.csv.hooks.castToScalar});
    // //console.log(arrayData);

    // // make DataTable from entire arrayData
    // var data = new google.visualization.arrayToDataTable(arrayData);
    // // link to div
    // var table = new google.visualization.Table(document.getElementById('table_div'));
    // // make a view with subset of data (in this case view still includes entire data array)
    // var tableview = new google.visualization.DataView(data);

    // // get the col numbers of all that should appear in table
    // // var collist = [];
    // // for (var i = 0; i <= arrayData[0].length - 1; i++) {
    // //     collist.push(i);
    // // }
    // // tableview.setColumns(collist);

    // table.draw(tableview, {showRowNumber: false, page: 'enable', pageSize:25});


    // // Build Chart from same DataTable as above
    // var chartview = new google.visualization.DataView(data);

    // var subregion = $("#region option:selected").text(); // get selected subregion from dropdown
    // maplayer = $("#domain option:selected").text(); // get selected maplayer from dropdown
    // // filter view by values of certain columns
    // chartview.setRows(chartview.getFilteredRows([{column: 2, value: maplayer}, {column: 3, value: subregion}]));
    // chartview.setColumns([0,1]);

    // var options = {
    //    title: 'Distribution of Risk Scores',
    //    legend: { position: 'none' },
    //    colors: ['gray'],
    // };

    // //var chart = new google.charts.Bar(document.getElementById("chart_div"));
    // var chart = new google.visualization.ColumnChart(document.getElementById("chart_div"));
    // //var chart = new google.visualization.Histogram(document.getElementById('chart_div'));
    // chart.draw(chartview, options);


    // // set listener for the subregion dropdown
    // $("#region").change(function(){
    //   subregion = $("#region option:selected").text();
    //   chartview = new google.visualization.DataView(data);
    //     //console.log(chartview);

    //   chartview.setRows(chartview.getFilteredRows([{column: 2, value: maplayer}, {column: 3, value: subregion}]));
    //   chartview.setColumns([0,1]);

    //    // update the chart
    //   chart.draw(chartview, options);

    // });

    // // set listener for the maplayer dropdown, chart and map must respond
    // $("#domain").change(function(){
    //   // get current selection
    //   maplayer = $("#domain option:selected").text();

    //   // reset chartview to entire dataTable
    //   chartview = new google.visualization.DataView(data);

    //   // filter rows by currently selected maplayer and subregion
    //   chartview.setRows(chartview.getFilteredRows([{column: 2, value: maplayer}, {column: 3, value: subregion}]));
    //   chartview.setColumns([0,1]);

    //   // redraw the chart
    //   chart.draw(chartview, options);

    // });

  // }); // end get CSV json
//};  /// end of drawChart()
//makePage();

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
