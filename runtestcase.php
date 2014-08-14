<?php

include "randomtable.class.php";

$parser = new randomtable(file_get_contents("testdata.txt"));

$res = $parser->generate();

echo $res;

echo "\n\nDone\n";

