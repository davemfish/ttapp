
###  script to create geojson data for php viewer.  called by the php viewer.

library(rgdal)
library(raster)
library(RColorBrewer)
library(RJSONIO)

## SessionID is passed as an argument from PHP page
args=(commandArgs(TRUE))
if(length(args)==0) { 
  print("ERROR: No arguments supplied.")
} else {
  for(i in 1:length(args)){
    eval(parse(text=args[[i]]))
  }
}
# sess <- "gb5fkgf007rt73537e5cuinfu4"        # stick a test sessionID here for debugging
print(paste("SessionID =",sess))

Cut2Num <- function(x){
  ids <- unique(as.numeric(x))
  char.x <- as.character(levels(x))
  num.x <- as.numeric(gsub(unlist(strsplit(char.x, split=",")), pattern='\\(|\\[|\\)|\\]', replacement=""))
  return(list(brks=unique(num.x), ids=ids))
}

## write geojson data
LoadSpace <- function(ws, outpath){
  #ws <- "C:/Users/dfisher5/Documents/Shiny/CoastalVulnerability/data/Florida_CV_inputs_WGS84/Florida_CV_inputs_WGS84/CV_out_200m"
  ce <- read.table(file.path(ws, "coastal_exposure.csv"), sep=",", colClasses="numeric", header=T, check.names=F)
  tmp.ce <- ce
  tmp.ce$ID <- seq(0,nrow(ce)-1,1) # start IDs at 0 to match js array indexing
  tmp.ce <- tmp.ce[,c("ID", names(ce))]
  write.table(tmp.ce, file.path(outpath, "coastal_exposure.csv"), sep=",", row.names=F)
  aoi <- raster(file.path(ws, "00_PRE_aoi.tif"))
  points.wgs84 <- rgdal::project(as.matrix(ce[,1:2]), proj=projection(aoi), inv=T)
  
  leg.list <- list()
  ### add style to df, then create GeoJSON
  for(j in 5:ncol(ce)){
    nm <- names(ce)[j]
    if (nm %in% c("coastal_exposure", "geomorphology", "natural_habitats", "wave_exposure", "surge_potential", "coastal_exposure_no_habitats", "sea_level_rise", "relief")){
      cats <- cut(ce[,nm], c(1,2,3,4,5), right=F, include.lowest=T)
      cols <- brewer.pal(4, "YlOrRd")[as.numeric(cats)]
      brks.list <- Cut2Num(cats)
      num.brks <- brks.list[["brks"]]
      legbrks <- round(num.brks, digits=3)
      #legbrks[1] <- 0
      ids <- brks.list[["ids"]]
      ids <- ids[order(ids)]
      #ids <- c(ids)
      #legbrks <- legbrks[ids]
      if (length(ids) > 1){
        leglabs <- list()
        for (i in 2:length(legbrks)){
          #         if (i == 1) { 
          #           leglabs[[i]] <- legbrks[i] 
          #         } else {
          leglabs[[i-1]] <- paste(legbrks[i-1], "-", legbrks[i])
          #        }
        }
        legcols <- brewer.pal(4, "YlOrRd")[ids]
        leg.list[[j]] <- list(layer=nm, leglabs=unlist(leglabs[ids]), legcols=legcols)
      } else {
        legcols <- c(brewer.pal(4, "YlOrRd")[ids], NA)
        leglabs <- c(as.character(legbrks)[ids], NA)
        leg.list[[j]] <- list(layer=nm, leglabs=unlist(leglabs), legcols=legcols)
      }
      
    }
    if (nm %in% c("shore_exposure", "erodible_shoreline")){
      cols <- c("#92c5de", "#f4a582")[ce[,nm]+1]
      leg.list[[j]] <- list(layer=nm, leglabs=c("0 - No", "1 - Yes"), legcols=c("#92c5de", "#f4a582"))
    }
    if (nm == "habitat_role"){
      nonzero <- ce[which(ce[,nm] > 0),nm]
      brks <- quantile(nonzero, seq(0,1,.25))
      cats <- cut(ce[,nm], breaks=brks, right=F, include.lowest=T)
      cols <- brewer.pal(4, "Purples")[as.numeric(cats)]
      cols <- sapply(cols, FUN=function(x){
        if (is.na(x)){
          return("#d3d3d3")
        } else {
          return(x)
        }
      })
      brks.list <- Cut2Num(cats[!is.na(cats)])
      num.brks <- brks.list[["brks"]]
      legbrks <- round(num.brks, digits=3)
      legbrks <- c(0, legbrks)
      ids <- brks.list[["ids"]]
      ids <- ids[order(ids)]
      #ids <- c(1, ids+1)
      #legbrks <- legbrks[ids]
      
      leglabs <- list()
      for (i in 1:(length(legbrks)-1)){
                if (i == 1) { 
                  leglabs[[i]] <- legbrks[i] 
                } else {
                  leglabs[[i]] <- paste(legbrks[i], "-", legbrks[i+1])
                }
      }
      legcols <- c("#d3d3d3", brewer.pal(4, "Purples")[ids])
      leg.list[[j]] <- list(layer=nm, leglabs=unlist(leglabs), legcols=legcols)
    }
    if (!(nm %in% c("habitat_role", "shore_exposure", "erodible_shoreline", "coastal_exposure", "geomorphology", "natural_habitats", "wave_exposure", "surge_potential", "coastal_exposure_no_habitats", "sea_level_rise", "relief"))){
      brks <- quantile(ce[,nm], seq(0,1,.25))
      brks <- unique(brks)
      if (length(brks) > 1){
        cats <- cut(ce[,nm], breaks=brks, right=F, include.lowest=T)
        cols <- brewer.pal(length(unique(brks)), "PuRd")[as.numeric(cats)]
        brks.list <- Cut2Num(cats)
        num.brks <- brks.list[["brks"]]
        legbrks <- round(num.brks, digits=3)
        #legbrks[1] <- 0
        ids <- brks.list[["ids"]]
        ids <- ids[order(ids)]
        #ids <- c(ids)
        #legbrks <- legbrks[ids]
        
        leglabs <- list()
        for (i in 2:length(legbrks)){
          #         if (i == 1) { 
          #           leglabs[[i]] <- legbrks[i] 
          #         } else {
          leglabs[[i-1]] <- paste(legbrks[i-1], "-", legbrks[i])
          #        }
        }
        legcols <- brewer.pal(4, "PuRd")[ids]
        leg.list[[j]] <- list(layer=nm, leglabs=unlist(leglabs), legcols=legcols)
      } else {
        leglabs <- c(as.character(brks), NA)
        cols <- "#8c2d04"
        legcols <- c(cols, NA)
        leg.list[[j]] <- list(layer=nm, leglabs=leglabs, legcols=legcols)
      }
    }
    cols <- sub(cols, pattern="#", replacement="hex")
    df <- cbind(data.frame(ce[,nm]), data.frame(cols))
    names(df)[1] <- c(nm)
    df$ID <- tmp.ce$ID
    spdf <- SpatialPointsDataFrame(round(points.wgs84, digits=6), data=df)
    jsonfiles <- list.files(file.path(outpath), pattern="*.geojson$")
    if(!(paste(nm, ".geojson", sep="") %in% jsonfiles)){
      writeOGR(obj=spdf, dsn=paste(outpath, nm, ".geojson", sep=""), layer="layer", driver="GeoJSON", overwrite=T)
    }
}
    writeLines(toJSON(leg.list[5:length(leg.list)]), file.path(outpath, "legend.json"))
}

## doit
workspace <- paste("/var/www/html/ttapp/tmp/", sess, "/", sep='')
outspace <- paste("/var/www/html/ttapp/tmp/", sess, "/", sep='')
#workspace <- "C:/Users/dfisher5/Documents/Shiny/CoastalVulnerability/data/CV"
#outspace <- "C:/Users/dfisher5/Documents/Shiny/www/ttapp/tmp/c6bg64k71ef48vbqtdpmcbou50/"
LoadSpace(workspace, outspace)

