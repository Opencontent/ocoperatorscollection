<?php


$Module = array( 'name' => 'OpenContent Switch User',
                 'variable_params' => true );

$ViewList = array();

$ViewList['user'] = array( 'functions' => array( 'user' ),
                           'script' => 'user.php',
                           'params' => array( 'User' ),
                           'unordered_params' => array() );

$FunctionList['user'] = array();



?>
