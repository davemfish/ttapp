####### Data flow for HRA-dash: #################
## client upload zip to server, including entire output workspace
## server unzip with php

## server run a shell script that includes: 

### GDAL commands to convert tif to shp:

###  For gdal_calc, '0' is interpreted as NoData and is left out of 
###  the subsequent polygons. This is desired behavior for ecosys_risk.tif, 
###  maybe not for other tifs?
###  For gdal_polygonize, it dissolves.

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
  area.subregions <- gArea(aoi)
  area.aoi <- sum(gArea(aoi))
  
  aoi.wgs84 <- spTransform(aoi, CRS("+proj=longlat +datum=WGS84 +no_defs"))
  bbox <- bbox(aoi.wgs84)
  
  shps <- list.files(file.path(ws, "output/Maps"), pattern="*.shp$")
  tifs <- list.files(file.path(ws, "output/Maps"), pattern="cum_risk.*tif$")
  
  ## list of tif filenames
  ptm <- proc.time()
  summlist <- list()
  for (g in 1: length(tifs)){
    nm1 <- sub(pattern=".tif", replacement="", tifs[g])
    rast <- raster(file.path(ws, "output/Maps", tifs[g]))
    regionlist <- list()
    for (k in 1:length(aoi)){
      region <- aoi[k,]
      vals <- unlist(extract(rast, region))
      #df <- as.data.frame(table(vals))
      factorx <- factor(cut(vals, breaks=nclass.Sturges(vals)))
      df <- as.data.frame(table(factorx))
      df$Habitat <- nm1
      df$Subregion <- as.character(region@data$name)
      regionlist[[k]] <- df
    }
    summlist[[g]] <- do.call("rbind", regionlist)
  }
  habsummary <- do.call("rbind", summlist)
  proc.time() - ptm
  
  #mapdatalist <- list()
  leg.list <- list()
  
  ## for each shapefile in Maps directory
  ptm <- proc.time()
  summ <- list()
  #for (j in 1:length(shps)){
  for (j in 1:4){
    
    nm <- sub(pattern=".shp", replacement="", shps[j])
    print("read shp")
    shp.prj <- readOGR(dsn=file.path(ws, "output/Maps"), layer=nm)
    
    ## use projected habitat shp to build table of risk class/area
    
    ## initialize table stuff
    summarytable <- data.frame(matrix(NA, nrow=3, ncol=4))
    #tab.list[[j]] <- data.frame(summarytable)
    names(summarytable) <- c("Habitat", "Risk", "Percent_ofHab", "Subregion")
    summarytable$Habitat <- nm
    summarytable$Risk <- c("LOW", "MED", "HIGH")
    
    ## subset hab shp into each risk class
    low <- shp.prj[which(shp.prj@data$CLASSIFY=="LOW"),]
    med <- shp.prj[which(shp.prj@data$CLASSIFY=="MED"),]
    high <- shp.prj[which(shp.prj@data$CLASSIFY=="HIGH"),]
    
    ## check for invalid geometries
    ## buffer by 0 -- a known workaround to fix this
    if (!(gIsValid(low))){
      print("buffering")
      low <- gBuffer(low, width=0)
    } else {
      print("VALID")
    }
    if (!(gIsValid(med))){
      print("buffering")
      med <- gBuffer(med, width=0)
    } else {
      print("VALID")
    }
    if (!(gIsValid(high))){
      print("buffering")
      high <- gBuffer(high, width=0)
    } else {
      print("VALID")
    }
    
    ## For each subregion in AOI
    tab.list <- list()
    for (k in 1:length(aoi)){
      region <- aoi[k,]
      ## intersect risk class with current subregion
      low.sect <- gIntersection(low, region, byid=F)
      med.sect <- gIntersection(med, region, byid=F)
      high.sect <- gIntersection(high, region, byid=F)
      
      ## sum areas of indiv pgons in each class
      ## some error handling in case an above intersection returns NULL
      l <- tryCatch({
        a <- sum(gArea(low.sect))
      }, error=function(e){
        a <- 0
        return(a)
      })
      m <- tryCatch({
        a <- sum(gArea(med.sect))
      }, error=function(e){
        a <- 0
        return(a)
      })
      h <- tryCatch({
        a <- sum(gArea(high.sect))
      }, error=function(e){
        a <- 0
        return(a)
      })
      risk.areas <- c(l,m,h)
      #risk.areas <- c(sum(gArea(low.sect)), sum(gArea(med.sect)), sum(gArea(high.sect)))
      ## total habitat area
      habitat.area <- sum(risk.areas)
      ## percent of habitat in each risk class
      ## TODO: percentage of AOI in each risk class
      percentage <- risk.areas/habitat.area
      summarytable$Percent_ofHab <- percentage
      summarytable$Subregion <- as.character(region@data$name)
      ## store result to list
      tab.list[[k]] <- summarytable
    } # next subregion
    
    ## append this habitat summary to a dataframe
    summ[[j]] <- do.call("rbind", tab.list)
  } # next habitat
  habsummary <- do.call("rbind", summ)
  proc.time() - ptm
    
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
      
    } else {
      
      shp.wgs84@data$cols[shp.wgs84@data$CLASSIFY == "LOW"] <- "hex2979E3"
      shp.wgs84@data$cols[shp.wgs84@data$CLASSIFY == "MED"] <- "hexE3E029"
      shp.wgs84@data$cols[shp.wgs84@data$CLASSIFY == "HIGH"] <- "hexE33229"
      leg.list[[j]] <- list(layer=nm, leglabs=c("LOW", "MED", "HIGH"), legcols=c("#2979E3", "#E3E029","#E33229"))
    }
    
    print("write json")
    
    ## write geojson
    ## limit coord precision to 5 decimals
    #print(round(coordinates(shp.wgs84)[1:5,], digits=5), digits=10)
#     test <- shp.wgs84
#     test.df <- fortify(test)
#     newcoords <- round(test.df[,c(1,2)], digits=5)
#     
#     list of P <- Polygon(newcoords, hole=)
#     list of Ps <- Polygons(list of P, ID)
#     SP <- SpatialPolygons(list of Ps, order, proj4string=CRS())
#     spdf <- SpatialPolygonsDataFrame(SP, data, match.ID=T)
#     newcoords <- round(coordinates(test), digits=5)
#     coordinates(test.df) <- newcoords
    
    jsonfiles <- list.files(file.path(outpath), pattern="*.geojson$")
    if(!(paste(nm, ".geojson", sep="") %in% jsonfiles)){
      writeOGR(obj=shp.wgs84, dsn=paste(outpath, nm, ".geojson", sep=""), layer="layer", driver="GeoJSON", overwrite=T)
    }
    
  } # next shapefile

  ## write legend json
  writeLines(toJSON(leg.list[1:length(leg.list)]), file.path(outpath, "legend.json"))

  ## write habitat summary csv
  habsummary <- do.call("rbind", summ)
  habsummary$Percent_ofHab <- habsummary$Percent_ofHab*100
  write.csv(habsummary, file.path(outpath, "habsummary.csv"), row.names=F)
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
