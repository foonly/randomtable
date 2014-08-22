<?php

include "randomtable.class.php";

$parser = new randomtable(file_get_contents("testdata.txt"));

$res = $parser->generate();

print_r($parser);

echo $res;

echo "\n\nDone\n";

