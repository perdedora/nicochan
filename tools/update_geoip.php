<?php

require dirname(__FILE__) . '/inc/cli.php';

query('ALTER TABLE ``custom_geoip`` DROP `country_name`;');
query('ALTER TABLE ``custom_geoip`` MODIFY `country` varchar(6) NOT NULL;');
