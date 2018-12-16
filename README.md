## ArchivesSpace Accession Report (ish)

This is a thin wrapper around a database query.

### Install

- clone this to a web root
- ensure that the following are installed: php, libapache2php, php-mysql; on ubuntu, `apt-get install libapache2-mod-php php-mysql`
- copy `config.ini.dist` to `config.ini` with valid local values for your database; ensure db user can only `SELECT`.
- if the webserver is proxy-ing everything for aspace, add an exception for this in your vhost config, like: `ProxyPassMatch ^/accession-report !`


### Usage

Note: at the time of this writing, this is a prototype, and details are very likely to change.

This app/service has its entrypoint at `/accession-report.php`.
A value entered in the 'Search' form is used to query the Aspace db for a matching 'Mss Number' (alter the query to use a different field).

The search string needs to be alpha-numeric, and can include a hyphen.

When multiple db rows match, they are all returned, and a list of ordered Mss numbers at the top of the page links to the full record elsewhere in the page.
Each record starts with the Mss in <h1>, and clicking this links to a display of just that record (provided the Mss is unique).

Printing is supported with print CSS that removes the search box and link list.


###  Resources

PDO

- SQL injection: https://www.owasp.org/index.php/Testing_for_SQL_Injection_(OTG-INPVAL-005)
- basics: https://phpdelusions.net/pdo

Apache:

- proxy everything except...: https://stackoverflow.com/questions/26848945/exclude-an-alias-from-virtualhost-proxypass


CSS Resources:

- dictionary layout: https://stackoverflow.com/questions/1713048/how-to-style-dt-and-dd-so-they-are-on-the-same-line
- grid sizing: https://developer.mozilla.org/en-US/docs/Web/CSS/grid-template-columns
