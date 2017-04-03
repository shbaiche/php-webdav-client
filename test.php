<?php
require 'WebDAV.php';

$client = new WebDAV([
	'host'=>'localhost',
	'port'=>80
]);

//var_dump( $client->head("/") );
//var_dump( $client->get("/") );
//var_dump( $client->options("/") );
var_dump( $client->post("/", ['a'=>'b']) );
