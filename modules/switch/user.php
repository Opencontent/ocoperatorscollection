<?php
/** @var eZModule $module */
$module = $Params['Module'];
$UserName = $Params['User'];

$currentUser = eZUser::currentUser();
if ( $UserName == 'admin' )
{
    $currentUser->logoutCurrent();
}
else
{
    $user = eZUser::fetchByName( $UserName );
    if ( $user instanceof eZUser )
    {
        eZUser::setCurrentlyLoggedInUser( $user, $user->attribute( 'contentobject_id' ) );
    }
}

return $module->redirectTo( '/' );
    
?>