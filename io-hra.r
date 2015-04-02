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
library(rgdal)
library(RColorBrewer)
library(RJSONIO)
#library(XML)

# ## SessionID is passed as an argument from PHP page
# args=(commandArgs(TRUE))
# if(length(args)==0) { 
#   print("ERROR: No arguments supplied.")
# } else {
#   for(i in 1:length(args)){
#     eval(parse(text=args[[i]]))
#   }
# }
# # sess <- "testing"        # stick a test sessionID here for debugging
# print(paste("SessionID =",sess))

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
  
  ##### Load Logfile
  ## gridsize and max stressors
  logfile <- readLines(con=file.path(ws, list.files(ws, pattern="hra-log-*")), n=-1)
  blanks <- which(logfile=="")
  logtable <- logfile[1:(min(blanks) - 1)]
  l.grid <- logtable[grep(logtable, pattern="grid_size")]
  gridsize <- as.numeric(tail(unlist(strsplit(l.grid, split=" ")), 1))
  l.nstress <- logtable[grep(logtable, pattern="max_stress")]
  nstress <- as.numeric(tail(unlist(strsplit(l.nstress, split=" ")), 1))
  
  ##### Load HTML Table output
 # theurl <- list.files(file.path(ws, "output/HTML_Plots"), pattern="Sub_Region*", full.names=T)
 # tables <- readHTMLTable(theurl)
 # thepage <- htmlParse(theurl, useInternalNodes=F)
 # zz <- xpathApply(thepage$children$html, "//h2")
 # for (i in 1:length(tables)){
 #   tables[[i]]$Subregion <- tail(as.character(zz[[i]]$children$text), 1)
 # }
 # datECR <- do.call("rbind", tables)
  
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
    b <- sub(pattern="].tif", replacement="", a, fixed=T)
    d <- sub(pattern="[", replacement="H_", b, fixed=T)
    }))
  tifs <- c(tifs, "ecosys_risk")
  tiffiles <- c(tiffiles, "ecosys_risk.tif")
  
  ## read and process tifs
  ptm <- proc.time()
  summlist <- list()
  ## find 33% and 66% breaks based on max_stressor rating
  quants <- quantile(c(0,nstress), probs=seq(0,1,1/3), na.rm=T)
  p33 <- quants[2]
  p66 <- quants[3]
  for (g in 1: length(tiffiles)){
#     nm1 <- unlist(strsplit(tifs[g], split="_"))[3]
#     nm1 <- sub(pattern=".tif", replacement="", nm1)
    rast <- raster(file.path(ws, "output/Maps", tiffiles[g]))
    regionlist <- list()
    for (k in 1:length(aoi)){
      region <- aoi[k,]
      r <- mask(rast, region)
      vals <- getValues(r)
      
      lows <- vals[which(vals <= p33)]
      meds <- vals[which(vals > p33 & vals <= p66)]
      highs <- vals[which(vals > p66)]
      
      A.low <- length(lows)*gridsize*gridsize
      A.med <- length(meds)*gridsize*gridsize
      A.high <- length(highs)*gridsize*gridsize

      #factorx <- factor(cut(vals, breaks=nclass.Sturges(vals)))
      #df <- as.data.frame(table(factorx))
      names(region@data) <- tolower(names(region@data))
      df <- data.frame("Habitat"=tifs[g], "Subregion"=as.character(region@data$name), "Classify"=c("LOW", "MED", "HIGH"), "Area"=NA)
      
      df$Area[1] <- A.low
      df$Area[2] <- A.med
      df$Area[3] <- A.high
      
      #df$Habitat <- tifs[g]
      #df$Subregion <- as.character(region@data$name)
      
      regionlist[[k]] <- df
      print(proc.time() - ptm)
    }
    summlist[[g]] <- do.call("rbind", regionlist)
    
  }
  habsummary <- do.call("rbind", summlist)
  proc.time() - ptm
  
  ## write habitat summary csv and json
  write.csv(habsummary, file.path(outpath, "habsummary.csv"), row.names=F)
  habjson <- toJSON(habsummary)
  habjson <- paste('{ "data":', habjson, "}")
  writeLines(habjson, file.path(outpath, "habsummary.json"))
  
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

#workspace <- paste("/var/www/html/ttapp/tmp-hra/", sess, "/", sep='')
#outspace <- paste("/var/www/html/ttapp/tmp-hra/", sess, "/", sep='')
workspace <- "./"
outspace <- "./"
#workspace <- "C:/Users/dfisher5/Documents/Shiny/HRA/data"
#outspace <- "C:/Users/dfisher5/Documents/Shiny/www/ttapp/tmp-hra/"
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
