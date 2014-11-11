
###  script to create geojson data for php viewer.  called by the php viewer.

library(rgdal)
library(raster)
library(RColorBrewer)

## SessionID is passed as an argument from PHP page
args=(commandArgs(TRUE))
if(length(args)==0) { 
  print("ERROR: No arguments supplied.")
} else {
  for(i in 1:length(args)){
    eval(parse(text=args[[i]]))
  }
}
# sess <- "XXXXXXXXXXXXXXX"        # stick a test sessionID here for debugging
print(paste("SessionID =",sess))

## write geojson data
LoadSpace <- function(ws, outpath){
  #ws <- "C:/Users/dfisher5/Documents/Shiny/CoastalVulnerability/data/BigBC"
  ce <- read.table(file.path(ws, "outputs/coastal_exposure/coastal_exposure.csv"), sep=",", colClasses="numeric", header=T)
  aoi <- raster(file.path(ws, "intermediate/00_preprocessing/00_PRE_aoi.tif"))
  points.wgs84 <- rgdal::project(as.matrix(ce[,1:2]), proj=projection(aoi), inv=T)
  
  ### add style to df, then create GeoJSON
  for(i in 5:ncol(ce)){
    nm <- names(ce)[i]
    if (nm %in% c("coastal_exposure", "geomorphology", "natural_habitats", "wave_exposure", "surge_potential", "coastal_exposure_no_habitats")){
      cols <- brewer.pal(4, "YlOrRd")[as.numeric(cut(ce[,nm], c(1,2,3,4,5), right=F, include.lowest=T))]
    }
    if (nm %in% c("shore_exposure", "erodible_shoreline")){
      cols <- c("#92c5de", "#f4a582")[ce[,nm]+1]
    }
    if (nm == "habitat_role"){
      nonzero <- ce[which(ce[,nm] > 0),nm]
      brks <- quantile(nonzero, seq(0,1,.25))
      cols <- brewer.pal(4, "Purples")[as.numeric(cut(ce[,nm], breaks=brks, right=F, include.lowest=T))]
      cols <- sapply(cols, FUN=function(x){
        if (is.na(x)){
          return("#d3d3d3")
        } else {
          return(x)
        }
      })
    }
    cols <- sub(cols, pattern="#", replacement="hex")
    df <- cbind(points.wgs84, data.frame(ce[,nm]), data.frame(cols))
    names(df)[1:3] <- c("lng", "lat", nm)
    spdf <- SpatialPointsDataFrame(points.wgs84, data=df)
    jsonfiles <- list.files(file.path(outpath), pattern="*.geojson$")
    if(!(paste(nm, ".geojson", sep="") %in% jsonfiles)){
      writeOGR(obj=spdf, dsn=paste(outpath, nm, ".geojson", sep=""), layer="layer", driver="GeoJSON", overwrite=T)
    }
  }
}

## doit
workspace <- paste("/var/www/html/ttapp/tmp/", sess, "/", sep='')
outspace <- paste("/var/www/html/ttapp/tmp/", sess, "/", sep='')
#workspace <- paste("http://localhost:8000/ttapp/tmp/", sess, "/", sep='')
#outspace <- paste("http://localhost:8000/ttapp/tmp/", sess, "/", sep='')
LoadSpace(workspace, outspace)

