<?php
session_start();

///
/// version 2:  switch to php and incorporate fxns to upload files and batch the r code (SAW)
///  20141030
///
/// 
/// TODO: somewhere this code should probably cleanup old sessions
/// 


 //
 // TOP STUFF
 //

// set time zone
date_default_timezone_set('America/Los_Angeles');

// make a unique folder for each run
// // was using session (like in natcap docs autobuilder), then switched to datetime + who instead
$sessid = session_id();
$pathid = "./tmp/" . $sessid . "/";

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


    <link rel=\"stylesheet\" type=\"text/css\" href=\"./libs/Leaflet.markercluster-master/dist/MarkerCluster.Default.css\">
    <link rel=\"stylesheet\" type=\"text/css\" href=\"./libs/Leaflet.markercluster-master/dist/MarkerCluster.css\">
    <script src=\"./libs/Leaflet.markercluster-master/dist/leaflet.markercluster.js\"></script>



    <script src=\"http://d3js.org/d3.v3.min.js\" charset=\"utf-8\"></script>
    <script src=\"https://www.google.com/jsapi\"></script>

    <script type=\"text/javascript\" src=\"./libs/leaflet-ajax-master/dist/leaflet.ajax.js\"></script>
  </head>
  <body> ";

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

  <h3>Recreation Dashboard</h3>

  <ul class=\"nav nav-tabs\" role=\"tablist\" id=\"mytabs\">
    <li role=\"presentation\" class=\"active\"><a href=\"#upload\" aria-controls=\"upload\" role=\"tab\" data-toggle=\"tab\">Upload</a></li>
    <li role=\"presentation\"><a href=\"#one\" aria-controls=\"one\" role=\"tab\" data-toggle=\"tab\">Map</a></li>
    <li role=\"presentation\"><a href=\"#two\" aria-controls=\"two\" role=\"tab\" data-toggle=\"tab\">Table</a></li>
    <li role=\"presentation\"><a href=\"#three\" aria-controls=\"three\" role=\"tab\" data-toggle=\"tab\">About</a></li>
  </ul> ";

  echo "
  <div class=\"tab-content\">
  <div role=\"tabpanel\" class=\"tab-pane active\" id=\"upload\"> 
    <div id=formbody>
      <form enctype=\"multipart/form-data\" id=\"form1\" name=\"form1\" method=\"post\" action=\"$_SERVER[PHP_SELF]\" accept-charset=utf-8>
        <input type=hidden name=doit value=y>
        <br></br>
        <input name=\"logfile\" type=\"file\">
        <b>recreation_client-log-YYYY-MM-DD--HH_MM_SS.txt </b>
        <p>from 'YOUR WORKSPACE' / recreation_client-log-YYYY-MM-DD--HH_MM_SS.txt </p>

        <br></br>
        <input type=Submit name=junk value=\"Upload Results\">
      </form>
    </div>";
    if (isset($_SESSION["message"])) {
      echo "<div id=message>".$_SESSION["message"]."</div>";
      }
    unset($_SESSION["message"]);
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
      </div>
  </div>
  </div>

  <div role=\"tabpanel\" class=\"tab-pane\" id=\"two\"> 
    <button id=\"tablebutton\" title=\"Limit the table to display points currently visible on the map\">Query table by map view</button>
    <button id=\"tableselect\" title=\"Zoom the map to the row selected in the table \">Zoom To Point</button>
    <button id=\"tablereset\" title=\"Reset table to all rows \">Reset</button>
    <div id=\"table_div\"></div>
  </div>
  <div role=\"tabpanel\" class=\"tab-pane\" id=\"three\"> 

    <h3>About this application</h3>

    <p>This application allows an InVEST user to view a set of model results interactively in a web browser. 
    All the data displayed in this app come from the <em>grid.shp</em> shapefile in the results zip folder of an InVEST workspace.</p>
    <br>
    <p>The raw data from the grid.shp is viewable on the Table tab and on the Map at high zoom levels. 
    At lower zoom levels data-points are clustered together, and clicking a cluster reveals all the individual points.
    For a gridded AOI, grid cells are represented here by their centerpoint</p>
    <br>
    <p>Not all of the results produced by the Recreation model are displayed in this application.
    You may wish to explore and analyze your results further with GIS or data analysis software.</p>
    <br>
    <p>This application is built by the <a href=\"http://naturalcapitalproject.org\">Natural Capital Project</a>. The source code (R and javascript) is available and
    you are encouraged to submit bugs and feature requests at <a href=\"https://github.com/davemfish/ttapp-rec/issues\">https://github.com/davemfish/ttapp-rec/issues</a></p>

  </div>
  
  </div>
</div> ";

// <button onclick=\"Mapquery()\">Update</button>


// UPLOAD 
if (isset($_POST['doit']) & !empty($_FILES['logfile']['tmp_name'])) {
  // File Quality Control
  // // check for errors
  if ($_FILES["logfile"]["error"] > 0) {
    $_SESSION['message'] = "ERROR: " . $_FILES["logfile"]["error"];
    header("Location:rec-dash.php");
    die;   // THIS IS IMPORTANT, or else it continues running, launches the R script, etc!!
  }
  // // check mime type
//  $mimes = array('text/plain','text/csv');
//  if(!in_array($_FILES['expfile']['type'],$mimes)) {
//    $_SESSION['message'] =  "Input does not appear to be a csv file.";
//    header("Location:clusterpies.php");
//    die;
//  }
  // // // i don't know how to check a tif mime type

  // Report some things to screen
  echo "<p><b> starting session... </b><p>";
  echo "<pre>";
  echo "Session ID: $sessid";
  echo "   Path ID: $pathid";
  echo "</pre>";
  echo "<p><b> blah blah blah...</b><p>";
  flush();
  ob_flush();

  // set the time limit to XX seconds
  set_time_limit(300);

  // Create session directory and cd to it
  $sdir = "$pathid";
  if (!file_exists($sdir)) {
    echo "<pre>";
    //passthru("mkdir $pathid");
    echo $sdir;
    mkdir($pathid);
    echo "</pre>";
  }

  // Upload the tables
  echo "<p><b> uploading inputs... </b><p>";
  // // exposure table
  $outloadfile = $pathid . "rec_logfile.txt";
  if (move_uploaded_file($_FILES['logfile']['tmp_name'], $outloadfile)) {
    echo "logfile was successfully uploaded. <p>";
  } else {
    echo "logfile cannot be uploaded.";
    echo $outloadfile;
  }


  // Run local R script
  echo "<p><b> creating geojson files... </b><p>";
  flush();
  ob_flush();
  echo "<pre>";
  passthru("R -q --vanilla '--args sess=\"$sessid\"' < io-rec.r | tee io.r.log | grep -e \"^[^>+]\" -e \"^> ####\" -e \"QAQC:\" -e \"^ERROR:\" -e \"WARN:\"");  // -e "^ " -e "^\[" 
  echo "</pre>";
  flush();
  ob_flush();

  // after upload and R completes, switch to map tab
  echo "
  <script>
  console.log('switching?');
    $(function () {
      $('#mytabs a[href=\"#one\"]').tab('show')
  map.invalidateSize(false);
    })
  </script> ";
}


// If data already exists, map it yah!
if (file_exists($pathid . "rec_logfile.txt")) {

  echo "
    <script>

      // Define vars
      var geojson,
        metadata = [],
        sessPath = '$pathid'
        //sessPath = 'http://127.0.0.1/ttapp/tmp/$sessid/'
        geojsonPath = sessPath + 'usdyav.geojson',
        csvPath = '$pathid/grid.csv',
        symbPath = sessPath + 'legend.json'
        initPath = sessPath + 'init.json'
        categoryField = 'cols', //This is the fieldname for marker category (used in the pie and legend)
        //iconField = '5065', //This is the fieldame for marker icon
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

// load the init.json -- used to tell Leaflet if points/polys
var init = (function() {
        var init = null;
        $.ajax({
            'async': false,
            'global': false,
            'url': initPath,
            'dataType': "json",
            'success': function (data) {
                init = data;
            }
        });
        return init;
    })();

// is this page dealing with points or polygons?
var pointdata = null;
if (init["pts_poly"] === "points"){
  pointdata = true;
} else{
  pointdata = false;
}
      
var  rmax = 27, //Maximum radius for cluster pies
    noclusterzoom = 11, // this should somehow be a function of model resolution
    markerclusters = L.markerClusterGroup({
      maxClusterRadius: 1*rmax,
      iconCreateFunction: defineClusterIcon, //this is where the magic happens
      disableClusteringAtZoom: noclusterzoom
    });
    //pgons = new L.geoJson();
    pgons = new L.featureGroup();
    //markers = new L.featureGroup();
    

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

    L.control.layers();
;

// add stuff to the map

map.addLayer(OpenMapSurfer_Roads);
if (pointdata){
  map.addLayer(markerclusters);
  overlays = {
        "Markers": markerclusters,
  };
} else {
  map.addLayer(pgons)
  overlays = {
        "Markers": pgons,
  };
}

L.control.layers(base, overlays).addTo(map);

// Initialize legend
var legend = L.control({position: 'bottomright'});
//legend.addTo(map);

//var leg = {};
var maplayer = 'usdyav'

function makeLegend(){
     // update map legend
   // load legend metadata
  $.getJSON(symbPath, function(symbols){
    console.log(symbols);
    var leglayer = jQuery.grep(symbols, function(data) { 
      return data.layer == maplayer
    })
    //console.log(leg);
    //makeLegend(leg[0]);
    legend.onAdd = function (map) {
    console.log(leglayer[0]);
    var div = L.DomUtil.create('div', 'info legend'),
        grades = leglayer[0]['leglabs'],
        cols = leglayer[0]['legcols'];
        labels=[];

    //loop through our density intervals and generate a label with a colored square for each interval
    for (var i = 0; i < grades.length; i++) {
        div.innerHTML +=
            '<i style="background:' + cols[i] + '"></i> ' +
            grades[i] + '<br>';
            // grades[i] + (grades[i + 1] ? '&ndash;' + grades[i + 1] + '<br>' : '+');
    }
    //console.log(labels);
    return div;
    };
    legend.addTo(map);
  })
};
  //console.log(leg);
  
makeLegend();

// load the visualization library from Google and set a listener
google.load("visualization", "1", {packages:["corechart"]});
google.load("visualization", "1", {packages:["table"]});
google.setOnLoadCallback(drawChart);
// wait till the DOM is loaded


//// A lot happens in here because this is the ajax call that reads the csv
//// anything relying on the csv data must go in here
function drawChart() {
   // grab the CSV
   $.get(csvPath, function(csvString) {

      // transform the CSV string into a 2-dimensional array
      var arrayData = $.csv.toArrays(csvString, {onParseValue: $.csv.hooks.castToScalar});
      
      // use arrayData to load the select dropdown with the appropriate options
      // first remove cellArea from the array of columns
      var cellarea = arrayData[0] .indexOf("cellArea");

      for (var i = 0; i < arrayData[0].length; i++) {
        if (i === cellarea){
          continue;
        }
      // this adds the given option to select element
        $("select").append("<option value='" + i + "'>" + arrayData[0][i] + "</option");
      }
      // get the index of the average user-days column to use in default plot
      var colnum = arrayData[0].indexOf('usdyav');
      // get the col numbers of all that should appear in table
      var collist = [];
      for (var i = 0; i <= arrayData[0].length - 1; i++) {
          collist.push(i);
      }
      //var ar = [0];
      //collist = ar.concat(collist); 
      // set the default selection
      // $("#range option[value='0']").attr("selected","selected");
      $("#domain option[value='" + colnum + "']").attr("selected","selected");
      //console.log(arrayData[0]);

      // this new DataTable object holds all the data
      var data = new google.visualization.arrayToDataTable(arrayData);
      console.log(arrayData[1]);

      var table = new google.visualization.Table(document.getElementById('table_div'));

      var tableview = new google.visualization.DataView(data);

      $("#tablereset").click(function () {
        var tableview = new google.visualization.DataView(data);
        tableview.setColumns(collist);
        table.draw(tableview, {showRowNumber: false, page: 'enable', pageSize:25});
      });

      $("#tablebutton").click(function () {
        // For each marker, consider whether it is currently visible by comparing
        // with the current map bounds.
        bounds = map.getBounds();
        var inBounds = [];

        if (pointdata){
          datalayer = markerclusters;
          datalayer.eachLayer(function(marker) {
              if (bounds.contains(marker.getLatLng())) {
                console.log(marker);
                  inBounds.push(marker.feature.id);
              }
          });
        } else { // need a different function here because polygons have diff .getLatLng() method
          datalayer = pgons.getLayers()[0]; // see comments under tableselect below
          datalayer.eachLayer(function(marker) {
              if (bounds.contains(marker.getLatLngs())) {
                console.log(marker);
                  inBounds.push(marker.feature.id);
              }
          });
        }

          tableview.setColumns(collist);
          tableview.setRows(inBounds);
          table.draw(tableview, {showRowNumber: false, page: 'enable', pageSize:25});
          console.log(inBounds);
      });

      $("#tableselect").click(function () {
        //console.log(geojsonLayer);
        //bounds = map.getBounds();
        var selrows = table.getSelection();
        if (selrows.length === 0){
          alert("no rows are selected");
        } else if (selrows.length === 1){
          // from underlying tableview, get feature cellID which is in selected row, col 0
          var ptid = tableview.getValue(selrows[0]["row"], 0);

          console.log(ptid);
          $('#mytabs a[href="#one"]').tab('show')

          if (pointdata){
            datalayer = markerclusters;
          } else {
            // this is messy, somehow loading the geojson polygons results in some 
            // weird nested layers, where pgons is parent, contains only 1 layer,
            // which contains layers for each feature/polygon.
            datalayer = pgons.getLayers()[0]; // so grab that first child layer
          }

          datalayer.eachLayer(function(marker) { 
            //console.log(marker);
            if (marker["feature"]["properties"]["cellID"] === ptid){ 
              
              if (pointdata){
                map.panTo(marker.getLatLng());
              } else {
                map.panTo(marker.getLatLngs()[0]);
              }
              
              //map.fitBounds(marker.getBounds());
              setTimeout(function() {
                map.setZoom(noclusterzoom);

                setTimeout(function() {
                  marker.openPopup();
                }, 500);
              }, 500);
              
            }
          });

        } else {
          alert("select only 1 row at a time");

        }
      });

      tableview.setColumns(collist);

      table.draw(tableview, {showRowNumber: false, page: 'enable', pageSize:25});

      // this view can select a subset of the data at a time
      var chartview = new google.visualization.DataView(data);

      chartview.setColumns([colnum]);
      //console.log(view);

      var options = {
         title: 'Distribution of mapped layer',
         legend: { position: 'none' },
         colors: ['gray'],
      };

      var chart = new google.visualization.Histogram(document.getElementById('chart_div'));
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
        layer.bindPopup(popupContent,{offset: L.point(-3,-2)});
      }

      //Ready to go, load the geojson
      // There are 2 almost identical load calls
      // This first one calls map.fitBounds
      // The second one doesn't fit the map view because that
      // one is called inside the select dropdown listener

      var geojsonFirst = L.geoJson.ajax(geojsonPath,{
        middleware:function(data){
            geojson = data;

            if (pointdata){
              var markers = L.geoJson(geojson, {
                pointToLayer: defineFeature,
                onEachFeature: defineFeaturePopup
              });

              markerclusters.addLayer(markers);
            } else {
              /// results of below are slightly different in structure than above
              /// probably because we can't call 'pointToLayer' with polygons
              /// result is an object pgons that contains 1 layer that contains
              /// all the features/cells * see above tablebutton for more.
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
                },
                onEachFeature: defineFeaturePopup
              });
              pgons.addLayer(markers);
            }
            map.fitBounds(markers.getBounds());
        }
      });



      // set listener for the update button
      $("select").change(function(){
         // determine selected domain and range
         var domain = +$("#domain option:selected").val();
         // var range = +$("#range option:selected").val();
         // update the view
         chartview.setColumns([domain]);

         // update the chart
         chart.draw(chartview, options);

         // update markers on map
         popupFields = [];
         markerclusters.clearLayers();
         maplayer = $("#domain option:selected").text();
         geojsonPath = sessPath + maplayer +'.geojson';
         // geojsonLayer.refresh(geojsonPath);//add a new layer 

        var geojsonLayer = L.geoJson.ajax(geojsonPath,{
                middleware:function(data){
                    geojson = data;

                    if (pointdata){
                      var markers = L.geoJson(geojson, {
                        pointToLayer: defineFeature,
                        onEachFeature: defineFeaturePopup
                      });

                      markerclusters.addLayer(markers);
                    } else {
                      /// results of below are slightly different in structure than above
                      /// probably because we can't call 'pointToLayer' with polygons
                      /// result is an object pgons that contains 1 layer that contains
                      /// all the features/cells * see above tablebutton for more.
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
                        },
                        onEachFeature: defineFeaturePopup
                      });
                      pgons.clearLayers(); 
                      pgons.addLayer(markers);
                    }
                }
              });
        legend.removeFrom(map);
        makeLegend();
        //legend.addTo(map);
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


function defineClusterIcon(cluster) {
  var children = cluster.getAllChildMarkers(),
      n = children.length, //Get number of markers in cluster
      strokeWidth = 1, //Set clusterpie stroke width
      r = rmax-2*strokeWidth-(n<10?14:n<100?13:n<1000?12:10), //Calculate clusterpie radius...
      iconDim = (r+strokeWidth)*2, //...and divIcon dimensions (leaflet really want to know the size)
      data = d3.nest() //Build a dataset for the pie chart
        .key(function(d) { return d.feature.properties[categoryField]; })
        .entries(children, d3.map),
      //bake some svg markup
      html = bakeThePie({data: data,
                          valueFunc: function(d){return d.values.length;},
                          strokeWidth: 1,
                          outerRadius: r,
                          innerRadius: r-9,
                          pieClass: 'cluster-pie',
                          pieLabel: n,
                          pieLabelClass: 'marker-cluster-pie-label',
                          pathClassFunc: function(d){return "category-"+d.data.key;}
                          // pathTitleFunc: function(d){return metadata.fields[categoryField].lookup[d.data.key]+' ('+d.data.values.length+' accident'+(d.data.values.length!=1?'s':'')+')';}
                        }),
      //Create a new divIcon and assign the svg markup to the html property
      myIcon = new L.DivIcon({
          html: html,
          className: 'marker-cluster', 
          iconSize: new L.Point(iconDim, iconDim)
      });
        //console.log(myIcon);
        //console.log(d.feature.properties[categoryField]);
  return myIcon;
}

/*function that generates a svg markup for the pie chart*/
function bakeThePie(options) {
    /*data and valueFunc are required*/
    if (!options.data || !options.valueFunc) {
        return '';
    }
    var data = options.data,
        valueFunc = options.valueFunc,
        r = options.outerRadius?options.outerRadius:28, //Default outer radius = 28px
        rInner = options.innerRadius?options.innerRadius:r-10, //Default inner radius = r-10
        strokeWidth = options.strokeWidth?options.strokeWidth:1, //Default stroke is 1
        pathClassFunc = options.pathClassFunc?options.pathClassFunc:function(){return '';}, //Class for each path
        //pathTitleFunc = options.pathTitleFunc?options.pathTitleFunc:function(){return '';}, //Title for each path
        pieClass = options.pieClass?options.pieClass:'marker-cluster-pie', //Class for the whole pie
        pieLabel = options.pieLabel?options.pieLabel:d3.sum(data,valueFunc), //Label for the whole pie
        pieLabelClass = options.pieLabelClass?options.pieLabelClass:'marker-cluster-pie-label',//Class for the pie label
        
        origo = (r+strokeWidth), //Center coordinate
        w = origo*2, //width and height of the svg element
        h = w,
        donut = d3.layout.pie(),
        arc = d3.svg.arc().innerRadius(rInner).outerRadius(r);
        
    //Create an svg element
    var svg = document.createElementNS(d3.ns.prefix.svg, 'svg');
    //Create the pie chart
    var vis = d3.select(svg)
        .data([data])
        .attr('class', pieClass)
        .attr('width', w)
        .attr('height', h);
        
    var arcs = vis.selectAll('g.arc')
        .data(donut.value(valueFunc))
        .enter().append('svg:g')
        .attr('class', 'arc')
        .attr('transform', 'translate(' + origo + ',' + origo + ')');
    
    arcs.append('svg:path')
        .attr('class', pathClassFunc)
        .attr('stroke-width', strokeWidth)
        .attr('d', arc);
        // .append('svg:title')
        //   .text(pathTitleFunc);
                
    // vis.append('text')
    //     .attr('x',origo)
    //     .attr('y',origo)
    //     .attr('class', pieLabelClass)
    //     .attr('text-anchor', 'middle')
    //     //.attr('dominant-baseline', 'central')
    //     /*IE doesn't seem to support dominant-baseline, but setting dy to .3em does the trick*/
    //     .attr('dy','.3em')
    //     .text(pieLabel);
    //Return the svg-markup rather than the actual element
    return serializeXmlNode(svg);
}


/*Helper function*/
function serializeXmlNode(xmlNode) {
    if (typeof window.XMLSerializer != "undefined") {
        return (new window.XMLSerializer()).serializeToString(xmlNode);
    } else if (typeof xmlNode.xml != "undefined") {
        return xmlNode.xml;
    }
    return "";
}
  
 
</script>

    <!-- Google Charts stuff below -->

   


<?php

} else {

  echo "
  <script>
    $(function () {
      $('#mytabs a:first').tab('show')
    })
  </script> ";
  //
  //  FORM SCREEN
  //

  // echo "
  // <div id=formbody>
  //   <form enctype=\"multipart/form-data\" id=\"form1\" name=\"form1\" method=\"post\" action=\"$_SERVER[PHP_SELF]\" accept-charset=utf-8>
  //     <input type=hidden name=doit value=y>
  //     <p><b>coastal_exposure.csv from CV outputs:</b><input name=\"expfile\" type=\"file\">
  //     <p><b>00_PRE_aoi.tif from CV intermediate:</b><input name=\"aoifile\" type=\"file\">
  //     <p><input type=Submit name=junk value=\"yah go for it\">
  //   </form>
  // </div> ";

}



 //
 // BOTTOM BUSINESS
 //

echo "

  </body>
</html> ";

?>