<?php


$Module = array( 'name' => 'Add location',
                 'variable_params' => true );

$ViewList = array();

$ViewList['to'] = array( 'functions' => array( 'to' ),
                                      'script' => 'to.php',                                      
                                      'params' => array( 'ContentObjectID' ),
                                      'unordered_params' => array() );

$FunctionList['to'] = array();



?>
