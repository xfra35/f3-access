<?php
require('vendor/autoload.php');
$f3=Base::instance();

$f3->mset(array(
    'AUTOLOAD'=>'tests/',
    'UI'=>'tests/',
    'TEMP'=>'var/tmp/',
    'DEBUG'=>3,
));
$f3->route('GET /','Tests->run');

$f3->run();
