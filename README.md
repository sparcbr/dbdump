# dbdump
Web UI to manage mysql DB schema dumps

## Instructions
Copy db dir to your project.

Protect the dir. If using apache you can use the provided .htaccess; change the password using:
```htpasswd db/.htpasswd admin```

Fill your DB credentials in db/db.config.sample.php and rename to db.config.php

In db/dump/index.php there are some constants which you can change to specify schema dir and git updated schema.sql. The defaults are respectively db/schema/ and db/schema.sql

Point your browser to your project root URL followed by /db/dump/

Feel free to mess around or ask any questions.

Thanks!
