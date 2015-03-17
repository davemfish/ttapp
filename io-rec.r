###  script to create geojson data for php viewer.  called by the php viewer.

library(rgdal)
library(raster)
library(RColorBrewer)
library(RJSONIO)
library(rgeos)

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

### Things this script should check for in the results:
## Is AOI gridded?  How many grid cells?
#   convert to points to work with markercluster?
#   OR create multiple aggregated versions to load at diff zoom scales

## Is there are results.zip? And a grid.shp? If not, errors need to get 
## back to user

#paste("<div class=alert alert-info role=alert> you have", x, "polygons</div>")  ????

Cut2Num <- function(x){
  ids <- unique(as.numeric(x))
  char.x <- as.character(levels(x))
  num.x <- as.numeric(gsub(unlist(strsplit(char.x, split=",")), pattern='\\(|\\]', replacement=""))
  return(list(brks=unique(num.x), ids=ids))
}

### The symbology scheme:

LoadSpace <- function(workspace, outspace){ # x is the session ID
  logfile <- readLines(con=file.path(workspace, "rec_logfile.txt"), n=-1)
  blanks <- which(logfile=="")
  logtable <- logfile[1:(min(blanks) - 1)]
  sessionline <- logfile[grep(logfile, pattern="Assigned server session id")]
  sessid <- sub(sessionline, pattern=".*Assigned server session id ", replacement="")
  sessid <- sub(sessid, pattern="\\.", replacement="")
  
  ## get results and unzip
  #ws <- file.path(workspace, "results.zip")   ## test locally
  #unzip(file.path(ws, "results.zip"), exdir="C:/Users/dfisher5/Documents/Shiny/www/ttapp-rec/tmp/testingio")
  ws <- file.path("/mnt/recreation/public_html/data", sessid, "results.zip")
  #setwd(workspace)
  #unzip(file.path(ws, "results.zip"), exdir=".")
  #system(paste("unzip", ws, paste(workspace, "/*", sep=""), sep=" "))
  system(paste("unzip", ws, "-d", workspace, sep=" "))
  #read the shapefile from the zip folder that gets delivered to user
  grid <- readOGR(dsn=workspace, layer="grid")
  #read the init.json for metadata
  init <- fromJSON(file.path(workspace, "init.json"))
  
  atts <- grid@data
  ## trim out cell ID and Area cols before making geojson
  atts <- atts[,c(-1,-4)]

  leg.list <- list()
  for (j in 1:length(names(atts))){ ## loop through fields in table and make geojson for each
    nm <- names(atts)[j]
    dat <- atts[,nm]
    ## if dat contains only 1 unique value, skip this attribute altogether.
    ## it won't get a geojson for the map, but will still appear in table.
    if (length(unique(dat)) < 2){
      #paste("WARN:", nm, "values are the same in every cell. Layer will not be mapped", sep=" ")
      leg.list[[j]] <- list(layer=nm, leglabs="none", legcols="none")
      next
    }
    if (nm %in% c("usdyav", "usdyav_pr", "usdyav_est")){
      ramp <- "RdPu"
    } else {
      ramp <- "Oranges"
    }
    
    if (nm %in% c("usdyav", "usdyav_est")){
      ## log transform data and make cuts
        brks <- cut(log(dat+1), breaks=6)
        ## assign colors from ramp to break categories
        cols <- as.list(brewer.pal(6, ramp)[as.numeric(brks)])
        ## manually assign color for zeros
        cols[which(dat == 0)] <- "#d3d3d3"
        ## convert brks factor to numeric, for use in legend
        brks.list <- Cut2Num(brks)
        num.brks <- brks.list[["brks"]]
        ## back-transform numeric breaks to real values
        legbrks <- round(exp(num.brks)-1, digits=3)
    } else { # same as above but without log transform
        brks <- cut(dat, breaks=6) # fails if breaks not unique
        cols <- as.list(brewer.pal(6, ramp)[as.numeric(brks)])
        cols[which(dat == 0)] <- "#d3d3d3"
        brks.list <- Cut2Num(brks)
        num.brks <- brks.list[["brks"]]
        legbrks <- round(num.brks, digits=3)
    }
    
    legbrks[1] <- round(min(dat), digits=3) ## set lower limit to min val.
    legbrks[length(legbrks)] <- round(max(dat), digits=3) ## set upper limit to max value (it ends up higher after cuts)
    ids <- brks.list[["ids"]] 
    ids <- ids[order(ids)]
    ids <- c(1, ids+1)
    legbrks <- legbrks[ids]
    
    ## make labels for legend
    leglabs <- list()
    if (legbrks[1] == 0){ ## if zeros are there own category
      for (i in 1:length(legbrks)){
        if (i == 1) { 
          leglabs[[i]] <- legbrks[i] 
        } else {
          leglabs[[i]] <- paste((legbrks[i-1]+0.001), "-", legbrks[i])
        }
      }
      legcols <- c("#d3d3d3", brewer.pal(length(legbrks)-1, ramp))
    } else { ## if there are no zeros
      for (i in 1:(length(legbrks)-1)){
        leglabs[[i]] <- paste(legbrks[i], "-", (legbrks[i+1]-0.001))
      }
      legcols <- brewer.pal(length(legbrks)-1, ramp)
    } 
    ## if there are only 2 categories with data, 
    ## limit the colors listed in legend to only 2
    ## (colorBrewer pallette returns 3 minimum)
    if (length(unlist(leglabs)) < length(legcols)){
      legcols <- tail(legcols, length(unlist(leglabs)))
    }
    
    
    leg.list[[j]] <- list(layer=nm, leglabs=unlist(leglabs), legcols=legcols)
    
    ## cols for each point go in geojson, used simply as classes, actual color assigned in css
    cols <- sub(cols, pattern="#", replacement="hex")
    df <- cbind(data.frame(atts[,nm]), data.frame(cols))
    names(df)[1] <- c(nm)
    df$cellID <- grid@data$cellID
    
    
    if (init$grid & length(grid) > 3000){ ## if AOI is gridded and > 3000 cells
      ## convert grid cell to point, by centroid
      points <- gCentroid(grid, byid=TRUE)
      points.wgs84 <- spTransform(points, CRS("+proj=longlat +datum=WGS84 +no_defs"))
      spdf <- SpatialPointsDataFrame(points.wgs84, data=grid@data)
      spdf@data <- df
      init$pts_poly <- "points"
    } else { ## if AOI not gridded
      ## don't convert polygons to points
      spdf <- spTransform(grid, CRS("+proj=longlat +datum=WGS84 +no_defs"))
      spdf@data <- df
      init$pts_poly <- "poly"
    } 
    
    ## writeOGR overwrite arg doesn't work, so check if file exists first.
    jsonfiles <- list.files(file.path(outspace), pattern="*.geojson$")
    if(!(paste(nm, ".geojson", sep="") %in% jsonfiles)){
      writeOGR(obj=spdf, dsn=file.path(outspace, paste(nm, ".geojson", sep="")), layer="layer", driver="GeoJSON", overwrite=T)
    }
    #print("QAQC:")
    #print(paste("WARN: Creating", paste(outspace, nm, ".geojson", sep="")))
  }
  writeLines(toJSON(leg.list), file.path(outspace, "legend.json"))
  writeLines(toJSON(init), file.path(outspace, "init.json"))
  write.csv(grid@data, file.path(outspace, "grid.csv"), row.names=F)
}



## doit
### these are 2 different vars only so its easier to test locally
workspace <- paste("/var/www/html/ttapp/tmp-rec/", sess, sep='') # where R finds the uploaded logfile
outspace <- paste("/var/www/html/ttapp/tmp-rec/", sess, sep='')
#workspace <- "C:/Users/dfisher5/Documents/Shiny/www/data/OR"
#outspace <- "C:/Users/dfisher5/Documents/Shiny/www/ttapp-rec/tmp/testingio"
LoadSpace(workspace, outspace)
