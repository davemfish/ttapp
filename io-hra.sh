#!/bin/bash

## this will handle all the initial data input for the hra dashboard

appdir=$(pwd)

## unzip workspace
cd tmp-hra/$1/
unzip ./workspace.zip -d .

## get name of workspace folder and cd to it
cd $(find . -maxdepth 1 -not -name '.' -type d)

## check for uppercase dir names and lowercase them
## (HRA 3.0 had caps, 3.1 is all lowercase)
## http://stackoverflow.com/questions/17180580/how-to-create-a-bash-script-that-will-lower-case-all-files-in-the-current-folder
for file in *
do
    #Check to see if the filename contains any uppercase characters
    iscap=`echo $file | awk '{if ($0 ~ /[[:upper:]]/) print }'`
    if [[ -n $iscap ]]
    then
    #If the filename contains upper case characters convert them to lower case
        newname=`echo $file | tr '[A-Z]' '[a-z]'` #make lower case
    #Rename file
        echo "Moving $file\n To $newname\n\n"
        mv $file $newname
    fi
done

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
R -q --vanilla < $appdir/io-hra.r | tee io.r.log | grep -e \"kadfkjalkjdfadijfaijdfkdfdsa\"
