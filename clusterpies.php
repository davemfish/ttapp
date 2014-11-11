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
    <script src=\"http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.js\"></script> 
    <link rel=\"stylesheet\" href=\"http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.css\" />
    <link rel=\"stylesheet\" href=\"clusterpies.css\">

    <link rel=\"stylesheet\" type=\"text/css\" href=\"https://cdn.rawgit.com/Leaflet/Leaflet.markercluster/v0.4.0/dist/MarkerCluster.Default.css\">
    <link rel=\"stylesheet\" type=\"text/css\" href=\"https://cdn.rawgit.com/Leaflet/Leaflet.markercluster/v0.4.0/dist/MarkerCluster.css\">
    <script src=\"https://cdn.rawgit.com/Leaflet/Leaflet.markercluster/v0.4.0/dist/leaflet.markercluster.js\"></script>
    <script src=\"http://d3js.org/d3.v3.min.js\" charset=\"utf-8\"></script>

    <script src=\"https://www.google.com/jsapi\"></script>
    <script src=\"http://code.jquery.com/jquery-1.10.1.min.js\"></script>
    <script src=\"../jquery.csv-0.71.js\"></script>
  </head>
  <body> ";


 //
 // MESSAGE BAR
 //

if (isset($_SESSION["message"])) {
  echo "<hr><div id=message>".$_SESSION["message"]."</div><hr>";
}
unset($_SESSION["message"]);


 //
 // MAP SCREEN
 //

// UPLOAD 
if (isset($_POST['doit']) & !empty($_FILES['expfile']['tmp_name']) & !empty($_FILES['aoifile']['tmp_name'])) {
  // File Quality Control
  // // check for errors
  if ($_FILES["expfile"]["error"] > 0) {
    $_SESSION['message'] = "ERROR: " . $_FILES["expfile"]["error"];
    header("Location:clusterpies.php");
    die;   // THIS IS IMPORTANT, or else it continues running, launches the R script, etc!!
  }
  if ($_FILES["aoifile"]["error"] > 0) {
    $_SESSION['message'] = "ERROR: " . $_FILES["aoifile"]["error"];
    header("Location:clusterpies.php");
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
    // passthru("mkdir $pathid");
    echo $sdir;
    mkdir($pathid);
    echo "</pre>";
  }

  // Upload the tables
  echo "<p><b> uploading inputs... </b><p>";
  // // exposure table
  $outloadfile = $pathid . "coastal_exposure.csv";
  if (move_uploaded_file($_FILES['expfile']['tmp_name'], $outloadfile)) {
    echo "exposure table was successfully uploaded. <p>";
  } else {
    echo "exposure table cannot be uploaded.";
    echo $outloadfile;
  }
  // // aoi raster
  $outloadfile = $pathid . "00_PRE_aoi.tif";
  if (move_uploaded_file($_FILES['aoifile']['tmp_name'], $outloadfile)) {
    echo "aoi raster was successfully uploaded. <p>";
  } else {
    echo "aoi raster cannot be uploaded.";
  }

  // Run local R script
  echo "<p><b> creating geojson files... </b><p>";
  flush();
  ob_flush();
  echo "<pre>";
  passthru("R -q --vanilla '--args sess=\"$sessid\"' < io.r | tee io.r.log | grep -e \"^[^>+]\" -e \"^> ####\" -e \"QAQC:\" -e \"^ERROR:\" -e \"WARN:\"");  // -e "^ " -e "^\[" 
  echo "</pre>";
  flush();
  ob_flush();
}


// If data already exists, map it yah!
if (file_exists($pathid . "coastal_exposure.csv") & file_exists($pathid . "00_PRE_aoi.tif")) {

    echo "
    <div id=\"map\"></div>
    <script>

      // Define vars
      var geojson,
        metadata = [],
        geojsonPath = 'http://127.0.0.1/ttapp/tmp/$sessid/coastal_exposure.geojson',
        csvPath = '$pathid/coastal_exposure.csv',
        categoryField = 'cols', //This is the fieldname for marker category (used in the pie and legend)
        //iconField = '5065', //This is the fieldame for marker icon
        popupFields = [], //Popup will display these fields
        tileServer = 'https://a.tiles.mapbox.com/v3/geointerest.map-dqz2pa8r/{z}/{x}/{y}.png',
        tileAttribution = 'Map data &copy; <a href=\"http://openstreetmap.org\">OpenStreetMap</a> contributors, <a href=\"http://creativecommons.org/licenses/by-sa/2.0/\">CC-BY-SA</a>, Imagery © <a href=\"http://mapbox.com\">Mapbox</a>',
        rmax = 27, //Maximum radius for cluster pies
        markerclusters = L.markerClusterGroup({
          maxClusterRadius: 1*rmax,
          iconCreateFunction: defineClusterIcon //this is where the magic happens
        });
  ";

?>

      var map = L.map('map', {
        center: [30.505, -90],
        zoom: 7
      });

      //Add basemap
      L.tileLayer(tileServer, {attribution: tileAttribution,  maxZoom: 15}).addTo(map);
      //and the empty markercluster layer
      map.addLayer(markerclusters);

      //Ready to go, load the geojson
      d3.json(geojsonPath, function(error, data) {
          if (!error) {
              geojson = data;
              // feats = data.features;
              // //var metadata
              // for (var i = 0; i < feats.length; i++){
              //   metadata.push(feats[i].properties);
              // }
              
              popupFields.push(Object.keys(geojson.features[0].properties)); //Popup will display these fields
              console.log(popupFields);
              // feature popup function relies on this callback function
              function defineFeaturePopup(feature, layer) {
                var props = feature.properties,
                  fields = popupFields,
                  popupContent = '';
//console.log(popupFields[0]);
                  //console.log(props[popupFields[0]]);
                  
                popupFields[0].map( function(key) {
                  if (props[key]) {
                    var val = props[key],
                      //label = fields[key].name;
                      label = key;
                    // if (fields[key].lookup) {
                    //   val = fields[key].lookup[val];
                    // }
                    popupContent += '<span class="attribute"><span class="label">'+label+':</span> '+val+'</span>';
                  }
                });
                popupContent = '<div class="map-popup">'+popupContent+'</div>';
                layer.bindPopup(popupContent,{offset: L.point(1,-2)});
              }

              var markers = L.geoJson(geojson, {
                pointToLayer: defineFeature,
                onEachFeature: defineFeaturePopup
                    });

              markerclusters.addLayer(markers);
              map.fitBounds(markers.getBounds());
              //map.attributionControl.addAttribution(metadata.attribution);
              //renderLegend();
          } else {
        console.log('Could not load data...');
          }
      });

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

      // function renderLegend() {
      //   var data = d3.entries(metadata.fields[categoryField].lookup),
      //     legenddiv = d3.select('body').append('div')
      //       .attr('id','legend');
            
      //   var heading = legenddiv.append('div')
      //       .classed('legendheading', true)
      //       .text(metadata.fields[categoryField].name);

      //   var legenditems = legenddiv.selectAll('.legenditem')
      //       .data(data);
            
      //   legenditems
      //       .enter()
      //       .append('div')
      //       .attr('class',function(d){return 'category-'+d.key;})
      //       .classed({'legenditem': true})
      //       .text(function(d){return d.value;});
      // }

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

   <h4> select a layer:</h4>
   <select id="domain">
   </select>
   <div id="chart_div" style="width: 900px; height: 500px;"></div>

    <script>

      // load the visualization library from Google and set a listener
      google.load("visualization", "1", {packages:["corechart"]});
      google.setOnLoadCallback(drawChart);
      // wait till the DOM is loaded
      
      function drawChart() {
         // grab the CSV
         $.get(csvPath, function(csvString) {

            // transform the CSV string into a 2-dimensional array
            var arrayData = $.csv.toArrays(csvString, {onParseValue: $.csv.hooks.castToScalar});
            // use arrayData to load the select elements with the appropriate options
            for (var i = 0; i < arrayData[0].length; i++) {
            // this adds the given option to both select elements
            $("select").append("<option value='" + i + "'>" + arrayData[0][i] + "</option");
            }
            // get the index of the coastal_exposure column to use in default plot
            var colnum = arrayData[0].indexOf('coastal_exposure');
            // set the default selection
            // $("#range option[value='0']").attr("selected","selected");
            $("#domain option[value='" + colnum + "']").attr("selected","selected");
            //console.log(arrayData[0]);

            // this new DataTable object holds all the data
            var data = new google.visualization.arrayToDataTable(arrayData);

            // this view can select a subset of the data at a time
            var view = new google.visualization.DataView(data);

            view.setColumns([colnum]);
            //console.log(view);

            var options = {
               title: 'Distribution of Vulnerability',
               legend: { position: 'none' },
               colors: ['gray'],
            };

            var chart = new google.visualization.Histogram(document.getElementById('chart_div'));
            chart.draw(view, options);

            // set listener for the update button
            $("select").change(function(){
               // determine selected domain and range
               var domain = +$("#domain option:selected").val();
               // var range = +$("#range option:selected").val();
               // update the view
               view.setColumns([domain]);

               // update the chart
               chart.draw(view, options);

               // re-set geojsonpath and reload map.
            });

         });
      };

   </script>


<?php

} else {

  //
  //  FORM SCREEN
  //

  echo "
  <div id=formbody>
    <form enctype=\"multipart/form-data\" id=\"form1\" name=\"form1\" method=\"post\" action=\"$_SERVER[PHP_SELF]\">
      <input type=hidden name=doit value=y>
      <p><b>coastal_exposure.csv from CV outputs:</b><input name=\"expfile\" type=\"file\">
      <p><b>00_PRE_aoi.tif from CV intermediate:</b><input name=\"aoifile\" type=\"file\">
      <p><input type=Submit name=junk value=\"yah go for it\">
    </form>
  </div> ";

}



 //
 // BOTTOM BUSINESS
 //

echo "
  </body>
</html> ";

 ?>
