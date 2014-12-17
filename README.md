News: ttapp-rec repo is now obsolete, files and outstanding issues were moved to this repo.
*some files were renamed, including 'tmp' directory where R output goes. So to develop locally, make 'tmp-rec' and 'tmp-cv' in your cloned repo.

InVEST Dashboard 
=====

About this application
=========================

This application allows an InVEST user to view a set of model results interactively in a web browser. 
All the data displayed in this app come from the coastal_exposure.csv file in the outputs folder of an InVEST workspace.

The raw data from this csv is viewable on the Table tab and on the Map at high zoom levels. 
At lower zoom levels data-points are clustered together, and clicking a cluster reveals all the individual coastal segments.

Not all of the results produced by the Coastal Vulnerability model are displayed in this application.
You may wish to explore and analyze your results further with GIS or data analysis software.

This application is built by the Natural Capital Project. The source code (R and javascript) is available and
you are encouraged to submit bugs and feature requests at https://github.com/davemfish/ttapp/issues
