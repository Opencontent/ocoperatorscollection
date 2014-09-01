<?php
require 'autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance( array( 'description' => ( "Fix vardir in ezimagefile table" ),
                                   'use-session' => false,
                                   'use-modules' => true,
                                   'use-extensions' => true ) );

$script->startup();

$options = $script->getOptions( '[from_path:]',
    '',
    array( 'from_path'  => 'Old vardir image path')
);
$script->initialize();
$script->setUseDebugAccumulators( true );

$user = eZUser::fetchByName( 'admin' );
eZUser::setCurrentlyLoggedInUser( $user , $user->attribute( 'contentobject_id' ) );

$varDir = eZSys::varDirectory();
if( $options['from_path'] )
    $path = $options['from_path'];
else
{
    $cli->error( "Specifica il percorso da sostituire" );
    $script->shutdown();
    eZExecution::cleanExit();
}

$path = rtrim( $path, '/' ) . '/';
$varDir = rtrim( $varDir, '/' ) . '/';

$output = new ezcConsoleOutput();
$question = ezcConsoleQuestionDialog::YesNoQuestion(
    $output,
    "Correggo i percorsi per VarDir: da \"$path\" a \"$varDir\" ?",
    "y"
);


if ( ezcConsoleDialogViewer::displayDialog( $question ) == "n" )
{
    $script->shutdown();
    eZExecution::cleanExit();
}
else
{
    $cli->output( "Process ezimagefile table" );

    $list = eZImageFile::fetchObjectList( eZImageFile::definition() );
    foreach( $list as $item )
    {
        $newPath = str_replace( $path, $varDir, $item->attribute( 'filepath' ) );
        if ( $newPath != $item->attribute( 'filepath' ) )
        {
            $cli->output( "Fix attribute " . $item->attribute( 'contentobject_attribute_id' ) . " " . $item->attribute( 'filepath' ) );
            eZImageFile::moveFilepath( $item->attribute( 'contentobject_attribute_id' ), $item->attribute( 'filepath' ), $newPath );            
        }
        $attributes = eZPersistentObject::fetchObjectList( eZContentObjectAttribute::definition(),
                                                           null,
                                                           array( 'id' => $item->attribute( 'contentobject_attribute_id' ) ) );                
        foreach( $attributes as $attribute )
        {                                    
            $newDataText = str_replace( $path, $varDir, $attribute->attribute( 'data_text' ) );
            $attribute->setAttribute( 'data_text', $newDataText );
            $attribute->store();               
        }
    }

    $script->shutdown();
}