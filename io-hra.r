## Data flow for HRA-dash:
## client upload zip to server
## server unzip with php
## server run some gdal commands to convert tif to shp:

### For gdal_calc, '0' is interpreted as NoData and is left out of 
### the subsequent polygons. This is desired behavior for ecosys_risk.tif, 
### maybe not for other tifs?
### For gdal_polygonize, it dissolves.

#   gdal_calc -A ecosys_risk.tif --outfile=ecorisk_mult.tif --calc="A*10000"    
#   gdal_polygonize ecorisk_mult.tif -f "ESRI Shapefile" ecosys_risk.shp ecosys_risk risk

## values in ecosys_risk shp will need be divided by 1000 to get original float vals.
## server run this R script to read shp, style, and write geojson

# subregions shp is not available among outputs.

library(raster)
library(rgeos)
library(rgdal)
library(RColorBrewer)
library(RJSONIO)

Cut2Num <- function(x){
  ids <- unique(as.numeric(x))
  char.x <- as.character(levels(x))
  num.x <- as.numeric(gsub(unlist(strsplit(char.x, split=",")), pattern='\\(|\\[|\\)|\\]', replacement=""))
  return(list(brks=unique(num.x), ids=ids))
}


## Build a table holding all values from these layers at each point (csv with 300,000+ rows).
# but the polygonized tifs don't have pgons for each cell. 

## On click of ecosys_risk, what info to return?
# val of ecosys_risk
# Val of cumulative habitat risk for all hab layers present
# list of stressors present

## Then symbolize based on entire ranges of variables.

## SYMBOLIZE:

## habitat risk shps - categorical
#vals 1,2,3

## OR ## - the habitat risk shps above are just classified version of the cum. risk tifs below.

## cumulative risk by habitat - continuous
# min=0, 
# max= max # of overlapping stressors - can get this from logfile.

## ecosystem risk - continuous 
# min=0
# max= # of stressors * # of habitats?? - this layer is the sum of all cum risk by hab layers.
# divide by constant 10,000 to get the original values back.

## recovery potential by habitat

LoadSpace <- function(ws, outpath){
  #ws <- "C:/Users/dfisher5/Documents/Shiny/HRA/data"
  shps <- list.files(file.path(ws, "output/Maps"), pattern="*.shp$")
  mapdatalist <- list()
  leg.list <- list()
  
  for (j in 1:length(shps)){
    
    nm <- sub(pattern=".shp", replacement="", shps[j])
    print("read shp")
    shp.prj <- readOGR(dsn=file.path(ws, "output/Maps"), layer=nm)
    shp.wgs84 <- spTransform(shp.prj, CRS("+proj=longlat +datum=WGS84 +no_defs"))
    
    if (nm == "ecosys_risk"){
      ## back transform risk scores
      shp.wgs84@data$risk <- shp.wgs84@data$risk/10000
      dat <- shp.wgs84@data$risk
      
      ## break into equal intervals
      numcols <- 7
      brks <- seq(from=floor(min(dat)), to=ceiling(max(dat)), length.out=numcols)
      cats <- cut(dat, brks, right=F, include.lowest=T)
      
      ## assign colors
      cols <- brewer.pal(numcols, "YlOrRd")[as.numeric(cats)]
      
      ## get breakpoints that actually exist in the set to use for legend
      brks.list <- Cut2Num(cats) 
      num.brks <- brks.list[["brks"]] 
      legbrks <- round(num.brks, digits=3) 
      #legbrks[1] <- 0
      ## get indices of the colors that actually exist in the set to use for legend
      ids <- brks.list[["ids"]]
      ids <- ids[order(ids)]
      #ids <- c(ids)
      #legbrks <- legbrks[ids]
      
      ## build legend list for json
      if (length(ids) > 1){
        leglabs <- list()
        for (i in 2:length(legbrks)){
          #         if (i == 1) { 
          #           leglabs[[i]] <- legbrks[i] 
          #         } else {
          leglabs[[i-1]] <- paste(legbrks[i-1], "-", legbrks[i])
          #        }
        }
        legcols <- brewer.pal(numcols, "YlOrRd")[ids]
        leg.list[[j]] <- list(layer=nm, leglabs=unlist(leglabs[ids]), legcols=legcols)
      } else {
        legcols <- c(brewer.pal(numcols, "YlOrRd")[ids], NA)
        leglabs <- c(as.character(legbrks)[ids], NA)
        leg.list[[j]] <- list(layer=nm, leglabs=unlist(leglabs), legcols=legcols)
      }
      shp.wgs84@data$cols <- sub(cols, pattern="#", replacement="hex")
      
    } else {
      
      shp.wgs84@data$cols[shp.wgs84@data$CLASSIFY == "LOW"] <- "hex2979E3"
      shp.wgs84@data$cols[shp.wgs84@data$CLASSIFY == "MED"] <- "hexE3E029"
      shp.wgs84@data$cols[shp.wgs84@data$CLASSIFY == "HIGH"] <- "hexE33229"
      leg.list[[j]] <- list(layer=nm, leglabs=c("LOW", "MED", "HIGH"), legcols=c("#2979E3", "#E3E029","#E33229"))
    }
    
    print("write json")
    
    ## write geojson
    jsonfiles <- list.files(file.path(outpath), pattern="*.geojson$")
    if(!(paste(nm, ".geojson", sep="") %in% jsonfiles)){
      writeOGR(obj=shp.wgs84, dsn=paste(outpath, nm, ".geojson", sep=""), layer="layer", driver="GeoJSON", overwrite=T)
    }
    ## write legend json
    writeLines(toJSON(leg.list[1:length(leg.list)]), file.path(outpath, "legend.json"))
    
  } # close loop through shapefiles
} # close function def

#workspace <- paste("/var/www/html/ttapp/tmp-cv/", sess, "/", sep='')
#outspace <- paste("/var/www/html/ttapp/tmp-cv/", sess, "/", sep='')
workspace <- "C:/Users/dfisher5/Documents/Shiny/HRA/data"
outspace <- "C:/Users/dfisher5/Documents/Shiny/www/ttapp/tmp-hra/"
LoadSpace(workspace, outspace)

####################################
### trying stuff out with rasters

# r.risk <- raster(file.path(ws, "output/Maps/risk_mult.tif"))
# ## note only 32 unique floating pt values in this raster of 328,440 cells:
# length(unique(getValues(r.risk)))
# 
# #### using gdal_polygonize is wayyyy faster
# v.risk <- rasterToPolygons(r.risk, dissolve=T) ## takes a few minutes
# 
# writeOGR(obj=v.risk, dsn=paste(ws, "/", "tmp-hra", "risk.geojson", sep=""), layer="layer", driver="GeoJSON")