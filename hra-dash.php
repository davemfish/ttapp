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
    <link rel=\"stylesheet\" href=\"rec-dash.css\">

    <script src=\"http://code.jquery.com/jquery-1.10.1.min.js\"></script>
    <script src=\"./libs/jquery.csv-0.71.js\"></script>

    <!-- Latest compiled and minified CSS -->
    <link rel=\"stylesheet\" href=\"https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap.min.css\">

    <!-- Optional theme -->
    <link rel=\"stylesheet\" href=\"https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap-theme.min.css\">

    <!-- Latest compiled and minified JavaScript -->
    <script src=\"https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/js/bootstrap.min.js\"></script>

    <!-- Bootstrap file for file input forms -->
    <script type=\"text/javascript\" src=\"./libs/bootstrap-filestyle.min.js\"> </script>

    <script src=\"http://d3js.org/d3.v3.min.js\" charset=\"utf-8\"></script>
    <script src=\"https://www.google.com/jsapi\"></script>

    <script type=\"text/javascript\" src=\"./libs/leaflet-ajax-master/dist/leaflet.ajax.js\"></script>
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
        <h5> select a layer to map:</h5>
        <select id=\"domain\"></select>
      </div>
      <div class=\"col-lg-5\">
        <div id=\"chart_div\"></div>
        <h5> select a subregion to plot:</h5>
        <select id=\"region\"></select>
      </div>
  </div>
  </div>

  <div role=\"tabpanel\" class=\"tab-pane\" id=\"two\"> 
    <div id=\"table_div\"></div>
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
        //sessPath = 'http://127.0.0.1/ttapp/tmp/$sessid/',
        geojsonPath = sessPath + 'ecosys_risk.geojson',
        geojsonURLs = [],
        aoiPath = sessPath + 'aoi.geojson',
        csvPath = '$pathid/habsummary.csv',
        symbPath = sessPath + 'legend.json',
        //initPath = sessPath + 'init.json',
        categoryField = 'cols', //This is the fieldname for marker category (used in the pie and legend)
        popupFields = [] //Popup will display these fields
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
    L.control.layers();
;


// add stuff to the map
map.addLayer(MapBoxSat);
//map.addLayer(pgons);

// overlays = {
//   "Habitats": pgons,
// };
// overlays = {};

//L.control.layers(base, overlays).addTo(map);

// Initialize legend
var legend = L.control({position: 'bottomright'});

// init default maplayer
var maplayer = 'ecosys_risk'

// load AOI geojson, add to map
// get subregion names from geojson properties, build dropdown select for subregions
var aoijson = {};
function drawAOI(){
  loadaoi = L.geoJson.ajax(aoiPath,{ // provide url to geojson
    middleware:function(data){ // do some function on the geojson data before returning it
        aoijson = data;
        var aoi = L.geoJson(aoijson,{ // build a leaflet layer with style parameters
          style: function(feature){
            return {
              fillColor: "orange",
              color: "white",
              fillOpacity:0.0,
              opacity:1,
              weight:1
            }
          }
          //onEachFeature: defineFeaturePopup
        });
        // add AOI to map
        map.addLayer(aoi);
        map.fitBounds(aoi.getBounds());

        // Build subregion dropdown select from geojson properties
        for (var i = 0; i <= aoijson.features.length - 1; i++) {
          $("#region").append("<option value='" + i + "'>" + aoijson.features[i].properties.name + "</option");
        }
         // set the default selection to the 1st feature 
        $("#region option[value='" + dropdown.indexOf(aoijson.features[1].properties.name) + "']").attr("selected","selected");
    }
  });
}
drawAOI(); // and it builds subregion dropdown



// load the visualization library from Google and set a listener
google.load("visualization", "1", {packages:["corechart"]});
//google.load("visualization", "1.1", {packages:["bar"]});
google.load("visualization", "1", {packages:["table"]});
google.setOnLoadCallback(drawChart);


//// A lot happens in here:
//// Read legend.json - build maplayer dropdown from legend elements; build legend div.
//// Read csv - build table and chart (chart responds to both dropdowns)
//// Read geoJSON (first with default layer, then in response to dropdown selection)
function drawChart() {

  function makeMap() {
    // load legend json
    $.getJSON(symbPath, function(symbols){
      console.log(symbols[0].layer);

      // build array of geojson urls to load all at once
      // build dropdown array with legend elements
      var pgons = [];
      var overlays = {};
      var dropdown = [];
      for (var i = 0; i < symbols.length; i++) {
        $("#domain").append("<option value='" + i + "'>" + symbols[i].layer + "</option");
        dropdown.push(symbols[i].layer)
        geojsonURLs.push(sessPath + symbols[i].layer + ".geojson")
      }
      // set the default selection 
      $("#domain option[value='" + dropdown.indexOf("ecosys_risk") + "']").attr("selected","selected");

      for (var j = 0; j < geojsonURLs.length; j++){
        var g1 = L.geoJson.ajax(geojsonURLs[j],{
          middleware:function(data){
            var g2 = L.geoJson(data,{
              style: function(feature){
                gridcolor = feature.properties.cols.replace("hex", "#");
                return {
                  fillColor:gridcolor,
                  color:gridcolor,
                  fillOpacity:0.7,
                  opacity:1,
                  weight:0.5
                }
              }
              //onEachFeature: defineFeaturePopup
            });
            var pgons = new L.featureGroup();
            pgons.addLayer(g2);
            map.addLayer(pgons);

            //overlays[dropdown[j]] = pgons;
            overlays["thing"] = "that";
            console.log(overlays);
            //map.fitBounds(markers.getBounds());
          }
        });

      }
      console.log(overlays);
      L.control.layers(base, overlays).addTo(map);
    })
  };
  makeMap();
  //   console.log(geojsonURLs);
  //   function LoadAllGeo(){
  //   for (var j = 0; j < geojsonURLs.length; j++){
  //     var g1 = L.geoJson.ajax(geojsonURLs[j],{
  //       middleware:function(data){
  //         var g2 = L.geoJson(data,{
  //           style: function(feature){
  //             gridcolor = feature.properties.cols.replace("hex", "#");
  //             return {
  //               fillColor:gridcolor,
  //               color:gridcolor,
  //               fillOpacity:0.7,
  //               opacity:1,
  //               weight:0.5
  //             }
  //           }
  //           //onEachFeature: defineFeaturePopup
  //         });
  //         pgons.addLayer(g2);
  //         map.fitBounds(markers.getBounds());
  //       }
  //     });
  //   }
  // };
  // LoadAllGeo();
    


  function makeLegend(){
    $.getJSON(symbPath, function(symbols){
      // build legend div based on maplayer (responds to dropdown selection)
      var leglayer = jQuery.grep(symbols, function(data) { 
        return data.layer == maplayer
      })

      legend.onAdd = function (map) {
      console.log(leglayer[0]);
      var div = L.DomUtil.create('div', 'info legend'),
          grades = leglayer[0]['leglabs'],
          cols = leglayer[0]['legcols'];
          labels=[];

      //loop through our intervals and generate a label with a colored square for each interval
      for (var i = 0; i < grades.length; i++) {
          div.innerHTML +=
              '<i style="background:' + cols[i] + '"></i> ' +
              grades[i] + '<br>';
      }
      return div;
      };
      legend.addTo(map);
    })
  };  
  makeLegend();

   // Load the CSV
  $.get(csvPath, function(csvString) {

    // Build table from csv to display as google vis

    // transform the CSV string into a 2-dimensional array
    var arrayData = $.csv.toArrays(csvString, {onParseValue: $.csv.hooks.castToScalar});
    //console.log(arrayData);

    // make DataTable from entire arrayData
    var data = new google.visualization.arrayToDataTable(arrayData);
    // link to div
    var table = new google.visualization.Table(document.getElementById('table_div'));
    // make a view with subset of data (in this case view still includes entire data array)
    var tableview = new google.visualization.DataView(data);

    // get the col numbers of all that should appear in table
    // var collist = [];
    // for (var i = 0; i <= arrayData[0].length - 1; i++) {
    //     collist.push(i);
    // }
    // tableview.setColumns(collist);

    table.draw(tableview, {showRowNumber: false, page: 'enable', pageSize:25});


    // Build Chart from same DataTable as above
    var chartview = new google.visualization.DataView(data);

    var subregion = $("#region option:selected").text(); // get selected subregion from dropdown
    // filter view by values of certain columns
    chartview.setRows(chartview.getFilteredRows([{column: 2, value: maplayer}, {column: 3, value: subregion}]));
    chartview.setColumns([0,1]);

    var options = {
       title: 'Distribution of Risk Scores',
       legend: { position: 'none' },
       colors: ['gray'],
    };

    //var chart = new google.charts.Bar(document.getElementById("chart_div"));
    var chart = new google.visualization.ColumnChart(document.getElementById("chart_div"));
    //var chart = new google.visualization.Histogram(document.getElementById('chart_div'));
    chart.draw(chartview, options);

    // feature popup function relies on this callback function
    function defineFeaturePopup(feature, layer) {
      //var props = feature.properties,
        //fields = popupFields,
       var popupContent = '',
        id = feature.properties.cellID,
        val = arrayData[id],
        label = arrayData[0],
        poptable = "<table class='table table-condensed'>";
        for (var i=0; i < val.length; i=i+1) {
          if (label[i] === maplayer){
            poptable += "<tr><td><b>" + label[i] + "</b></td>";  
            poptable += "<td><b>" + val[i] + "</b></td></tr>"; 
          } else {
            poptable += "<tr><td>" + label[i] + "</td>";  
            poptable += "<td>" + val[i] + "</td></tr>";  
          } 
        }
        //poptable += "<tr><td>" + "ID" + "</td><td>" + id + "</td></tr>"
        
      //popupContent = '<span class="attribute"><span class="label">'+label+':</span> '+val+'</span>';
      //popupContent = '<span class="attribute">'+poptable+'</span>';
      //console.log(popupContent);
      popupContent = '<div class="map-popup">'+ poptable +'</div>';
      layer.bindPopup(popupContent,{
        offset: L.point(0,10),
        maxHeight: 300
      });
    }

    //Ready to go, load the geojson
    // There are 2 almost identical load calls
    // This first one calls map.fitBounds
    // The second one doesn't fit the map view because that
    // one is called inside the select dropdown listener

    var geojsonFirst = L.geoJson.ajax(geojsonPath,{
      middleware:function(data){
          geojson = data;
            
          var markers = L.geoJson(geojson,{
            style: function(feature){
              gridcolor = feature.properties.cols.replace("hex", "#");
              return {
                fillColor:gridcolor,
                color:gridcolor,
                fillOpacity:0.7,
                opacity:1,
                weight:0.5
              }
            }
            //onEachFeature: defineFeaturePopup
          });
          pgons.addLayer(markers);
          map.fitBounds(markers.getBounds());
      }
    });

    // set listener for the subregion dropdown
    $("#region").change(function(){
      subregion = $("#region option:selected").text();
      chartview = new google.visualization.DataView(data);
        //console.log(chartview);

      chartview.setRows(chartview.getFilteredRows([{column: 2, value: maplayer}, {column: 3, value: subregion}]));
      chartview.setColumns([0,1]);

       // update the chart
      chart.draw(chartview, options);

    });

    // set listener for the maplayer dropdown, chart and map must respond
    $("#domain").change(function(){
      // get current selection
      maplayer = $("#domain option:selected").text();

      // reset chartview to entire dataTable
      chartview = new google.visualization.DataView(data);

      // filter rows by currently selected maplayer and subregion
      chartview.setRows(chartview.getFilteredRows([{column: 2, value: maplayer}, {column: 3, value: subregion}]));
      chartview.setColumns([0,1]);

      // redraw the chart
      chart.draw(chartview, options);

      // Linking dropdown to map
      popupFields = [];
      geojsonPath = sessPath + maplayer +'.geojson'; // build url to new maplayer
      // geojsonLayer.refresh(geojsonPath);//add a new layer 

      var geojsonLayer = L.geoJson.ajax(geojsonPath,{
        middleware:function(data){
          geojson = data;

            var markers = L.geoJson(geojson,{
              style: function(feature){
                gridcolor = feature.properties.cols.replace("hex", "#");
                return {
                  fillColor:gridcolor,
                  color:gridcolor,
                  fillOpacity:0.7,
                  opacity:1,
                  weight:0.5
                }
              }
              //onEachFeature: defineFeaturePopup
            });
            pgons.clearLayers(); 
            pgons.addLayer(markers);
        }
      });
      legend.removeFrom(map);
      makeLegend();
    });

 });
};  /// end of ajax call read csv

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