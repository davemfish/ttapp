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
    <script src=\"http://cdn.datatables.net/1.10.5/js/jquery.dataTables.min.js\"></script>
    <link rel=\"stylesheet\" href=\"http://cdn.datatables.net/1.10.5/css/jquery.dataTables.css\">

    <!-- Latest compiled and minified CSS -->
    <link rel=\"stylesheet\" href=\"https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap.min.css\">

    <!-- Optional theme -->
    <link rel=\"stylesheet\" href=\"https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap-theme.min.css\">

    <!-- Latest compiled and minified JavaScript -->
    <script src=\"https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/js/bootstrap.min.js\"></script>

    <!-- Bootstrap file for file input forms -->
    <script type=\"text/javascript\" src=\"./libs/bootstrap-filestyle.min.js\"> </script>

    <!--<script src=\"http://d3js.org/d3.v3.min.js\" charset=\"utf-8\"></script>-->
    <!--<script src=\"https://raw.githubusercontent.com/novus/nvd3/master/build/nv.d3.min.js\"></script>-->

    <script src=\"https://www.google.com/jsapi\"></script>

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
    <li role=\"presentation\" class=\"disabled\"><a href=\"#one\" aria-controls=\"one\" role=\"tab\" data-toggle=\"tab\">Map</a></li>
    <li role=\"presentation\" class=\"disabled\"><a href=\"#two\" aria-controls=\"two\" role=\"tab\" data-toggle=\"tab\">Table</a></li>
    <li role=\"presentation\"><a href=\"#three\" aria-controls=\"three\" role=\"tab\" data-toggle=\"tab\">About</a></li>
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
        <h4>View results of the InVEST Habitat Risk Assessment model by uploading the ....</h4>
        <br></br>
        <input name=\"logfile\" type=\"file\" class=\"filestyle\" data-buttonBefore=\"true\" data-buttonText=\"Browse...\">
        <ul><br>Select <b>....something....</i>.txt</b> from your InVEST workspace</ul>
        <br>
        <input type=Submit name=junk value=\"Upload Results\">
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
    if (isset($_POST['doit']) & !empty($_FILES['logfile']['tmp_name'])) {
      // File Quality Control
      // // check for errors
      if ($_FILES["logfile"]["error"] > 0) {
        echo "<div class=\"alert alert-danger\" role=\"alert\">" . $_FILES["logfile"]["error"] . "</div>";
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
      $pathid = "./tmp-rec/" . $sessid . "/";

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
      // // logfile
      $outloadfile = $pathid . "rec_logfile.txt";
      if (move_uploaded_file($_FILES['logfile']['tmp_name'], $outloadfile)) {
        echo "<div class=\"alert alert-success\" role=\"alert\">logfile was successfully uploaded.</div>";
      } else {
        echo "<div class=\"alert alert-danger\" role=\"alert\">logfile cannot be uploaded.</div>";
      }

      // Run local R script
      echo "<div class=\"alert alert-info\" role=\"alert\">creating geojson files...</div>";
      flush();
      ob_flush();
      echo "<div class=\"alert alert-info\" role=\"alert\">";
      //passthru("R -q --vanilla '--args sess=\"$sessid\"' < io-rec.r | tee io.r.log | grep -e \"^[^>+]\" -e \"^> ####\" -e \"QAQC:\" -e \"^ERROR:\" -e \"WARN:\"");  // -e "^ " -e "^\[" 
      passthru("R -q --vanilla '--args sess=\"$sessid\"' < io-rec.r | tee io.r.log | grep -e \"kadfkjalkjdfadijfaijdfkdfdsa\"");  // -e "^ " -e "^\[" 
      echo "</div>";
      flush();
      ob_flush();

      echo "
      <script>
      console.log('switching?');
        $(function () {
          $('ul.nav li').removeClass('disabled');
          $('#mytabs a[href=\"#one\"]').tab('show')
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
          $('#mytabs a[href=\"#one\"]').tab('show')
          map.invalidateSize(false);
        })
      </script> ";
    }

  echo "
  </div>

  <div role=\"tabpanel\" class=\"tab-pane\" id=\"one\"> 
    <div class=\"row\">
      <div class=\"col-lg-7\">
        <div id=\"map\"></div>
        <!--<h5> select a layer to map:</h5>
        <select id=\"domain\"></select>-->
      </div>
      <div class=\"col-lg-5\">
        <div id=\"chart_div\"></div>
        
        <!--<h5> select a subregion to plot:</h5>
        <select id=\"region\"></select>-->
      </div>
  </div>
  </div>

  <div role=\"tabpanel\" class=\"tab-pane\" id=\"two\"> 
  <div class=\"row\">
      <div class=\"col-lg-6\">
        <div id=\"table_div\">
          <table id=\"habsummary\" class=\"display\">
            <thead>
                <tr>
                    <th>Habitat</th>
                    <th>Area</th>
                    <th>Classify</th>
                    <th>Subregion</th>
                </tr>
            </thead>
     
            <tfoot>
                <tr>
                    <th>Habitat</th>
                    <th>Area</th>
                    <th>Classify</th>
                    <th>Subregion</th>
                </tr>
            </tfoot>
          </table>
        </div>
      </div>
      <div class=\"col-lg-6\">
        <div>
          <!--<a href=\"https://plot.ly/~davemfish/32/\" target=\"_blank\" title=\"LOW, MED, HIGH\" style=\"display: block; text-align: center;\"><img src=\"https://plot.ly/~davemfish/32.png\" alt=\"LOW, MED, HIGH\" style=\"max-width: 100%;\"  onerror=\his.onerror=null;this.src='https://plot.ly/404.png';\" /></a>
          <script data-plotly=\"davemfish:32\" src=\"https://plot.ly/embed.js\" async></script>-->
          <iframe width=\"640\" height=\"480\" frameborder=\"0\" seamless=\"seamless\" scrolling=\"no\" src=\"https://plot.ly/~davemfish/32/.embed?width=640&height=480\" ></iframe>
        </div>
      </div>
    </div>
  </div>
  <div role=\"tabpanel\" class=\"tab-pane\" id=\"three\"> 
    <div class=\"row\">
      <div class=\"col-lg-7\">

        <h3>About</h3>

        <p>This application allows an <a href=\"http://www.naturalcapitalproject.org/InVEST.html\"> InVEST</a> user to view model results interactively in a web browser. 
        All the data displayed in this app come from the <em>grid.shp</em> shapefile in the results zip file of an InVEST output workspace.</p>
        <p>The raw data from the <em>grid.shp</em> is viewable on the Table tab and on the Map.
        If your gridded AOI contains more than 3000 cells, the map will display cells as points,
        which are clustered together at low zoom levels. A cluster's color represents the largest value point within the cluster.
        Clicking a cluster reveals all its individual points.</p>
        <p>Not all of the results produced by the Recreation model are displayed in this application.
        You may wish to explore and analyze your results further with GIS or data analysis software.</p>
        <h3> Compatibility </h3>
        <p>For best results, please try Google Chrome or Mozilla Firefox web browsers.</p>
        <p>This app has only been tested with InVEST versions 3.0.0+</p>
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
if (file_exists($pathid . "habsummary.csv")) {

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
        lyrs = []
        //initPath = sessPath + 'init.json',
        //categoryField = 'cols', //This is the fieldname for marker category (used in the pie and legend)
        //popupFields = [] //Popup will display these fields
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

$('#one a').click(function (e) {
  e.preventDefault()
  $(this).tab('show')
})

$('#two a').click(function (e) {
  e.preventDefault()
  $(this).tab('show')
})

$('#three a').click(function (e) {
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
            if(lyrs[i].name.substring(0,1) == "H" | lyrs[i].name.substring(0,1) == "e"){
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
        map.openPopup(html, e.latlng);
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



// load the visualization library from Google and set a listener
google.load("visualization", "1", {packages:["corechart"]});
//google.load("visualization", "1.1", {packages:["bar"]});
google.load("visualization", "1", {packages:["table"]});
// google.setOnLoadCallback(drawChart);


//// A lot happens in here:
//// Read legend.json - build maplayer dropdown from legend elements; build legend div.
//// Read csv - build table and chart (chart responds to both dropdowns)
//// Read geoJSON (first with default layer, then in response to dropdown selection)
//function makePage() {
//function drawChart() {
  $.getJSON(symbPath, function(symbols){
    var dropdown = [];
    for (var i = 0; i < symbols.length; i++) {
      if (symbols[i].layer.substring(0,1) == "H"){
        habnames.push(symbols[i].layer)
        habURLs.push(sessPath + symbols[i].layer + ".geojson")
        // $("#domain").append("<option value='" + i + "'>" + symbols[i].layer + "</option");
        // dropdown.push(symbols[i].layer)
      }
      if (symbols[i].layer.substring(0,1) == "S"){
        stressnames.push(symbols[i].layer)
        stressURLs.push(sessPath + symbols[i].layer + ".geojson")
      }
      if (symbols[i].layer.substring(0,1) == "e"){
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
        geojson.addTo(map);
        geojson.bringToFront();
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
            layer.bindPopup(feature.properties.CLASSIFY);
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


   // Load the habitat summary data
  $('#habsummary').dataTable( {
    "ajax": tablePath,
    "columns": [
            { "data": "Habitat" },
            { "data": "Area" },
            { "data": "Classify" },
            { "data": "Subregion" }
        ]
    } );

//   d3.json(tablePath, function(data){
//     nv.addGraph(function() {
//       var chart = nv.models.multiBarChart()
//         .transitionDuration(350)
//         .reduceXTicks(true)   //If 'false', every single x-axis tick label will be rendered.
//         .rotateLabels(0)      //Angle to rotate x-axis labels.
//         .showControls(true)   //Allow user to switch between 'Grouped' and 'Stacked' mode.
//         .groupSpacing(0.1)    //Distance between each group of bars.
//       ;

//       chart.xAxis.tickFormat(function(d) {
//         return d3.format(',f')(data[0]);

//       chart.yAxis.tickFormat(d3.format(',.1f'));
//       var d = [{
//         values: data[1],
//         key: "Area"
//         color
//       }]

//       d3.select('#chart1 svg')
//           .datum(exampleData())
//           .call(chart);

//       nv.utils.windowResize(chart.update);

//       return chart;
//   });
// });
//   });

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