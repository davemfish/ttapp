
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
  #ws <- "C:/Users/dfisher5/Documents/Shiny/CoastalVulnerability/data/pointstest"
  ce <- read.table(file.path(ws, "coastal_exposure.csv"), sep=",", colClasses="numeric", header=T)
  aoi <- raster(file.path(ws, "00_PRE_aoi.tif"))
  points.wgs84 <- rgdal::project(as.matrix(ce[,1:2]), proj=projection(aoi), inv=T)
  
  ### add style to df, then create GeoJSON
  system(paste("rm ", outpath, "*geojson", sep=''))
  for(i in 5:ncol(ce)){
    nm <- names(ce)[i]
    cols <- brewer.pal(6, "YlOrRd")[as.numeric(cut(ce[,nm], c(-0.1,0,1,2,3,4,5), right=F, include.lowest=T))]
    cols <- sub(cols, pattern="#", replacement="hex")
    df <- cbind(points.wgs84, data.frame(ce[,nm]), data.frame(cols))
    names(df)[1:3] <- c("lng", "lat", nm)
    spdf <- SpatialPointsDataFrame(points.wgs84, data=df)
    jsonfiles <- list.files(file.path(outpath), pattern="*.geojson$")
    if(!(paste(nm, ".geojson", sep="") %in% jsonfiles)){
      writeOGR(obj=spdf, dsn=paste(outpath, nm, ".geojson", sep=""), layer="layer", driver="GeoJSON")  #, check_exists=T, overwrite_layer=T)
    }
  }
}

## doit
workspace <- paste("/var/www/html/ttapp/tmp/", sess, "/", sep='')
outspace <- paste("/var/www/html/ttapp/tmp/", sess, "/", sep='')
#workspace <- paste("http://localhost:8000/ttapp/tmp/", sess, "/", sep='')
#outspace <- paste("http://localhost:8000/ttapp/tmp/", sess, "/", sep='')
LoadSpace(workspace, outspace)

