####### Data flow for HRA-dash: #################
## client upload zip to server, including entire output workspace
## server unzip with php

## server run a shell script that includes: 

### GDAL commands to convert tif to shp:

###  For gdal_calc, '0' is interpreted as NoData and is left out of 
###  the subsequent polygons. This is desired behavior for ecosys_risk.tif, 
###  maybe not for other tifs?
###  For gdal_polygonize, it dissolves, but it also converts to integer, which 
###  is the reason to scale by 10000 first with gdal_calc.

#     gdal_calc -A ecosys_risk.tif --outfile=ecorisk_mult.tif --calc="A*10000"    
#     gdal_polygonize ecorisk_mult.tif -f "ESRI Shapefile" ecosys_risk.shp ecosys_risk risk

    ### can ogr2ogr to reduce coord precision happen here, shp2shp? What happens to coords
    ### when R reads the shp and writes the GeoJSON?

### R script (this one) to read shp, style, and write geojson

### OGR to reduce the coordinate precision of geojson, greatly reduces filesize

#     ogr2ogr -f "GeoJSON" -lco COORDINATE_PRECISION=5 ecosys_risk.geojson ecosys_risk.geojson

################################################

## subregions shp is not available among outputs.

## Simple bar charts Area ~ Risk class.

library(raster)
library(rgeos)
library(ggplot2)
library(rgdal)
library(RColorBrewer)
library(RJSONIO)

Cut2Num <- function(x){
  ids <- unique(as.numeric(x))
  char.x <- as.character(levels(x))
  num.x <- as.numeric(gsub(unlist(strsplit(char.x, split=",")), pattern='\\(|\\[|\\)|\\]', replacement=""))
  return(list(brks=unique(num.x), ids=ids))
}


## On click of ecosys_risk, what info to return?
# val of ecosys_risk
# Val of cumulative habitat risk for all hab layers present
# list of stressors present

## SYMBOLIZATION:

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
  ##### Load AOI
  aoi <- readOGR(dsn=file.path(ws, "intermediate"), layer="temp_aoi_copy")
  ## get Area of AOI in projected units
  ## TODO: grep the proj4string for units??
  #area.subregions <- gArea(aoi)
  #area.aoi <- sum(gArea(aoi))
  
  aoi.wgs84 <- spTransform(aoi, CRS("+proj=longlat +datum=WGS84 +no_defs"))
  bbox <- bbox(aoi.wgs84)
  print("write geojson")
  jsonfiles <- list.files(file.path(outpath), pattern="*.geojson$")
  if(!(paste("aoi", ".geojson", sep="") %in% jsonfiles)){
    writeOGR(obj=aoi.wgs84, dsn=paste(outpath, "aoi.geojson", sep=""), layer="layer", driver="GeoJSON", overwrite=T)
  }
  
  shps <- list.files(file.path(ws, "output/Maps"), pattern="*.shp$")
  tiffiles <- list.files(file.path(ws, "output/Maps"), pattern="cum_risk.*tif$")
  ## trim names to just the habitat word
  tifs <- unlist(lapply(tiffiles, FUN=function(x){
    a <- unlist(strsplit(x, split="_"))[3]
    b <- sub(pattern=".tif", replacement="", a)
    }))
  tifs <- c(tifs, "ecosys_risk")
  tiffiles <- c(tiffiles, "ecosys_risk.tif")
  
  ## read and process tifs
  ptm <- proc.time()
  summlist <- list()
  for (g in 1: length(tiffiles)){
#     nm1 <- unlist(strsplit(tifs[g], split="_"))[3]
#     nm1 <- sub(pattern=".tif", replacement="", nm1)
    rast <- raster(file.path(ws, "output/Maps", tiffiles[g]))
    regionlist <- list()
    for (k in 1:length(aoi)){
      region <- aoi[k,]
      vals <- unlist(extract(rast, region))
      #df <- as.data.frame(table(vals))
      factorx <- factor(cut(vals, breaks=nclass.Sturges(vals)))
      df <- as.data.frame(table(factorx))
      df$Habitat <- tifs[g]
      df$Subregion <- as.character(region@data$name)
      regionlist[[k]] <- df
    }
    summlist[[g]] <- do.call("rbind", regionlist)
  }
  habsummary <- do.call("rbind", summlist)
  ## TODO -- formatting of habsummary content: transform counts to areas
  
  ## write habitat summary csv
  write.csv(habsummary, file.path(outpath, "habsummary.csv"), row.names=F)
  proc.time() - ptm
  
  #mapdatalist <- list()
  leg.list <- list()
  
  ## for each shapefile in Maps directory

  for (j in 1:length(shps)){
    nm <- sub(pattern=".shp", replacement="", shps[j])
    print("read shp")
    shp.prj <- readOGR(dsn=file.path(ws, "output/Maps"), layer=nm)
    
    ####### Transform to wgs84 and style ######
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
      
      print("write geojson")
      jsonfiles <- list.files(file.path(outpath), pattern="*.geojson$")
      if(!(paste(nm, ".geojson", sep="") %in% jsonfiles)){
        writeOGR(obj=shp.wgs84, dsn=paste(outpath, nm, ".geojson", sep=""), layer="layer", driver="GeoJSON", overwrite=T)
      }
      
    } else { 
      if (grepl("\\RISK", nm)){ ## if it's a habitat risk layer
        
        nm <- unlist(strsplit(nm, split="_"))[1]
        nm <- gsub("[[:punct:]]", "", nm)
        nm <- paste("H_", nm, sep="")
        
        shp.wgs84@data$cols[shp.wgs84@data$CLASSIFY == "LOW"] <- "hex2979E3"
        shp.wgs84@data$cols[shp.wgs84@data$CLASSIFY == "MED"] <- "hexE3E029"
        shp.wgs84@data$cols[shp.wgs84@data$CLASSIFY == "HIGH"] <- "hexE33229"
        leg.list[[j]] <- list(layer=nm, leglabs=c("LOW", "MED", "HIGH"), legcols=c("#2979E3", "#E3E029","#E33229"))
        
        print("write geojson")
        jsonfiles <- list.files(file.path(outpath), pattern="*.geojson$")
        if(!(paste(nm, ".geojson", sep="") %in% jsonfiles)){
          writeOGR(obj=shp.wgs84, dsn=paste(outpath, "H_", nm, ".geojson", sep=""), layer="layer", driver="GeoJSON", overwrite=T)
        }
        
      } else { ## if its not ecosystem risk or habitat risk, it's a stressor
        nm <- paste("S_", nm, sep="")
        shp.wgs84@data$cols <- "hexd3d3d3"
        leg.list[[j]] <- list(layer=nm, leglabs=c("Stressor"), legcols=c("#d3d3d3"))
        
        print("write geojson")
        jsonfiles <- list.files(file.path(outpath), pattern="*.geojson$")
        if(!(paste(nm, ".geojson", sep="") %in% jsonfiles)){
          writeOGR(obj=shp.wgs84, dsn=paste(outpath, nm, ".geojson", sep=""), layer="layer", driver="GeoJSON", overwrite=T)
        }
      }
    }
    
  } # next shapefile

  ## write legend json
  writeLines(toJSON(leg.list[1:length(leg.list)]), file.path(outpath, "legend.json"))

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
