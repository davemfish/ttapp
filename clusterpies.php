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
    <!--<script type=\"text/javascript\" src=\"https://rawgit.com/calvinmetcalf/leaflet-ajax/master/dist/leaflet.ajax.min.js\"></script>-->
    <!--<script src=\"leaflet-ajax-master/dist/leaflet.ajax.min.js\"></script>-->
    <!--<script src=\"https://code.jquery.com/jquery-2.1.1.js\"></script>-->
    <script src=\"http://d3js.org/d3.v3.min.js\" charset=\"utf-8\"></script>

    <!--<link rel=\"points\" type=\"application/json\" href=\"http://localhost/data/coastal_exposure.geojson\">-->
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
  echo "Path ID: $pathid";
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
    passthru("mkdir $pathid");
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
        geojsonPath = 'http://vulpes.sefs.uw.edu/ttapp/tmp/$sessid/coastal_exposure.geojson',
        categoryField = 'cols', //This is the fieldname for marker category (used in the pie and legend)
        //iconField = '5065', //This is the fieldame for marker icon
        popupFields = ['coastal_exposure'], //Popup will display these fields
        tileServer = 'https://a.tiles.mapbox.com/v3/geointerest.map-dqz2pa8r/{z}/{x}/{y}.png',
        tileAttribution = 'Map data &copy; <a href=\"http://openstreetmap.org\">OpenStreetMap</a> contributors, <a href=\"http://creativecommons.org/licenses/by-sa/2.0/\">CC-BY-SA</a>, Imagery © <a href=\"http://mapbox.com\">Mapbox</a>',
        rmax = 30, //Maximum radius for cluster pies
        markerclusters = L.markerClusterGroup({
          maxClusterRadius: 2*rmax,
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
              //console.log(data.features.properties);
              var markers = L.geoJson(geojson, {
          pointToLayer: defineFeature
          //onEachFeature: defineFeaturePopup
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

      // function defineFeaturePopup(feature, layer) {
      //   var props = feature.properties,
      //     fields = metadata.fields,
      //     popupContent = '';
          
      //   popupFields.map( function(key) {
      //     if (props[key]) {
      //       var val = props[key],
      //         label = fields[key].name;
      //       if (fields[key].lookup) {
      //         val = fields[key].lookup[val];
      //       }
      //       popupContent += '<span class="attribute"><span class="label">'+label+':</span> '+val+'</span>';
      //     }
      //   });
      //   popupContent = '<div class="map-popup">'+popupContent+'</div>';
      //   layer.bindPopup(popupContent,{offset: L.point(1,-2)});
      // }

      function defineClusterIcon(cluster) {
        var children = cluster.getAllChildMarkers(),
            n = children.length, //Get number of markers in cluster
            strokeWidth = 1, //Set clusterpie stroke width
            r = rmax-2*strokeWidth-(n<10?12:n<100?8:n<1000?4:0), //Calculate clusterpie radius...
            iconDim = (r+strokeWidth)*2, //...and divIcon dimensions (leaflet really want to know the size)
            data = d3.nest() //Build a dataset for the pie chart
              .key(function(d) { return d.feature.properties[categoryField]; })
              .entries(children, d3.map),
            //bake some svg markup
            html = bakeThePie({data: data,
                                valueFunc: function(d){return d.values.length;},
                                strokeWidth: 1,
                                outerRadius: r,
                                innerRadius: r-10,
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
                      
          vis.append('text')
              .attr('x',origo)
              .attr('y',origo)
              .attr('class', pieLabelClass)
              .attr('text-anchor', 'middle')
              //.attr('dominant-baseline', 'central')
              /*IE doesn't seem to support dominant-baseline, but setting dy to .3em does the trick*/
              .attr('dy','.3em')
              .text(pieLabel);
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
      
      // L.tileLayer('https://a.tiles.mapbox.com/v3/geointerest.map-dqz2pa8r/{z}/{x}/{y}.png', {
      // attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, Imagery © <a href="http://mapbox.com">Mapbox</a>',
      // maxZoom: 18
      // }).addTo(map);

      //mbtiles.addTo(map);

      // var CVstyle = {
      //   "color": 'feature.properties.col',
      //   "weight": 5,
      //   "opacity": 0.65
      // };

     

      //var coastal_exposure = L.geoJson.ajax("http://localhost/data/coastal_exposure.geojson")
      // coastal_exposure.refilter(function(feature){
      //   return L.CircleMarker(latlng, {
      //         radius: 4,
      //         fillColor: 'white',    
      //         color: '#000',
      //         weight: 1,
      //         fillOpacity: 0.8
      //       })
      // });

      // $.getJSON("http://localhost/data/coastal_exposure.geojson", function(data) {
      //   var clusters = new L.MarkerClusterGroup({
      //     maxClusterRadius: 40
      //     iconCreateFunction: function (cluster) {
      //       //return L.divIcon({ html: cluster.getChildCount(), className: 'mycluster', iconSize: L.point(40, 40) });
      //       var children = cluster.getAllChildMarkers();
      //       var n = 0;
      //       for (var i = 0; i < markers.length; i++) {
      //         n += markers[i].number;
      //       }
      //       //var val = n / markers.length
      //       return L.divIcon({ html: n, className: 'mycluster', iconSize: L.point(40, 40) });
      // },
      //   });
      //   var points = new L.geoJson(data.features, {
      //     pointToLayer: function (feature, latlng){
      //       var marker = new L.CircleMarker(latlng, {
      //         radius: 4,
      //         fillColor: feature.properties.cols || 'white',    
      //         color: '#000',
      //         weight: 1,
      //         fillOpacity: 0.8
      //       });
      //       clusters.addLayer(marker);
      //       return clusters;
      //     }
      //   }).addTo(map);
      // });

      
      // var map = L.map('map').fitBounds(coastal_exposure.getBounds());
      
      //coastal_exposure.addTo(map);

      //var CVgrid = new L.LayerGroup();
      //var markers = new L.MarkerClusterGroup();

      //markers.addLayer(coastal_exposure);
      //map.addLayer(markers);

      //CVgrid.addTo(map);
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
