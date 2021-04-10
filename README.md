# orm-static
A php libary for efficently (as few querys as posible) accessing mysql data as php objects and still be able to write querys in plain SQL.
To generate PHP classes execute the function **SaveDBClassesToFile**. The function will ask the db for it's tables and create coresponding PHP classes.

## QUERYview
QUERYview is a library for viewing, sorting, filtering and exporting the results of large complex sql querys (querys using mulpiple joins) super efficently in the browser.
Built for working with sql querys with 10000+ rows but scales fairly linearly up can easily render 100000 rows.

The example is built with data tables made from https://github.com/calledit/xml2rDB
