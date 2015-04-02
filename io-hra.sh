#!/bin/bash

## this will handle all the initial data input for the hra dashboard

## unzip workspace
cd tmp-hra/$1/
unzip ./workspace.zip -d .

## get name of workspace folder

cd $(find . -maxdepth 1 -not -name '.' -type d)
pwd
## call some gdal commands
## convert ecosystem risk raster to vector
gdal_calc.py -A output/Maps/ecosys_risk.tif --outfile=output/Maps/ecorisk_mult.tif --calc="A*10000"
gdal_polygonize.py output/Maps/ecorisk_mult.tif -f "ESRI Shapefile" output/Maps/ecosys_risk.shp ecosys_risk risk

## convert stressor rasters
cd ./intermediate/Stressor_Rasters
## excluding the buffered versions
find . -not -name '*buff.tif' -name '*.tif' |  while read line; do
  echo $line
  tiffname=$(basename "$line")
  nm="${tiffname%.*}"
  echo $nm
  shpnm="$nm".shp
  echo $shpnm
  gdal_polygonize.py "$line" -f "ESRI Shapefile" ../../output/Maps/$shpnm $nm $nm
done
  
## run some R code
#wsnm=$(find . -maxdepth 1 -not -name '.' -type d)
cd ../..
R -q --vanilla < /home/dfisher5/ttapp/io-hra.r | tee io.r.log | grep -e \"kadfkjalkjdfadijfaijdfkdfdsa\"