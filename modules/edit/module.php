<?php


$Module = array(
    'name' => 'OpenContent Edit object',
    'variable_params' => true
);

$ViewList = array();

$ViewList['attribute'] = array(
    'functions' => array( 'edit' ),
    'script' => 'attribute.php',
    'params' => array( 'ObjectID', 'AttributeIdentifier', 'CreateNewVersion' ),
    'unordered_params' => array()
);

$FunctionList['edit'] = array();



?>
